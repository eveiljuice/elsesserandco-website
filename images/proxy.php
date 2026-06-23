<?php
/**
 * Image proxy — downloads remote images on behalf of the user's browser.
 *
 * Why: real-estate photos come from CDNs that are blocked by some Russian ISPs.
 *      We download once, cache to disk, then serve straight from our own domain.
 *
 * Routing (all defined in includes/image.php):
 *   /images/proxy.php?u=<urlencoded_external_url>
 *
 * Cache layout:
 *   /cache/images/<sha256-of-url>.<ext>
 *
 * Cache-Control is long (30 days), and we attach a Last-Modified so browsers
 * can revalidate cheaply.
 */

declare(strict_types=1);

// Block direct browser navigation / directory listings.
if (PHP_SAPI !== 'cli' && empty($_GET['u'])) {
    http_response_code(400);
    exit('Missing ?u=');
}

// We intentionally avoid requiring bootstrap.php / DB / session here so the
// proxy stays cheap and side-effect free.

const CACHE_DIR = __DIR__ . '/../cache/images';
const CACHE_TTL = 60 * 60 * 24 * 30; // 30 days

function proxyFail(int $code, string $msg): void
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    // Tiny inline transparent 1x1 GIF so <img> tags degrade silently rather
    // than showing the browser's broken-image icon.
    echo base64_decode(
        'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'
    );
    exit;
}

function cachePath(string $key, string $ext): string
{
    return CACHE_DIR . '/' . $key . '.' . $ext;
}

function pickExt(string $contentType, string $url): string
{
    $ct = strtolower($contentType);
    if (str_contains($ct, 'jpeg') || str_contains($ct, 'jpg')) return 'jpg';
    if (str_contains($ct, 'png'))  return 'png';
    if (str_contains($ct, 'webp')) return 'webp';
    if (str_contains($ct, 'gif'))  return 'gif';
    if (str_contains($ct, 'svg'))  return 'svg';
    if (str_contains($ct, 'avif')) return 'avif';

    // Fall back to URL path extension
    $path = parse_url($url, PHP_URL_PATH) ?: '';
    $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg','jpeg','png','webp','gif','svg','avif','jfif'], true)) {
        return $ext === 'jpeg' ? 'jpg' : $ext;
    }
    return 'jpg';
}

function sniffExtFromBytes(string $bytes): ?string
{
    if (strlen($bytes) < 12) return null;
    $h = substr($bytes, 0, 12);
    if (str_starts_with($h, "\xFF\xD8\xFF")) return 'jpg';
    if (str_starts_with($h, "\x89PNG\r\n\x1a\n")) return 'png';
    if (str_starts_with($h, 'GIF87a') || str_starts_with($h, 'GIF89a')) return 'gif';
    if (str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP') return 'webp';
    if (str_starts_with($h, "<svg") || str_starts_with($h, "<?xml")) return 'svg';
    return null;
}

// ----- input --------------------------------------------------------------

$rawUrl = (string)($_GET['u'] ?? '');
if ($rawUrl === '') {
    proxyFail(400, 'Missing url');
}
$url = filter_var($rawUrl, FILTER_VALIDATE_URL);
if (!$url || !preg_match('#^https?://#i', $url)) {
    proxyFail(400, 'Bad url');
}

$key = hash('sha256', $url);

// ----- cache hit fast path -------------------------------------------------

if (!is_dir(CACHE_DIR)) {
    @mkdir(CACHE_DIR, 0775, true);
}

$existing = glob(CACHE_DIR . '/' . $key . '.*');
if ($existing) {
    $path = $existing[0];
    $ext  = pathinfo($path, PATHINFO_EXTENSION);
    $mtime = (int)@filemtime($path);

    // Re-validate using If-Modified-Since when present.
    $ims = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
    if ($ims && strtotime($ims) >= $mtime) {
        header('HTTP/1.1 304 Not Modified');
        header("Last-Modified: " . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
        exit;
    }

    $mime = match ($ext) {
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'avif' => 'image/avif',
        'jfif' => 'image/jpeg',
        default => 'image/jpeg',
    };

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: public, max-age=2592000, immutable');
    header("Last-Modified: " . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    header('X-Image-Proxy: cache-hit');
    readfile($path);
    exit;
}

// ----- fetch upstream -----------------------------------------------------

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_CONNECTTIMEOUT => 6,
    CURLOPT_TIMEOUT        => 25,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
    CURLOPT_ACCEPT_ENCODING => '',
    CURLOPT_HTTPHEADER     => [
        'Accept: image/avif,image/webp,image/png,image/jpeg,image/*,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.9,ru;q=0.8',
    ],
]);
$body = curl_exec($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$ctype = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$err = curl_error($ch);
curl_close($ch);

if ($body === false || $code < 200 || $code >= 300 || strlen($body) < 200) {
    error_log("image_proxy: upstream failed url=$url code=$code err=$err");
    proxyFail(502, 'Upstream fetch failed');
}

// ----- persist to cache ---------------------------------------------------

$ext = pickExt($ctype, $url);
$sniff = sniffExtFromBytes($body);
if ($sniff !== null) {
    $ext = $sniff;
}
$path = cachePath($key, $ext);
@file_put_contents($path, $body, LOCK_EX);
@chmod($path, 0664);

// ----- serve --------------------------------------------------------------

$mime = match ($ext) {
    'png'  => 'image/png',
    'webp' => 'image/webp',
    'gif'  => 'image/gif',
    'svg'  => 'image/svg+xml',
    'avif' => 'image/avif',
    'jfif' => 'image/jpeg',
    default => 'image/jpeg',
};

header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($body));
header('Cache-Control: public, max-age=2592000, immutable');
header('X-Image-Proxy: cache-miss');
echo $body;