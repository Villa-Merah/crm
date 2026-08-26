<?php

declare(strict_types=1);

$output = $argv[1] ?? dirname(__DIR__) . '/_diagram_v1_3/cover-v1_3.png';
$version = $argv[2] ?? '1.3';
$directory = dirname($output);
if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
    throw new RuntimeException("Gagal membuat direktori {$directory}");
}

$width = 1240;
$height = 1754;
$image = imagecreatetruecolor($width, $height);
for ($y = 0; $y < $height; $y++) {
    $ratio = $y / max(1, $height - 1);
    for ($x = 0; $x < $width; $x++) {
        $horizontal = $x / max(1, $width - 1);
        $r = (int) (7 + 13 * (1 - $ratio) + 8 * $horizontal);
        $g = (int) (89 + 29 * (1 - $ratio) + 17 * $horizontal);
        $b = (int) (133 + 51 * (1 - $ratio) + 18 * $horizontal);
        imagesetpixel($image, $x, $y, imagecolorallocate($image, $r, $g, $b));
    }
}

imagealphablending($image, true);
$white = imagecolorallocate($image, 255, 255, 255);
$pale = imagecolorallocate($image, 221, 242, 255);
$light = imagecolorallocate($image, 188, 230, 255);
$outline = imagecolorallocatealpha($image, 255, 255, 255, 85);
$fill = imagecolorallocatealpha($image, 255, 255, 255, 105);

imagesetthickness($image, 3);
imageellipse($image, 1070, 210, 620, 620, $outline);
imageellipse($image, 110, 1440, 820, 820, $outline);
imageellipse($image, 940, 1480, 440, 440, $outline);
imagefilledrectangle($image, 80, 90, 320, 150, $fill);
imagerectangle($image, 80, 90, 320, 150, $outline);

$regular = 'C:/Windows/Fonts/arial.ttf';
$bold = 'C:/Windows/Fonts/arialbd.ttf';
imagettftext($image, 28, 0, 112, 132, $white, $bold, 'SIVMI CRM');
imagettftext($image, 34, 0, 82, 425, $light, $bold, 'DOKUMENTASI DAN PENGEMBANGAN SISTEM');
imagettftext($image, 72, 0, 82, 535, $white, $bold, 'CRM SIVMI');
imagefilledrectangle($image, 82, 640, 1110, 650, $outline);
imagettftext($image, 30, 0, 82, 735, $pale, $regular, 'Customer Relationship Management');
imagettftext($image, 30, 0, 82, 785, $pale, $regular, 'untuk operasional bimbingan belajar');
imagettftext($image, 24, 0, 84, 1365, $white, $bold, 'Versi sementara ' . $version);
imagettftext($image, 23, 0, 84, 1425, $white, $regular, 'Status: As-built dengan catatan gap');
imagettftext($image, 23, 0, 84, 1485, $white, $regular, '24 Agustus 2026');
imagettftext($image, 20, 0, 84, 1605, $light, $regular, 'Laravel 12 | Filament 5 | Livewire 4 | Tailwind CSS 4');

if (! imagepng($image, $output, 8)) {
    throw new RuntimeException("Gagal menyimpan {$output}");
}
imagedestroy($image);
echo $output . PHP_EOL;
