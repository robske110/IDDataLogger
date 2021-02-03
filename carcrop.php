<?php
$im = imagecreatefrompng("/usr/local/var/www/vwid3/car.png");
imagealphablending($im, true); // setting alpha blending on
imagesavealpha($im, true);
$cropped = imagecropauto($im, IMG_CROP_SIDES);
imagealphablending($cropped, true); // setting alpha blending on
imagesavealpha($cropped, true);
if($cropped !== false) { // in case a new image resource was returned
    imagedestroy($im);    // we destroy the original image
    $im = $cropped;       // and assign the cropped image to $im
}
imagepng($im, "/usr/local/var/www/vwid3/newcar.png");
imagedestroy($im);