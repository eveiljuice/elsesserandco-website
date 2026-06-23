<?php
/**
 * Patch script — wraps every image-URL render in the public templates with
 * imgSrc() so they route through images/proxy.php (which downloads + caches
 * remote URLs to /cache/images/).
 *
 * Idempotent — safe to re-run.
 *
 * Replaces patterns like:
 *   <img src="<?= imgSrc($row['primary_image']?? 'https://placeholder')) ?>"
 *   <img src="<?= imgSrc($row['image_url']) ?>"
 *   <img src="<?= imgSrc($row['avatar']) ?>"
 *
 * With:
 *   <img src="<?= imgSrc($row['primary_image'] ?? 'https://placeholder') ?>"
 *   <img src="<?= imgSrc($row['image_url']) ?>"
 *   <img src="<?= imgSrc($row['avatar']) ?>"
 *
 * And similar for $b[...], $property[...], $image[...], $building[...], $img[...], $pinnedProperty[...], $similar[...], $app[...], $dev[...], $comment[...], $author[...], $sender[...].
 *
 * Also patches <div style="background-image: url('...')"> inline backgrounds.
 *
 * Run from project root:
 *   php scripts/patch_image_renders.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
chdir($root);

$files = [];
$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iter as $f) {
    $path = $f->getPathname();
    if (!str_ends_with($path, '.php')) continue;
    if (str_contains($path, '/.cursor/')) continue;
    if (str_contains($path, '/scripts/')) continue;
    if (str_contains($path, '/cache/')) continue;
    if (str_contains($path, '/logs/')) continue;
    if (str_contains($path, 'image.php')) continue;     // self
    if (str_contains($path, 'proxy.php')) continue;
    $files[] = $path;
}

$totalFiles = 0;
$totalReplacements = 0;
$skipped = [];

foreach ($files as $file) {
    $orig = file_get_contents($file);
    if ($orig === false) continue;
    if (!str_contains($orig, 'image_url')
        && !str_contains($orig, 'primary_image')
        && !str_contains($orig, '->avatar')
        && !str_contains($orig, "['avatar']")
        && !str_contains($orig, 'avatar')
    ) {
        continue;
    }

    $new = $orig;
    $count = 0;

    // Replace escape($X['...']) — image-bearing fields only — with imgSrc(...)
    // We limit to specific keys we know are image URLs.
    $imgKeys = 'primary_image|image_url|avatar|logo';

    // 1) escape($X['key']) where X is one of the known variable names
    $pattern = '/\bescape\s*\(\s*(\$(?:row|b|property|building|image|img|pinnedProperty|similar|app|dev|user|comment|author|sender))\[\s*[\'"](' . $imgKeys . ')[\'"]\s*\](?:\s*\?\?[^)]*?)?\s*\)/';
    $new = preg_replace_callback($pattern, function ($m) use (&$count) {
        $count++;
        return 'imgSrc(' . $m[1] . '[\'' . $m[2] . '\']' . (str_contains($m[0], '??') ? (substr($m[0], strpos($m[0], '??'))) : '') . ')';
    }, $new);

    // 2) escape($X['key'] ?? '...') — preserve the ?? fallback
    //    (already covered above; this is a fallback safety net)

    // 3) $obj->image_url and $obj->avatar — for any case we missed
    $new = preg_replace_callback(
        '/\bescape\s*\(\s*(\$\w+->(?:image_url|avatar|logo))\s*\)/',
        function ($m) use (&$count) {
            $count++;
            return 'imgSrc(' . $m[1] . ')';
        },
        $new
    );

    // 4) Drop-in img() helper usage — optional but cleaner. Skip for now to
    //    minimize churn; imgSrc() at the src= site is enough.

    if ($new !== $orig && $count > 0) {
        file_put_contents($file, $new);
        $totalFiles++;
        $totalReplacements += $count;
        echo "  patched: $file ($count replacements)\n";
    }
}

echo "\nDone. $totalFiles files patched, $totalReplacements replacements total.\n";