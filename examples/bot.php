<?php declare(strict_types=1);
/**
 * Example bot.
 *
 * Copyright 2016-2020 Daniil Gentili
 * (https://daniil.it)
 * This file is part of MadelineProto.
 * MadelineProto is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * MadelineProto is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU General Public License along with MadelineProto.
 * If not, see <http://www.gnu.org/licenses/>.
 *
 * @author    Daniil Gentili <daniil@daniil.it>
 * @copyright 2016-2023 Daniil Gentili <daniil@daniil.it>
 * @license   https://opensource.org/licenses/AGPL-3.0 AGPLv3
 * @link https://docs.madelineproto.xyz MadelineProto documentation
 */

use danog\MadelineProto\Broadcast\Progress;
use danog\MadelineProto\Broadcast\Status;
use danog\MadelineProto\Db\DbArray;
use danog\MadelineProto\EventHandler;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\Database\Mysql;
use danog\MadelineProto\Settings\Database\Postgres;
use danog\MadelineProto\Settings\Database\Redis;
use danog\MadelineProto\Settings\Database\SerializerType;

// If a stable version of MadelineProto was installed via composer, load composer autoloader
if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
} else {
    // Otherwise download an !!! alpha !!! version of MadelineProto via madeline.php
    if (!file_exists('madeline.php')) {
        copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.php');
    }
    require_once 'madeline.php';
}
/**
 * Event handler class.
 */
class MyEventHandler extends EventHandler
{
    /**
     * @var int|string Username or ID of bot admin
     */
    const ADMIN = "@me"; // !!! Change this to your username !!!

    /**
     * List of properties automatically stored in database (MySQL, Postgres, redis or memory).
     *
     * Note that **all** class properties will be stored in the database, regardless of whether they're specified here.
     * The only difference is that properties *not* specified in this array will also always have a full copy in RAM.
     *
     * Also, properties specified in this array are NOT thread-safe, meaning you should also use a synchronization primitive
     * from https://github.com/amphp/sync/ to use them in a thread-safe manner (i.e. when checking-and-setting with isset=>set, et cetera).
     *
     * Remember: **ALL** onUpdate... handler methods are called in separate green threads, so at the end of the day,
     * unless your property is too large to be comfortably stored in memory (say >100MB), you should use normal properties to avoid race conditions.
     *
     * @see https://docs.madelineproto.xyz/docs/DATABASE.html
     */
    protected static array $dbProperties = [
        'dataStoredOnDb' => ['serializer' => SerializerType::SERIALIZE],
    ];

    /**
     * Use this *only* if the data you will store here is huge (>100MB).
     * @var DbArray<array-key, array>
     */
    protected DbArray $dataStoredOnDb;

    /**
     * Otherwise use this.
     * This property is also saved in the db, but it's also always kept in memory, unlike $dataStoredInDb which is exclusively stored in the db.
     */
    protected array $dataAlsoStoredOnDbAndInRam = [];

    /**
     * This property is also saved in the db, but it's also always kept in memory, unlike $dataStoredInDb which is exclusively stored in the db.
     * @var array<int, bool>
     */
    protected array $notifiedChats = [];

    /**
     * This property is also saved in the db, but it's also always kept in memory, unlike $dataStoredInDb which is exclusively stored in the db.
     */
    private int $adminId;

    /**
     * Get peer(s) where to report errors.
     *
     * @return int|string|array
     */
    public function getReportPeers()
    {
        return [self::ADMIN];
    }
    /**
     * Initialization logic.
     */
    public function onStart(): void
    {
        $this->logger("The bot was started!");
        $this->logger($this->getFullInfo('MadelineProto'));
        $this->adminId = $this->getId(self::ADMIN);

        if ($this->getSelf()['bot'] && $this->getSelf()['id'] === $this->adminId) {
            return;
        }
        $this->messages->sendMessage(
            peer: self::ADMIN,
            message: "The bot was started!"
        );
    }

    private int $lastLog = 0;
    /**
     * Handles updates to an in-progress broadcast.
     */
    public function onUpdateBroadcastProgress(Progress $progress): void
    {
        if (time() - $this->lastLog > 5 || $progress->status === Status::FINISHED) {
            $this->lastLog = time();
            $this->messages->sendMessage(
                peer: self::ADMIN,
                message: (string) $progress
            );
        }
    }

    /**
     * Handle updates from supergroups and channels.
     *
     * @param array $update Update
     */
    public function onUpdateNewChannelMessage(array $update): void
    {
        $this->onUpdateNewMessage($update);
    }

    /**
     * Handle updates from users.
     *
     * 100+ other types of onUpdate... method types are available, see https://docs.madelineproto.xyz/API_docs/types/Update.html for the full list.
     * You can also use onAny to catch all update types (only for debugging)
     * A special onUpdateCustomEvent method can also be defined, to send messages to the event handler from an API instance, using the sendCustomEvent method.
     *
     * @param array $update Update
     */
    public function onUpdateNewMessage(array $update): void
    {
        if ($update['message']['_'] === 'messageEmpty') {
            return;
        }

        $this->logger($update);

        // Chat ID
        $id = $this->getId($update);

        // Sender ID, not always present
        $from_id = isset($update['message']['from_id'])
            ? $this->getId($update['message']['from_id'])
            : null;

        // In this example code, send the "This userbot is powered by MadelineProto!" message only once per chat.
        // Ignore all further messages coming from this chat.
        if (!isset($this->notifiedChats[$id])) {
            $this->notifiedChats[$id] = true;

            $this->messages->sendMessage(
                peer: $update,
                message: "This userbot is powered by [MadelineProto](https://t.me/MadelineProto)!",
                reply_to_msg_id: $update['message']['id'] ?? null,
                parse_mode: 'Markdown'
            );
        }

        // If the message is a /restart command from an admin, restart to reload changes to the event handler code.
        if (($update['message']['message'] ?? '') === '/restart'
            && $from_id === $this->adminId
        ) {
            // Make sure to run in a bash while loop when running via CLI to allow self-restarts.
            $this->restart();
        }

        // We can broadcast messages to all users.
        if ($update['message']['message'] === '/broadcast'
            && $from_id === $this->adminId
        ) {
            if (!isset($update['message']['reply_to']['reply_to_msg_id'])) {
                $this->messages->sendMessage(
                    peer: $update,
                    message: "You should reply to the message you want to broadcast.",
                    reply_to_msg_id: $update['message']['id'] ?? null,
                );
                return;
            }
            $this->broadcastForwardMessages(
                from_peer: $update,
                message_ids: [$update['message']['reply_to']['reply_to_msg_id']],
                drop_author: true,
            );
            return;
        }

        if (($update['message']['message'] ?? '') === 'ping') {
            $this->messages->sendMessage(['message' => 'pong', 'peer' => $update]);
        }
    }
}

$settings = new Settings;
$settings->getLogger()->setLevel(Logger::LEVEL_ULTRA_VERBOSE);

// You can also use Redis, MySQL or PostgreSQL
// $settings->setDb((new Redis)->setDatabase(0)->setPassword('pony'));
// $settings->setDb((new Postgres)->setDatabase('MadelineProto')->setUsername('daniil')->setPassword('pony'));
// $settings->setDb((new Mysql)->setDatabase('MadelineProto')->setUsername('daniil')->setPassword('pony'));

// For users or bots
MyEventHandler::startAndLoop('bot.madeline', $settings);

// For bots only
// MyEventHandler::startAndLoopBot('bot.madeline', 'bot token', $settings);
