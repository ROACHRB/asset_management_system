<?php
// File: modules/tagging/generate_barcode_svg.php

/**
 * Simple SVG Barcode Generator
 * No external libraries or GD required
 */

// Check if the text parameter is provided
$text = isset($_GET['text']) ? $_GET['text'] : 'Default Text';

// Code 128 character encoding
function getCode128Encoding($char) {
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
    
    return isset($code_array[$char]) ? $code_array[$char] : $code_array[" "];
}

// Generate SVG for Code 128
function generateSVGBarcode($text) {
    // Barcode settings
    $height = 70;
    $barWidth = 2;
    $margin = 10;
    $textMargin = 5;
    
    // Start and stop codes for Code 128 (B)
    $startCode = "211214"; // Code 128 Start B pattern
    $stopCode = "2331112"; // Code 128 Stop pattern
    
    // Generate pattern from text
    $pattern = $startCode;
    foreach (str_split($text) as $char) {
        $pattern .= getCode128Encoding($char);
    }
    $pattern .= $stopCode;
    
    // Calculate width based on pattern
    $width = 0;
    for ($i = 0; $i < strlen($pattern); $i++) {
        $width += intval($pattern[$i]) * $barWidth;
    }
    $width += 2 * $margin;
    
    // Start SVG document
    $svg = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $svg .= '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '">' . "\n";
    $svg .= '<rect width="100%" height="100%" fill="white" />' . "\n";
    
    // Draw barcode
    $x = $margin;
    for ($i = 0; $i < strlen($pattern); $i++) {
        $barThickness = intval($pattern[$i]) * $barWidth;
        // Only draw black bars (even positions in the pattern)
        if ($i % 2 == 0) {
            $svg .= '<rect x="' . $x . '" y="' . $margin . '" width="' . $barThickness . '" height="' . ($height - $margin * 2 - 20) . '" fill="black" />' . "\n";
        }
        $x += $barThickness;
    }
    
    // Add text
    $svg .= '<text x="' . ($width / 2) . '" y="' . ($height - $margin) . '" text-anchor="middle" font-family="Arial" font-size="12">' . htmlspecialchars($text) . '</text>' . "\n";
    
    // End SVG document
    $svg .= '</svg>';
    
    return $svg;
}

// Set content type
header('Content-Type: image/svg+xml');
header('Cache-Control: max-age=86400');

// Output SVG
echo generateSVGBarcode($text);