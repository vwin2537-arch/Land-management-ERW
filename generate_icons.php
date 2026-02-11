<?php
// Create icons directory if not exists
if (!file_exists(__DIR__ . '/assets/icons')) {
    mkdir(__DIR__ . '/assets/icons', 0777, true);
}

function createIcon($size, $filename) {
    $im = imagecreatetruecolor($size, $size);
    $bg = imagecolorallocate($im, 22, 101, 52); // Forest Green #166534
    $white = imagecolorallocate($im, 255, 255, 255);
    
    imagefill($im, 0, 0, $bg);
    
    // Draw a simple tree shape (triangle and rectangle)
    // Scale factor
    $s = $size / 512;
    
    // Trunk
    imagefilledrectangle($im, 236*$s, 350*$s, 276*$s, 450*$s, $white);
    
    // Leaves (Triangle)
    $points = [
        256*$s, 100*$s,  // Top
        100*$s, 350*$s,  // Bottom Left
        412*$s, 350*$s   // Bottom Right
    ];
    imagefilledpolygon($im, $points, 3, $white);
    
    imagepng($im, __DIR__ . '/assets/icons/' . $filename);
    imagedestroy($im);
    echo "Created $filename ($size x $size)\n";
}

createIcon(192, 'icon-192.png');
createIcon(512, 'icon-512.png');
?>
