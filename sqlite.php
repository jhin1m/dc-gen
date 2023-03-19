<?php

$db = new SQLite3('webhooks.db');

$db->exec('CREATE TABLE IF NOT EXISTS webhooks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    guild_id INTEGER,
    channel_id INTEGER,
    webhook_id INTEGER,
    webhook_token TEXT
)');

$db->exec('CREATE UNIQUE INDEX IF NOT EXISTS noduplicate ON webhooks (guild_id, channel_id, webhook_id)');


// add column lock to table webhooks
$db->exec('ALTER TABLE webhooks ADD COLUMN lock INTEGER DEFAULT 0');