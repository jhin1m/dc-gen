<?php


$image = file_get_contents('https://i.ibb.co/stNrvZj/01.jpg');

function splitImage($image)
{
    $img = imagecreatefromstring($image);

    $width = imagesx($img);
    $height = imagesy($img);
    $maxHeight = 1200;

    if ($height < $maxHeight) {
        return [
            $image
        ];
    }

    // split image to max 1000 height

    $images = [];

    for ($i = 0; $i < $height; $i += $maxHeight) {
        $newHeight = $i + $maxHeight > $height ? $height - $i : $maxHeight;
        $newImage = imagecreatetruecolor($width, $newHeight);

        imagecopy($newImage, $img, 0, 0, 0, $i, $width, $newHeight);

        ob_start();
        imagejpeg($newImage);
        $images[] = ob_get_clean();

        imagedestroy($newImage);
    }

    return $images;
}

$images = splitImage($image);

foreach ($images as $i => $image) {
    file_put_contents("tmp/{$i}.jpg", $image);
}