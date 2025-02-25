<?php
// File: modules/tagging/generate_qr.php

// Get text parameter 
$text = isset($_GET['text']) ? $_GET['text'] : 'Default Text';

// Set content type header
header('Content-Type: image/png');

// Create a simple QR-like image using PHP GD
$size = 200;
$padding = 20;
$actual_size = $size - (2 * $padding);
$cell_size = floor($actual_size / 8); // 8x8 grid for simple QR

// Create image
$im = imagecreate($size, $size);
$white = imagecolorallocate($im, 255, 255, 255);
$black = imagecolorallocate($im, 0, 0, 0);

// Fill background
imagefilledrectangle($im, 0, 0, $size - 1, $size - 1, $white);

// Draw a basic QR pattern (positioning blocks in corners)
// Top-left corner
imagefilledrectangle($im, $padding, $padding, $padding + (3 * $cell_size), $padding + (3 * $cell_size), $black);
imagefilledrectangle($im, $padding + $cell_size, $padding + $cell_size, $padding + (2 * $cell_size), $padding + (2 * $cell_size), $white);

// Top-right corner
imagefilledrectangle($im, $size - $padding - (3 * $cell_size), $padding, $size - $padding, $padding + (3 * $cell_size), $black);
imagefilledrectangle($im, $size - $padding - (2 * $cell_size), $padding + $cell_size, $size - $padding - $cell_size, $padding + (2 * $cell_size), $white);

// Bottom-left corner
imagefilledrectangle($im, $padding, $size - $padding - (3 * $cell_size), $padding + (3 * $cell_size), $size - $padding, $black);
imagefilledrectangle($im, $padding + $cell_size, $size - $padding - (2 * $cell_size), $padding + (2 * $cell_size), $size - $padding - $cell_size, $white);

// Generate a simple pattern based on text
$hash = md5($text);
for ($i = 0; $i < 24; $i++) {
    $val = hexdec($hash[$i % strlen($hash)]);
    if ($val > 7) { // Fill cell if value > 7 (about half the time)
        $x = $padding + ((($i % 6) + 1) * $cell_size);
        $y = $padding + ((floor($i / 6) + 1) * $cell_size);
        imagefilledrectangle($im, $x, $y, $x + $cell_size, $y + $cell_size, $black);
    }
}

// Add some text
$font_size = 3;
$font_width = imagefontwidth($font_size);
$font_height = imagefontheight($font_size);

// Calculate text width and ensure it fits
$short_text = substr($text, 0, 20) . (strlen($text) > 20 ? '...' : '');
$text_width = $font_width * strlen($short_text);
$text_x = ($size - $text_width) / 2;
$text_y = $size - 15;
imagestring($im, $font_size, $text_x, $text_y, $short_text, $black);

// Output image
imagepng($im);
imagedestroy($im);