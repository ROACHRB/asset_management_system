<?php
// Save as test_gd.php
echo "<h1>Testing GD Library</h1>";

// Check if GD is loaded
if (extension_loaded('gd')) {
    echo "<p style='color:green'>GD Extension is loaded.</p>";
    
    // Check GD info
    $gd_info = gd_info();
    echo "<pre>";
    print_r($gd_info);
    echo "</pre>";
    
    // Try to create a simple image
    echo "<p>Attempting to create a test image:</p>";
    try {
        $im = @imagecreatetruecolor(100, 100);
        if ($im) {
            echo "<p style='color:green'>Successfully created image using imagecreatetruecolor().</p>";
            
            // Add some content to the image
            $text_color = imagecolorallocate($im, 233, 14, 91);
            imagestring($im, 1, 5, 5, 'GD Test', $text_color);
            
            // Set the content type header for the image
            header('Content-Type: image/png');
            
            // Output the image
            imagepng($im);
            
            // Free up memory
            imagedestroy($im);
            exit; // Stop execution after outputting image
        } else {
            echo "<p style='color:red'>Failed to create image with imagecreatetruecolor().</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color:red'>GD Extension is NOT loaded.</p>";
}
?>