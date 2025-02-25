<?php
// File: modules/tagging/generate_barcode.php

// Check if the text parameter is provided
$text = isset($_GET['text']) ? $_GET['text'] : 'Default Text';

// Barcode generation using pure PHP
function generateBarcode128($text) {
    $code_array = [
        " " => "212222", "!" => "222122", "\"" => "222221", "#" => "121223", "$" => "121322",
        "%" => "131222", "&" => "122213", "'" => "122312", "(" => "132212", ")" => "221213",
        "*" => "221312", "+" => "231212", "," => "112232", "-" => "122132", "." => "122231",
        "/" => "113222", "0" => "123122", "1" => "123221", "2" => "223211", "3" => "221132",
        "4" => "221231", "5" => "213212", "6" => "223112", "7" => "312131", "8" => "311222",
        "9" => "321122", ":" => "321221", ";" => "312212", "<" => "322112", "=" => "322211",
        ">" => "212123", "?" => "212321", "@" => "232121", "A" => "111323", "B" => "131123",
        "C" => "131321", "D" => "112313", "E" => "132113", "F" => "132311", "G" => "211313",
        "H" => "231113", "I" => "231311", "J" => "112133", "K" => "112331", "L" => "132131",
        "M" => "113123", "N" => "113321", "O" => "133121", "P" => "313121", "Q" => "211331",
        "R" => "231131", "S" => "213113", "T" => "213311", "U" => "213131", "V" => "311123",
        "W" => "311321", "X" => "331121", "Y" => "312113", "Z" => "312311", "[" => "332111",
        "\\" => "314111", "]" => "221411", "^" => "431111", "_" => "111224", "`" => "111422",
        "a" => "121124", "b" => "121421", "c" => "141122", "d" => "141221", "e" => "112214",
        "f" => "112412", "g" => "122114", "h" => "122411", "i" => "142112", "j" => "142211",
        "k" => "241211", "l" => "221114", "m" => "413111", "n" => "241112", "o" => "134111",
        "p" => "111242", "q" => "121142", "r" => "121241", "s" => "114212", "t" => "124112",
        "u" => "124211", "v" => "411212", "w" => "421112", "x" => "421211", "y" => "212141",
        "z" => "214121", "{" => "412121", "|" => "111143", "}" => "111341", "~" => "131141"
    ];
    
    // Start and stop patterns
    $startChar = 'Ì'; // ASCII 204
    $stopChar = 'Î'; // ASCII 206
    
    // Calculate checksum
    $checksum = 104; // Start B = 104
    for($i = 0; $i < strlen($text); $i++) {
        $char = $text[$i];
        if(!isset($code_array[$char])) {
            $char = ' '; // Default to space if character not supported
        }
        $checksum += (strpos(" !\"#$%&'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^_`abcdefghijklmnopqrstuvwxyz{|}~", $char) + 32) * ($i + 1);
    }
    $checksum %= 103;
    
    // Determine check character
    $checkChar = " !\"#$%&'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\]^_`abcdefghijklmnopqrstuvwxyz{|}~"[($checksum + 32) % 95];
    
    // Create the full code
    $fullCode = $startChar . $text . $checkChar . $stopChar;
    
    // Create pattern string
    $pattern = '';
    for($i = 0; $i < strlen($fullCode); $i++) {
        $char = $fullCode[$i];
        if($char == $startChar) {
            $pattern .= '211214'; // Code 128 Start B pattern
        } elseif($char == $stopChar) {
            $pattern .= '2331112'; // Code 128 Stop pattern
        } else {
            if(isset($code_array[$char])) {
                $pattern .= $code_array[$char];
            } else {
                $pattern .= $code_array[' ']; // Default to space
            }
        }
    }
    
    return $pattern;
}

// Image settings
$height = 70;
$width = 400;
$margin = 10;
$fontSize = 12;
$barWidth = 2; // Width multiplier for bars

// Create image
$im = imagecreatetruecolor($width, $height);
$white = imagecolorallocate($im, 255, 255, 255);
$black = imagecolorallocate($im, 0, 0, 0);
imagefilledrectangle($im, 0, 0, $width - 1, $height - 1, $white);

// Generate barcode pattern
$pattern = generateBarcode128($text);

// Draw barcode
$x = $margin;
$y = $margin;
$barHeight = $height - 30; // Space for text below

for($i = 0; $i < strlen($pattern); $i++) {
    $thickness = intval($pattern[$i]) * $barWidth;
    
    // Draw bar or space
    if($i % 2 == 0) {
        imagefilledrectangle($im, $x, $y, $x + $thickness - 1, $y + $barHeight, $black);
    }
    
    $x += $thickness;
}

// Add text below the barcode
$textWidth = imagefontwidth($fontSize) * strlen($text);
$textX = ($width - $textWidth) / 2;
$textY = $height - 20;
imagestring($im, $fontSize, $textX, $textY, $text, $black);

// Output image
header('Content-Type: image/png');
imagepng($im);
imagedestroy($im);