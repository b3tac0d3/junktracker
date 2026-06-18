<?php

declare(strict_types=1);

/**
 * Generate simple JunkMetrix PWA icons (requires PHP GD).
 * Usage: php scripts/generate-pwa-icons.php
 */

if (!extension_loaded('gd')) {
    fwrite(STDERR, "GD extension required.\n");
    exit(1);
}

$outDir = dirname(__DIR__) . '/public/assets/icons';
if (!is_dir($outDir) && !mkdir($outDir, 0755, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Could not create {$outDir}\n");
    exit(1);
}

$sizes = [
    'icon-192.png' => 192,
    'icon-512.png' => 512,
    'icon-512-maskable.png' => 512,
];

foreach ($sizes as $filename => $size) {
    $img = imagecreatetruecolor($size, $size);
    if ($img === false) {
        continue;
    }

    $bg = imagecolorallocate($img, 33, 37, 41);
    $accent = imagecolorallocate($img, 13, 110, 253);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagefilledrectangle($img, 0, 0, $size, $size, $bg);

    $padding = (int) round($size * 0.18);
    imagefilledrectangle($img, $padding, $padding, $size - $padding, $size - $padding, $accent);

    $letter = 'J';
    $fontSize = (int) round($size * 0.34);
    $box = imagettfbbox($fontSize, 0, '/System/Library/Fonts/Supplemental/Arial Bold.ttf', $letter);
    if ($box === false) {
        imagestring($img, 5, (int) ($size / 2 - 8), (int) ($size / 2 - 10), $letter, $white);
    } else {
        $textWidth = abs($box[2] - $box[0]);
        $textHeight = abs($box[7] - $box[1]);
        $x = (int) (($size - $textWidth) / 2);
        $y = (int) (($size + $textHeight) / 2);
        @imagettftext($img, $fontSize, 0, $x, $y, $white, '/System/Library/Fonts/Supplemental/Arial Bold.ttf', $letter);
        if (!file_exists('/System/Library/Fonts/Supplemental/Arial Bold.ttf')) {
            imagestring($img, 5, (int) ($size / 2 - 8), (int) ($size / 2 - 10), $letter, $white);
        }
    }

    $path = $outDir . '/' . $filename;
    imagepng($img, $path);
    imagedestroy($img);
    echo "Wrote {$path}\n";
}

echo "Done.\n";
