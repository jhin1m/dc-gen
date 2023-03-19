<?php

const DISCORD_TOKEN = 'MTAzNDQwODA5NDMyMDU1ODEwMA.GnUPU4.Vdzu7PuSYPzbEme9j-tQM5VOO_YBSbtzxEI8a4';


require_once __DIR__ . '/vendor/autoload.php';
include_once __DIR__ . '/webhook.php';


class Discord
{
    private $client;

    public function __construct()
    {
        $this->client = new \GuzzleHttp\Client([
            'headers' => [
                'authorization' => DISCORD_TOKEN,
            ],
            // 'http_errors' => false,
            // if 429 is returned, wait 1 second and try again
            'retries' => 1,
            'delay' => 1000,
        ]);
    }

    public function getGuilds()
    {
        $response = $this->client->request('GET', 'https://discordapp.com/api/v9/users/@me/guilds');
        return json_decode($response->getBody()->getContents(), true);
    }

    public function getChannels($guildId)
    {
        $response = $this->client->request('GET', "https://discordapp.com/api/v9/guilds/$guildId/channels");
        return json_decode($response->getBody()->getContents(), true);
    }

    public function createChannel($guildId, $name)
    {
        $response = $this->client->request('POST', "https://discordapp.com/api/v9/guilds/$guildId/channels", [
            'json' => [
                'name' => $name,
                'type' => 0,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    // get webhooks of a channel
    public function getWebhooks($channelId)
    {
        $response = $this->client->request('GET', "https://discordapp.com/api/v9/channels/$channelId/webhooks");
        return json_decode($response->getBody()->getContents(), true);
    }

    // create a webhook
    public function createWebhook($channelId, $name)
    {
        $response = $this->client->request('POST', "https://discordapp.com/api/v9/channels/$channelId/webhooks", [
            'json' => [
                'name' => $name,
            ],
        ]);
        return json_decode($response->getBody()->getContents(), true);
    }
}

$db = $GLOBALS['sqlite'];

$discord = new Discord();

$guilds = $discord->getGuilds();


foreach ($guilds as $guild) {
    echo $guild['name'] . PHP_EOL;

    $channels = $discord->getChannels($guild['id']);
    foreach ($channels as $channel) {
        //  if channel is a text channel
        if ($channel['type'] === 0) {
            echo '  ' . $channel['name'] . PHP_EOL;
            $webhooks = $discord->getWebhooks($channel['id']);

            foreach ($webhooks as $webhook) {
                $db->exec("INSERT OR IGNORE INTO webhooks (guild_id, channel_id, webhook_id, webhook_token, lock) VALUES (
                    {$guild['id']},
                    {$channel['id']},
                    {$webhook['id']},
                    '{$webhook['token']}',
                    0
                )");
            }


            $count = count($webhooks);
            // if < 10 webhooks, create one
            if ($count < 10) {
                $webhook = $discord->createWebhook($channel['id'], 'Webhook ' . ($count + 1));

                $db->exec("INSERT OR IGNORE INTO webhooks (guild_id, channel_id, webhook_id, webhook_token, lock) VALUES (
                    {$guild['id']},
                    {$channel['id']},
                    {$webhook['id']},
                    '{$webhook['token']}',
                    0
                )");

                break;
            }
        }
    }

    echo PHP_EOL;
}

// run this script a gain
chdir(__DIR__);
system('php discord.php');