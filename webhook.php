<?php


if (!class_exists('GuzzleHttp\Client')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

if (!file_exists(__DIR__ . '/webhooks.db')) {
    touch(__DIR__ . '/webhooks.db');
}

if(!isset($GLOBALS['sqlite'])){
    $GLOBALS['sqlite'] = new SQLite3(__DIR__ . '/webhooks.db');
    $GLOBALS['sqlite']->busyTimeout(5000);
}


function getWebhookUrl()
{
    $db = $GLOBALS['sqlite'];

    $webhook = $db->querySingle("SELECT * FROM webhooks WHERE lock = 0 LIMIT 1", true);

    if (!$webhook) {
        // unlock all webhooks and try again
        $db->exec("UPDATE webhooks SET lock = 0");

        $webhook = $db->querySingle("SELECT * FROM webhooks WHERE lock = 0 LIMIT 1", true);
    }

    // update lock
    $db->exec("UPDATE webhooks SET lock = 1 WHERE id = {$webhook['id']}");

    return "https://discord-api-cdn.b-cdn.net/api/webhooks/{$webhook['webhook_id']}/{$webhook['webhook_token']}";
}

function splitImage($image)
{
    $img = @imagecreatefromstring($image);
    if (!$img) {
        return [
            $image
        ];
    }

    $width = imagesx($img);
    $height = imagesy($img);
    $maxHeight = 1200;

    if ($height < $maxHeight) {
        return [
            $image
        ];
    }


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

// upload file via webhook url
function imagesViaWebhook($images)
{

    $newImages = [];
    // slice images
    foreach ($images as $i => $image) {
        foreach (splitImage($image) as $j => $newImage) {
            $newImages[] = $newImage;
        }
    }

    unset($images);

    $client = new GuzzleHttp\Client([
        'timeout' => 10,
        'retries' => 2,
        'delay' => 5000,
        // 'http_errors' => false,
        'verify' => false,
    ]);

    $promises = [];
    $urls = [];

    foreach ($newImages as $i => $image) {
        $promises[] = $client->postAsync(getWebhookUrl(), [
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => $image,
                    'filename' => "{$i}.jpg"
                ],
                [
                    'name' => 'payload_json',
                    'contents' => json_encode([
                        'username' => 'Image Uploader',
                    ])
                ]
            ],
        ])->then(function ($response) use ($i, &$urls) {
            $body = json_decode($response->getBody()->getContents(), true);

            if (!isset($body['attachments'][0]['url'])) {

                file_put_contents(__DIR__ . '/error-upload.log', $response->getBody()->getContents() . PHP_EOL, FILE_APPEND);
                
                throw new Exception('No attachment url');
            }


            $urls[$i] = $body['attachments'][0]['url'];

            echo "Image {$i} uploaded\n";
        });
    }

    unset($newImages);

    // wait for all of the requests to complete
    \GuzzleHttp\Promise\Utils::unwrap($promises);

    ksort($urls);
    
    return $urls;
}

