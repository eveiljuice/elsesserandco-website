<?php
/**
 * Image helper.
 *
 * Render-time URL normalizer for <img src> attributes.
 *
 *   img($url)                                 -> /images/proxy.php?u=<urlencoded>
 *   img($url, 'class="x"', 'alt="y"')         -> full <img> tag
 *   imgSrc($url)                              -> src string only
 *
 * Rules:
 *   - empty / null  -> placeholder via placeholder.com fallback (also proxied)
 *   - already local path (starts with '/' or 'images/' or 'uploads/' or 'cache/') -> as-is
 *   - data: URI     -> as-is
 *   - everything else (http://, https://, //cdn...) -> routed through proxy.php
 *
 * The proxy downloads + caches the remote file under /cache/images/ forever,
 * so the user's browser always hits our own domain (works in Russia without VPN),
 * and we never hammer the upstream CDN on repeat views.
 */

declare(strict_types=1);

if (!function_exists('imgSrc')) {
    /**
     * Resolve a possibly-external image URL to one our domain can serve in RU.
     */
    function imgSrc(?string $url): string
    {
        $url = trim((string)$url);

        if ($url === '') {
            return imgSrc('https://via.placeholder.com/800x500?text=No+image');
        }

        // data: URI or already local — leave untouched
        if (str_starts_with($url, 'data:')) {
            return $url;
        }
        if ($url[0] === '/' || $url[0] === '.') {
            return $url;
        }
        if (preg_match('#^(images/|uploads/|cache/|assets/|css/|js/|favicon)#i', $url)) {
            return $url;
        }
        if (!preg_match('#^https?://#i', $url) && !str_starts_with($url, '//')) {
            return $url;
        }

        // External URL — proxy through us.
        // Use a relative URL so it works on both http://localhost and the production domain.
        return '/images/proxy.php?u=' . rawurlencode($url);
    }
}

if (!function_exists('img')) {
    /**
     * Render a full <img> tag.
     *
     * Usage:
     *   <?= img($row['image_url']) ?>
     *   <?= img($row['avatar'], 'class="avatar"', 'alt="' . escape($row['name']) . '"') ?>
     */
    function img(?string $url, string ...$attrs): string
    {
        $src = imgSrc($url);
        $parts = ['src="' . htmlspecialchars($src, ENT_QUOTES) . '"'];
        foreach ($attrs as $a) {
            $parts[] = $a;
        }
        return '<img ' . implode(' ', $parts) . '>';
    }
}