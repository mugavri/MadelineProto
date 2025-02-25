<?php

declare(strict_types=1);

/**
 * Files module.
 *
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

namespace danog\MadelineProto\MTProtoTools;

use Amp\DeferredFuture;
use Amp\File\Driver\BlockingFile;
use Amp\Future;
use Amp\Http\Client\Request;
use danog\MadelineProto\Exception;
use danog\MadelineProto\FileCallbackInterface;
use danog\MadelineProto\Logger;
use danog\MadelineProto\MTProto;
use danog\MadelineProto\MTProtoTools\Crypt\IGE;
use danog\MadelineProto\RPCError\FloodWaitError;
use danog\MadelineProto\RPCErrorException;
use danog\MadelineProto\SecurityException;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Tools;
use Generator;
use Revolt\EventLoop;
use Throwable;

use const LOCK_EX;
use function Amp\async;
use function Amp\File\deleteFile;
use function Amp\File\getSize;
use function Amp\File\openFile;
use function Amp\Future\await;
use function Amp\Future\awaitFirst;

/**
 * Manages upload and download of files.
 *
 * @property Settings $settings Settings
 *
 * @internal
 */
trait Files
{
    use FilesLogic;
    /**
     * Upload file from URL.
     *
     * @param string|FileCallbackInterface $url       URL of file
     * @param integer                      $size      Size of file
     * @param string                       $fileName  File name
     * @param callable                     $cb        Callback (DEPRECATED, use FileCallbackInterface)
     * @param boolean                      $encrypted Whether to encrypt file for secret chats
     */
    public function uploadFromUrl(string|FileCallbackInterface $url, int $size = 0, string $fileName = '', ?callable $cb = null, bool $encrypted = false)
    {
        if (\is_object($url) && $url instanceof FileCallbackInterface) {
            $cb = $url;
            $url = $url->getFile();
        }
        $request = new Request($url);
        $request->setTransferTimeout(10 * 3600);
        $request->setBodySizeLimit(512 * 1024 * 8000);
        $response = $this->datacenter->getHTTPClient()->request($request);
        if (($status = $response->getStatus()) !== 200) {
            throw new Exception("Wrong status code: {$status} ".$response->getReason());
        }
        $mime = \trim(\explode(';', $response->getHeader('content-type') ?? 'application/octet-stream')[0]);
        $size = (int) ($response->getHeader('content-length') ?? $size);
        $stream = $response->getBody();
        if (!$size) {
            $this->logger->logger("No content length for {$url}, caching first");
            $body = $stream;
            $stream = new BlockingFile(\fopen('php://temp', 'r+b'), 'php://temp', 'r+b');
            while (($chunk = $body->read()) !== null) {
                $stream->write($chunk);
            }
            $size = $stream->tell();
            if (!$size) {
                throw new Exception('Wrong size!');
            }
            $stream->seek(0);
        }
        return $this->uploadFromStream($stream, $size, $mime, $fileName, $cb, $encrypted);
    }
    /**
     * Upload file from callable.
     *
     * The callable must accept two parameters: int $offset, int $size
     * The callable must return a string with the contest of the file at the specified offset and size.
     *
     * @param mixed    $callable  Callable
     * @param integer  $size      File size
     * @param string   $mime      Mime type
     * @param string   $fileName  File name
     * @param callable $cb        Callback (DEPRECATED, use FileCallbackInterface)
     * @param boolean  $seekable  Whether chunks can be fetched out of order
     * @param boolean  $encrypted Whether to encrypt file for secret chats
     */
    public function uploadFromCallable(callable $callable, int $size, string $mime, string $fileName = '', ?callable $cb = null, bool $seekable = true, bool $encrypted = false)
    {
        if (\is_object($callable) && $callable instanceof FileCallbackInterface) {
            $cb = $callable;
            $callable = $callable->getFile();
        }
        if (!\is_callable($callable)) {
            throw new Exception('Invalid callable provided');
        }
        if ($cb === null) {
            $cb = function ($percent): void {
                $this->logger->logger('Upload status: '.$percent.'%', Logger::NOTICE);
            };
        } else {
            $cb = function (float $percent, float $speed, float $time) use ($cb): void {
                EventLoop::queue(function () use ($percent, $speed, $time, $cb): void {
                    $r = $cb($percent, $speed, $time);
                    if ($r instanceof Generator) {
                        Tools::consumeGenerator($r);
                    }
                });
            };
        }
        $datacenter = $this->authorized_dc;
        if ($this->datacenter->has(-$datacenter)) {
            $datacenter = -$datacenter;
        }
        $part_size = 512 * 1024;
        $parallel_chunks = $this->settings->getFiles()->getUploadParallelChunks();
        $part_total_num = (int) \ceil($size / $part_size);
        $part_num = 0;
        $method = $size > 10 * 1024 * 1024 ? 'upload.saveBigFilePart' : 'upload.saveFilePart';
        $constructor = 'input'.($encrypted === true ? 'Encrypted' : '').($size > 10 * 1024 * 1024 ? 'FileBig' : 'File').($encrypted === true ? 'Uploaded' : '');
        $file_id = Tools::random(8);
        $ige = null;
        $fingerprint = null;
        $iv = null;
        $key = null;
        if ($encrypted === true) {
            $key = Tools::random(32);
            $iv = Tools::random(32);
            $digest = \hash('md5', $key.$iv, true);
            $fingerprint = Tools::unpackSignedInt(\substr($digest, 0, 4) ^ \substr($digest, 4, 4));
            $ige = IGE::getInstance($key, $iv);
            $seekable = false;
        }
        //$ctx = \hash_init('md5');
        $promises = [];
        $speed = 0;
        $time = 0;
        $cb = function () use ($cb, $part_total_num, &$speed, &$time): void {
            static $cur = 0;
            $cur++;
            $cb($cur * 100 / $part_total_num, $speed, $time);
        };
        $callable = static function (int $part_num) use ($size, $file_id, $part_total_num, $part_size, $callable, $ige) {
            static $offset = 0;
            $bytes = $callable(
                $offset,
                $offset + $part_size > $size ? ($size % $part_size) : $part_size
            );
            $offset += $part_size;
            if ($bytes instanceof Generator) {
                $bytes = Tools::consumeGenerator($bytes);
            }
            if ($bytes instanceof Future) {
                $bytes = $bytes->await();
            }
            if ($ige) {
                $bytes = $ige->encrypt(\str_pad($bytes, $part_size, \chr(0)));
            }
            //\hash_update($ctx, $bytes);
            return ['file_id' => $file_id, 'file_part' => $part_num, 'file_total_parts' => $part_total_num, 'bytes' => $bytes];
        };
        $resPromises = [];
        $start = \microtime(true);
        while ($part_num < $part_total_num) {
            $writePromise = async(
                $this->methodCallAsyncWrite(...),
                $method,
                fn () => $callable($part_num),
                ['heavy' => true, 'file' => true, 'datacenter' => &$datacenter]
            );
            if (!$seekable) {
                $writePromise->await();
            }
            EventLoop::queue(function () use ($writePromise, $cb, $part_num, &$resPromises): void {
                $readFuture = $writePromise->await();
                $d = new DeferredFuture;
                $resPromises[] = $d->getFuture();
                try {
                    // Wrote chunk!
                    if (!$readFuture->await()) {
                        throw new Exception('Upload of part '.$part_num.' failed');
                    }
                    // Got OK from server for chunk!
                    $cb();
                    $d->complete();
                } catch (Throwable $e) {
                    $this->logger("Got exception while uploading: {$e}");
                    $d->error($e);
                    throw $e;
                }
            });
            $promises[] = $writePromise;
            ++$part_num;
            if (\count($promises) === $parallel_chunks) {
                // By default, 10 mb at a time, for a typical bandwidth of 1gbps (run the code in this every second)
                awaitFirst($promises);
                foreach ($promises as $k => $p) {
                    if ($p->isComplete()) {
                        unset($promises[$k]);
                        break;
                    }
                }
            }
            if (!($part_num % $parallel_chunks)) {
                $time = \microtime(true) - $start;
                $speed = (int) ($size * 8 / $time) / 1000000;
                $this->logger->logger("Partial upload time: {$time}");
                $this->logger->logger("Partial upload speed: {$speed} mbps");
            }
        }
        await($promises);
        await($resPromises);
        $time = \microtime(true) - $start;
        $speed = (int) ($size * 8 / $time) / 1000000;
        $this->logger->logger("Total upload time: {$time}");
        $this->logger->logger("Total upload speed: {$speed} mbps");
        $constructor = ['_' => $constructor, 'id' => $file_id, 'parts' => $part_total_num, 'name' => $fileName, 'mime_type' => $mime];
        if ($encrypted === true) {
            $constructor['key_fingerprint'] = $fingerprint;
            $constructor['key'] = $key;
            $constructor['iv'] = $iv;
            $constructor['size'] = $size;
        }
        $constructor['md5_checksum'] = '';
        //\hash_final($ctx);
        return $constructor;
    }
    /**
     * Reupload telegram file.
     *
     * @param mixed    $media     Telegram file
     * @param callable $cb        Callback (DEPRECATED, use FileCallbackInterface)
     * @param boolean  $encrypted Whether to encrypt file for secret chats
     */
    public function uploadFromTgfile(mixed $media, ?callable $cb = null, bool $encrypted = false)
    {
        if (\is_object($media) && $media instanceof FileCallbackInterface) {
            $cb = $media;
            $media = $media->getFile();
        }
        $media = ($this->getDownloadInfo($media));
        if (!isset($media['size'], $media['mime'])) {
            throw new Exception('Wrong file provided!');
        }
        $size = $media['size'];
        $mime = $media['mime'];
        $chunk_size = 512 * 1024;
        $bridge = new class($size, $chunk_size, $cb) {
            /**
             * Read promises.
             *
             * @var array<DeferredFuture>
             */
            private array $read = [];
            /**
             * Read promises (write lenth).
             *
             * @var array<int>
             */
            private array $wrote = [];
            /**
             * Write promises.
             *
             * @var array<DeferredFuture>
             */
            private array $write = [];
            /**
             * Part size.
             *
             */
            private int $partSize;
            /**
             * Offset for callback.
             *
             */
            private int $offset = 0;
            /**
             * Callback.
             *
             * @var ?callable
             */
            private $cb;
            /**
             * Constructor.
             *
             * @param integer  $size     Total file size
             * @param integer  $partSize Part size
             * @param null|callable $cb       Callback
             */
            public function __construct(int $size, int $partSize, ?callable $cb)
            {
                for ($x = 0; $x < $size; $x += $partSize) {
                    $this->read[] = new DeferredFuture();
                    $this->write[] = new DeferredFuture();
                    $this->wrote[] = $size - $x < $partSize ? $size - $x : $partSize;
                }
                $this->partSize = $partSize;
                $this->cb = $cb;
            }
            /**
             * Read chunk.
             *
             * @param integer $offset Offset
             * @param integer $size   Chunk size
             */
            public function read(int $offset, int $size): string
            {
                $offset /= $this->partSize;
                return $this->write[$offset]->getFuture()->await();
            }
            /**
             * Write chunk.
             *
             * @param string  $data   Data
             * @param integer $offset Offset
             */
            public function write(string $data, int $offset): void
            {
                $offset /= $this->partSize;
                $this->write[$offset]->complete($data);
                $this->read[$offset]->getFuture()->await();
            }
            /**
             * Read callback, called when the chunk is read and fully resent.
             *
             * @param mixed ...$params Params to be passed to cb
             */
            public function callback(mixed ...$params): mixed
            {
                $offset = $this->offset++;
                $this->read[$offset]->complete($this->wrote[$offset]);
                if ($this->cb) {
                    return ($this->cb)(...$params);
                }
                return null;
            }
        };
        $reader = $bridge->read(...);
        $writer = $bridge->write(...);
        $cb = $bridge->callback(...);
        $read = async($this->uploadFromCallable(...), $reader, $size, $mime, '', $cb, true, $encrypted);
        $write = async($this->downloadToCallable(...), $media, $writer, null, true, 0, -1, $chunk_size);
        [$res] = await([$read, $write]);
        return $res;
    }

    private function genAllFile($media)
    {
        $res = [$this->TL->getConstructors()->findByPredicate($media['_'])['type'] => $media];
        switch ($media['_']) {
            case 'messageMediaPoll':
                $res['Poll'] = $media['poll'];
                $res['InputMedia'] = ['_' => 'inputMediaPoll', 'poll' => $res['Poll']];
                if (isset($res['Poll']['quiz']) && $res['Poll']['quiz']) {
                    if (empty($media['results']['results'])) {
                        //quizzes need a correct answer
                        throw new Exception('No poll results');
                    }
                    foreach ($media['results']['results'] as $answer) {
                        if ($answer['correct']) {
                            $res['InputMedia']['correct_answers'][] = $answer['option'];
                        }
                    }
                }
                if (isset($media['results']['solution'])) {
                    $res['InputMedia']['solution'] = $media['results']['solution'];
                }
                if (isset($media['results']['solution_entities'])) {
                    $res['InputMedia']['solution_entities'] = $media['results']['solution_entities'];
                }
                break;
            case 'updateMessagePoll':
                $res['Poll'] = $media['poll'];
                $res['InputMedia'] = ['_' => 'inputMediaPoll', 'poll' => $res['Poll']];
                $res['MessageMedia'] = ['_' => 'messageMediaPoll', 'poll' => $res['Poll'], 'results' => $media['results']];
                if (isset($res['Poll']['quiz']) && $res['Poll']['quiz']) {
                    if (empty($media['results']['results'])) {
                        //quizzes need a correct answer
                        throw new Exception('No poll results');
                    }
                    foreach ($media['results']['results'] as $answer) {
                        if ($answer['correct']) {
                            $res['InputMedia']['correct_answers'][] = $answer['option'];
                        }
                    }
                }
                if (isset($media['results']['solution'])) {
                    $res['InputMedia']['solution'] = $media['results']['solution'];
                }
                if (isset($media['results']['solution_entities'])) {
                    $res['InputMedia']['solution_entities'] = $media['results']['solution_entities'];
                }
                break;
            case 'messageMediaPhoto':
                if (!isset($media['photo']['access_hash'])) {
                    throw new Exception('No access hash');
                }
                $res['Photo'] = $media['photo'];
                $res['InputPhoto'] = ['_' => 'inputPhoto', 'id' => $media['photo']['id'], 'access_hash' => $media['photo']['access_hash'], 'file_reference' => $this->referenceDatabase->getReference(ReferenceDatabase::PHOTO_LOCATION, $media['photo'])];
                $res['InputMedia'] = ['_' => 'inputMediaPhoto', 'id' => $res['InputPhoto']];
                if (isset($media['ttl_seconds'])) {
                    $res['InputMedia']['ttl_seconds'] = $media['ttl_seconds'];
                }
                break;
            case 'messageMediaDocument':
                if (!isset($media['document']['access_hash'])) {
                    throw new Exception('No access hash');
                }
                $res['Document'] = $media['document'];
                $res['InputDocument'] = ['_' => 'inputDocument', 'id' => $media['document']['id'], 'access_hash' => $media['document']['access_hash'], 'file_reference' => $this->referenceDatabase->getReference(ReferenceDatabase::DOCUMENT_LOCATION, $media['document'])];
                $res['InputMedia'] = ['_' => 'inputMediaDocument', 'id' => $res['InputDocument']];
                if (isset($media['ttl_seconds'])) {
                    $res['InputMedia']['ttl_seconds'] = $media['ttl_seconds'];
                }
                break;
            case 'messageMediaDice':
                $res['InputMedia'] = ['_' => 'inputMediaDice', 'emoticon' => $media['emoticon']];
                break;
            case 'poll':
                $res['InputMedia'] = ['_' => 'inputMediaPoll', 'poll' => $res['Poll']];
                break;
            case 'document':
                if (!isset($media['access_hash'])) {
                    throw new Exception('No access hash');
                }
                $res['InputDocument'] = ['_' => 'inputDocument', 'id' => $media['id'], 'access_hash' => $media['access_hash'], 'file_reference' => $this->referenceDatabase->getReference(ReferenceDatabase::DOCUMENT_LOCATION, $media)];
                $res['InputMedia'] = ['_' => 'inputMediaDocument', 'id' => $res['InputDocument']];
                $res['MessageMedia'] = ['_' => 'messageMediaDocument', 'document' => $media];
                break;
            case 'photo':
                if (!isset($media['access_hash'])) {
                    throw new Exception('No access hash');
                }
                $res['InputPhoto'] = ['_' => 'inputPhoto', 'id' => $media['id'], 'access_hash' => $media['access_hash'], 'file_reference' => $this->referenceDatabase->getReference(ReferenceDatabase::PHOTO_LOCATION, $media)];
                $res['InputMedia'] = ['_' => 'inputMediaPhoto', 'id' => $res['InputPhoto']];
                $res['MessageMedia'] = ['_' => 'messageMediaPhoto', 'photo' => $media];
                break;
            default:
                throw new Exception("Could not convert media object of type {$media['_']}");
        }
        return $res;
    }
    /**
     * Get info about file.
     *
     * @param mixed $constructor File ID
     */
    public function getFileInfo(mixed $constructor): array
    {
        if (\is_string($constructor)) {
            $constructor = $this->unpackFileId($constructor);
            if (isset($constructor['MessageMedia'])) {
                $constructor = $constructor['MessageMedia'];
            } elseif (isset($constructor['InputMedia'])) {
                return $constructor;
            } elseif (isset($constructor['Chat']) || isset($constructor['User'])) {
                throw new Exception("Chat photo file IDs can't be reused to resend chat photos, please use getPwrChat()['photo'], instead");
            }
        }
        switch ($constructor['_']) {
            case 'updateNewMessage':
            case 'updateNewChannelMessage':
            case 'updateEditMessage':
            case 'updateEditChannelMessage':
                $constructor = $constructor['message'];
                // no break
            case 'message':
                $constructor = $constructor['media'];
        }
        return $this->genAllFile($constructor);
    }
    /**
     * Get download info of the propic of a user
     * Returns an array with the following structure:.
     *
     * `$info['ext']` - The file extension
     * `$info['name']` - The file name, without the extension
     * `$info['mime']` - The file mime type
     * `$info['size']` - The file size
     */
    public function getPropicInfo($data): array
    {
        return $this->getDownloadInfo($this->chats[$this->getInfo($data, MTProto::INFO_TYPE_ID)]);
    }
    /**
     * Extract file info from bot API message.
     *
     * @param array $info Bot API message object
     */
    public static function extractBotAPIFile(array $info): array|null
    {
        foreach (\danog\Decoder\TYPES as $type) {
            if (isset($info[$type]) && \is_array($info[$type])) {
                $method = $type;
                break;
            }
        }
        if (!isset($method)) {
            return null;
        }
        $info = $info[$method];
        if ($method === 'photo') {
            $info = $info[0];
        }
        $info['file_type'] = $method;
        return $info;
    }
    /**
     * Get download info of file
     * Returns an array with the following structure:.
     *
     * `$info['ext']` - The file extension
     * `$info['name']` - The file name, without the extension
     * `$info['mime']` - The file mime type
     * `$info['size']` - The file size
     *
     * @param mixed $messageMedia File ID
     */
    public function getDownloadInfo(mixed $messageMedia): array
    {
        if (\is_string($messageMedia)) {
            $messageMedia = $this->unpackFileId($messageMedia);
            if (isset($messageMedia['InputFileLocation'])) {
                return $messageMedia;
            }
            $messageMedia = $messageMedia['MessageMedia'] ?? $messageMedia['User'] ?? $messageMedia['Chat'];
        }
        if (!isset($messageMedia['_'])) {
            if (!isset($messageMedia['InputFileLocation']) && !isset($messageMedia['file_id'])) {
                $messageMedia = self::extractBotAPIFile($messageMedia) ?? $messageMedia;
            }
            if (isset($messageMedia['file_id'])) {
                $res = $this->getDownloadInfo($messageMedia['file_id']);
                $pathinfo = \pathinfo($messageMedia['file_name']);
                if (isset($pathinfo['extension'])) {
                    $res['ext'] = '.'.$pathinfo['extension'];
                }
                $res['name'] = $pathinfo['filename'];
                return $res;
            }
            return $messageMedia;
        }
        $res = [];
        switch ($messageMedia['_']) {
            // Updates
            case 'updateNewMessage':
            case 'updateNewChannelMessage':
            case 'updateEditMessage':
            case 'updateEditChannelMessage':
                $messageMedia = $messageMedia['message'];
                // no break
            case 'message':
                return $this->getDownloadInfo($messageMedia['media']);
            case 'updateNewEncryptedMessage':
                $messageMedia = $messageMedia['message'];
                // Secret media
                // no break
            case 'encryptedMessage':
                if ($messageMedia['decrypted_message']['media']['_'] === 'decryptedMessageMediaExternalDocument') {
                    return $this->getDownloadInfo($messageMedia['decrypted_message']['media']);
                }
                $res['InputFileLocation'] = ['_' => 'inputEncryptedFileLocation', 'id' => $messageMedia['file']['id'], 'access_hash' => $messageMedia['file']['access_hash'], 'dc_id' => $messageMedia['file']['dc_id']];
                $res['size'] = $messageMedia['decrypted_message']['media']['size'];
                $res['key_fingerprint'] = $messageMedia['file']['key_fingerprint'];
                $res['key'] = $messageMedia['decrypted_message']['media']['key'];
                $res['iv'] = $messageMedia['decrypted_message']['media']['iv'];
                if (isset($messageMedia['decrypted_message']['media']['file_name'])) {
                    $pathinfo = \pathinfo($messageMedia['decrypted_message']['media']['file_name']);
                    if (isset($pathinfo['extension'])) {
                        $res['ext'] = '.'.$pathinfo['extension'];
                    }
                    $res['name'] = $pathinfo['filename'];
                }
                if (isset($messageMedia['decrypted_message']['media']['mime_type'])) {
                    $res['mime'] = $messageMedia['decrypted_message']['media']['mime_type'];
                } elseif ($messageMedia['decrypted_message']['media']['_'] === 'decryptedMessageMediaPhoto') {
                    $res['mime'] = 'image/jpeg';
                }
                if (isset($messageMedia['decrypted_message']['media']['attributes'])) {
                    foreach ($messageMedia['decrypted_message']['media']['attributes'] as $attribute) {
                        switch ($attribute['_']) {
                            case 'documentAttributeFilename':
                                $pathinfo = \pathinfo($attribute['file_name']);
                                if (isset($pathinfo['extension'])) {
                                    $res['ext'] = '.'.$pathinfo['extension'];
                                }
                                $res['name'] = $pathinfo['filename'];
                                break;
                            case 'documentAttributeAudio':
                                $audio = $attribute;
                                break;
                        }
                    }
                }
                if (isset($audio) && isset($audio['title']) && !isset($res['name'])) {
                    $res['name'] = $audio['title'];
                    if (isset($audio['performer'])) {
                        $res['name'] .= ' - '.$audio['performer'];
                    }
                }
                if (!isset($res['ext']) || $res['ext'] === '') {
                    $res['ext'] = Tools::getExtensionFromLocation($res['InputFileLocation'], Tools::getExtensionFromMime($res['mime'] ?? 'image/jpeg'));
                }
                if (!isset($res['mime']) || $res['mime'] === '') {
                    $res['mime'] = Tools::getMimeFromExtension($res['ext'], 'image/jpeg');
                }
                if (!isset($res['name']) || $res['name'] === '') {
                    $res['name'] = Tools::unpackSignedLongString($messageMedia['file']['access_hash']);
                }
                return $res;
                // Wallpapers
            case 'wallPaper':
                return $this->getDownloadInfo($messageMedia['document']);
                // Photos
            case 'photo':
            case 'messageMediaPhoto':
                if ($messageMedia['_'] == 'photo') {
                    $messageMedia = ['_' => 'messageMediaPhoto', 'photo' => $messageMedia, 'ttl_seconds' => 0];
                }
                $res['MessageMedia'] = $messageMedia;
                $messageMedia = $messageMedia['photo'];
                $size = Tools::maxSize($messageMedia['sizes']);
                if (isset($size['volume_id'])) {
                    $res['InputFileLocation'] = [
                        '_' => 'inputPhotoLegacyFileLocation',
                        'id' => $messageMedia['id'],
                        'access_hash' => $messageMedia['access_hash'],
                        'dc_id' => $messageMedia['dc_id'],
                        'file_reference' => $this->referenceDatabase->getReference(ReferenceDatabase::PHOTO_LOCATION, $messageMedia),
                        'volume_id' => $size['volume_id'],
                        'local_id' => $size['local_id'],
                        'secret' => $size['secret'],
                    ];
                } else {
                    $res = \array_merge($res, $this->getDownloadInfo($size));
                    $res['InputFileLocation'] = [
                        '_' => 'inputPhotoFileLocation',
                        'id' => $messageMedia['id'],
                        'access_hash' => $messageMedia['access_hash'],
                        'dc_id' => $messageMedia['dc_id'],
                        'file_reference' => $this->referenceDatabase->getReference(ReferenceDatabase::PHOTO_LOCATION, $messageMedia),
                        'thumb_size' => $res['thumb_size'] ?? 'x',
                    ];
                }
                $res['name'] = Tools::unpackSignedLongString($messageMedia['id']).'_'.($res['thumb_size'] ?? 'x').'_'.$messageMedia['dc_id'];
                $res['ext'] = '.jpg';
                $res['mime'] = 'image/jpeg';
                return $res;
            case 'user':
            case 'folder':
            case 'channel':
            case 'chat':
            case 'updateUserPhoto':
                $res = ($this->getDownloadInfo($messageMedia['photo']));
                if (\is_array($messageMedia) && ($messageMedia['min'] ?? false) && isset($messageMedia['access_hash'])) {
                    // bot API file ID
                    $messageMedia['min'] = false;
                    $peer = $this->genAll($messageMedia, null, MTProto::INFO_TYPE_PEER);
                } else {
                    $peer = $this->getInfo($messageMedia, MTProto::INFO_TYPE_PEER);
                }
                $res['InputFileLocation'] = [
                    '_' => 'inputPeerPhotoFileLocation',
                    'big' => true,
                    'peer' => $peer,
                    'photo_id' => $res['InputFileLocation']['photo_id'],
                ];
                $res['name'] = Tools::unpackSignedLongString($messageMedia['id']).'_'.($res['thumb_size'] ?? 'x').'_'.$messageMedia['photo']['dc_id'];
                $res['ext'] = '.jpg';
                $res['mime'] = 'image/jpeg';
                return $res;
            case 'userProfilePhoto':
            case 'chatPhoto':
                $res['InputFileLocation']['has_video'] = $messageMedia['has_video'] ?? false;
                $res['InputFileLocation']['photo_id'] = $messageMedia['photo_id'];
                $res['InputFileLocation']['dc_id'] = $messageMedia['dc_id'];
                return $res;
            case 'photoStrippedSize':
                $res['size'] = \strlen($messageMedia['bytes']['bytes'] ?? $messageMedia['bytes']);
                $res['data'] = $messageMedia['bytes'];
                $res['thumb_size'] = 'JPG';
                return $res;
            case 'photoCachedSize':
                $res['size'] = \strlen($messageMedia['bytes']);
                $res['data'] = $messageMedia['bytes'];
                $res['thumb_size'] = $messageMedia['type'];
                return $res;
            case 'photoSize':
                $res['thumb_size'] = $messageMedia['type'];
                //$res['thumb_size'] = $size;
                if (isset($messageMedia['size'])) {
                    $res['size'] = $messageMedia['size'];
                }
                return $res;
            case 'photoSizeProgressive':
                $res['thumb_size'] = $messageMedia['type'];
                if (isset($messageMedia['sizes'])) {
                    $res['size'] = \end($messageMedia['sizes']);
                }
                return $res;
                // Documents
            case 'decryptedMessageMediaExternalDocument':
            case 'document':
                $messageMedia = ['_' => 'messageMediaDocument', 'ttl_seconds' => 0, 'document' => $messageMedia];
                // no break
            case 'messageMediaDocument':
                $res['MessageMedia'] = $messageMedia;
                foreach ($messageMedia['document']['attributes'] as $attribute) {
                    switch ($attribute['_']) {
                        case 'documentAttributeFilename':
                            $pathinfo = \pathinfo($attribute['file_name']);
                            if (isset($pathinfo['extension'])) {
                                $res['ext'] = '.'.$pathinfo['extension'];
                            }
                            $res['name'] = $pathinfo['filename'];
                            break;
                        case 'documentAttributeAudio':
                            $audio = $attribute;
                            break;
                    }
                }
                if (isset($audio) && isset($audio['title']) && !isset($res['name'])) {
                    $res['name'] = $audio['title'];
                    if (isset($audio['performer'])) {
                        $res['name'] .= ' - '.$audio['performer'];
                    }
                }
                $res['InputFileLocation'] = ['_' => 'inputDocumentFileLocation', 'id' => $messageMedia['document']['id'], 'access_hash' => $messageMedia['document']['access_hash'], 'version' => $messageMedia['document']['version'] ?? 0, 'dc_id' => $messageMedia['document']['dc_id'], 'file_reference' => $this->referenceDatabase->getReference(ReferenceDatabase::DOCUMENT_LOCATION, $messageMedia['document'])];
                if (!isset($res['ext']) || $res['ext'] === '') {
                    $res['ext'] = Tools::getExtensionFromLocation($res['InputFileLocation'], Tools::getExtensionFromMime($messageMedia['document']['mime_type']));
                }
                if (!isset($res['name']) || $res['name'] === '') {
                    $res['name'] = Tools::unpackSignedLongString($messageMedia['document']['access_hash']);
                }
                if (isset($messageMedia['document']['size'])) {
                    $res['size'] = $messageMedia['document']['size'];
                }
                $res['name'] .= '_'.Tools::unpackSignedLongString($messageMedia['document']['id']);
                $res['mime'] = $messageMedia['document']['mime_type'];
                return $res;
            default:
                throw new Exception('Invalid constructor provided: '.$messageMedia['_']);
        }
    }
    /**
     * Download file to directory.
     *
     * @param mixed                        $messageMedia File to download
     * @param string|FileCallbackInterface $dir           Directory where to download the file
     * @param callable                     $cb            Callback (DEPRECATED, use FileCallbackInterface)
     */
    public function downloadToDir(mixed $messageMedia, string|FileCallbackInterface $dir, ?callable $cb = null)
    {
        if (\is_object($dir) && $dir instanceof FileCallbackInterface) {
            $cb = $dir;
            $dir = $dir->getFile();
        }
        $messageMedia = ($this->getDownloadInfo($messageMedia));
        return $this->downloadToFile($messageMedia, $dir.'/'.$messageMedia['name'].$messageMedia['ext'], $cb);
    }
    /**
     * Download file.
     *
     * @param mixed                        $messageMedia File to download
     * @param string|FileCallbackInterface $file          Downloaded file path
     * @param callable                     $cb            Callback (DEPRECATED, use FileCallbackInterface)
     */
    public function downloadToFile(mixed $messageMedia, string|FileCallbackInterface $file, ?callable $cb = null): false|string
    {
        if (\is_object($file) && $file instanceof FileCallbackInterface) {
            $cb = $file;
            $file = $file->getFile();
        }
        $file = Tools::absolute(\preg_replace('|/+|', '/', $file));
        if (!\file_exists($file)) {
            \touch($file);
        }
        $file = \realpath($file);
        $messageMedia = ($this->getDownloadInfo($messageMedia));
        $size = getSize($file);
        $stream = openFile($file, 'cb');
        $this->logger->logger('Waiting for lock of file to download...');
        $unlock = Tools::flock("$file.lock", LOCK_EX);
        $this->logger->logger('Got lock of file to download');
        try {
            $this->downloadToStream($messageMedia, $stream, $cb, $size, -1);
        } finally {
            $unlock();
            deleteFile("$file.lock");
            $stream->close();
        }
        return $file;
    }
    /**
     * Download file to callable.
     * The callable must accept two parameters: string $payload, int $offset
     * The callable will be called (possibly out of order, depending on the value of $seekable).
     *
     * @param mixed                          $messageMedia  File to download
     * @param callable|FileCallbackInterface $callable      Chunk callback
     * @param callable                       $cb            Status callback (DEPRECATED, use FileCallbackInterface)
     * @param bool                           $seekable      Whether the callable can be called out of order
     * @param int                            $offset        Offset where to start downloading
     * @param int                            $end           Offset where to stop downloading (inclusive)
     * @param int                            $part_size     Size of each chunk
     */
    public function downloadToCallable(mixed $messageMedia, callable $callable, ?callable $cb = null, bool $seekable = true, int $offset = 0, int $end = -1, ?int $part_size = null)
    {
        $messageMedia = ($this->getDownloadInfo($messageMedia));
        if (\is_object($callable) && $callable instanceof FileCallbackInterface) {
            $cb = $callable;
            $callable = $callable->getFile();
        }
        if (!\is_callable($callable)) {
            throw new Exception('Wrong callable provided');
        }
        if ($cb === null) {
            $cb = function ($percent): void {
                $this->logger->logger('Download status: '.$percent.'%', Logger::NOTICE);
            };
        } else {
            $cb = function (float $percent, float $speed, float $time) use ($cb): void {
                EventLoop::queue(function () use ($percent, $speed, $time, $cb): void {
                    $r = $cb($percent, $speed, $time);
                    if ($r instanceof Generator) {
                        Tools::consumeGenerator($r);
                    }
                });
            };
        }
        if ($end === -1 && isset($messageMedia['size'])) {
            $end = $messageMedia['size'];
        }
        $part_size ??= 1024 * 1024;
        $parallel_chunks = $this->settings->getFiles()->getDownloadParallelChunks();
        $datacenter = $messageMedia['InputFileLocation']['dc_id'] ?? $this->authorized_dc;
        if ($this->datacenter->has(-$datacenter)) {
            $datacenter = -$datacenter;
        }
        if (isset($messageMedia['key'])) {
            $digest = \hash('md5', $messageMedia['key'].$messageMedia['iv'], true);
            $fingerprint = Tools::unpackSignedInt(\substr($digest, 0, 4) ^ \substr($digest, 4, 4));
            if ($fingerprint !== $messageMedia['key_fingerprint']) {
                throw new Exception('Fingerprint mismatch!');
            }
            $ige = IGE::getInstance($messageMedia['key'], $messageMedia['iv']);
            $seekable = false;
        }
        if ($offset === $end) {
            $cb(100, 0, 0);
            return true;
        }
        $params = [];
        $start_at = $offset % $part_size;
        $probable_end = $end !== -1 ? $end : 512 * 1024 * 8000;
        $breakOut = false;
        for ($x = $offset - $start_at; $x < $probable_end; $x += $part_size) {
            $end_at = $part_size;
            if ($end !== -1 && $x + $part_size > $end) {
                $end_at = $end % $part_size;
                $breakOut = true;
            }
            $params[] = ['offset' => $x, 'limit' => $part_size, 'part_start_at' => $start_at, 'part_end_at' => $end_at];
            $start_at = 0;
            if ($breakOut) {
                break;
            }
        }
        if (!$params) {
            $cb(100, 0, 0);
            return true;
        }
        $count = \count($params);
        $time = 0;
        $speed = 0;
        $origCb = $cb;
        $cb = static function () use ($cb, $count, &$time, &$speed): void {
            static $cur = 0;
            $cur++;
            $cb($cur * 100 / $count, $time, $speed);
        };
        $cdn = false;
        $params[0]['previous_promise'] = true;
        $start = \microtime(true);
        $old_dc = null;
        $size = $this->downloadPart($messageMedia, $cdn, $datacenter, $old_dc, $ige, $cb, $initParam = \array_shift($params), $callable, $seekable);
        if ($initParam['part_end_at'] - $initParam['part_start_at'] !== $size) {
            // Premature end for undefined length files
            $origCb(100, 0, 0);
            return true;
        }
        $parallel_chunks = $seekable ? $parallel_chunks : 1;
        if ($params) {
            $previous_promise = true;
            $promises = [];
            foreach ($params as $key => $param) {
                $param['previous_promise'] = $previous_promise;
                $previous_promise = async($this->downloadPart(...), $messageMedia, $cdn, $datacenter, $old_dc, $ige, $cb, $param, $callable, $seekable);
                $previous_promise->map(static function (int $res) use (&$size): void {
                    $size += $res;
                });
                $promises[] = $previous_promise;
                if (\count($promises) === $parallel_chunks) {
                    // 20 mb at a time, for a typical bandwidth of 1gbps
                    awaitFirst($promises);
                    foreach ($promises as $k => $p) {
                        if ($p->isComplete()) {
                            unset($promises[$k]);
                            break;
                        }
                    }
                }
                if (!($key % $parallel_chunks)) {
                    $time = \microtime(true) - $start;
                    $speed = (int) ($size * 8 / $time) / 1000000;
                    $this->logger->logger("Partial download time: {$time}");
                    $this->logger->logger("Partial download speed: {$speed} mbps");
                }
            }
            if ($promises) {
                await($promises);
            }
        }
        $time = \microtime(true) - $start;
        $speed = (int) ($size * 8 / $time) / 1000000;
        $this->logger->logger("Total download time: {$time}");
        $this->logger->logger("Total download speed: {$speed} mbps");
        if ($cdn) {
            $this->clearCdnHashes($messageMedia['file_token']);
        }
        if (!isset($messageMedia['size'])) {
            $origCb(100, $time, $speed);
        }
        return true;
    }
    /**
     * Download file part.
     *
     * @param array    $messageMedia  File object
     * @param bool     $cdn           Whether this is a CDN file
     * @param int      $datacenter    DC ID
     * @param null|int     $old_dc       Previous DC ID
     * @param IGE      $ige           IGE decryptor instance
     * @param callable $cb            Status callback
     * @param array    $offset        Offset
     * @param callable $callable      Chunk callback
     * @param boolean  $seekable      Whether the download file is seekable
     * @param boolean  $postpone      Whether to postpone method call
     */
    private function downloadPart(array &$messageMedia, bool &$cdn, int &$datacenter, ?int &$old_dc, ?IGE &$ige, callable $cb, array $offset, callable $callable, bool $seekable, bool $postpone = false): int
    {
        do {
            if (!$cdn) {
                $basic_param = ['location' => $messageMedia['InputFileLocation']];
            } else {
                $basic_param = ['file_token' => $messageMedia['file_token']];
            }
            //$x = 0;
            while (true) {
                try {
                    $res = $this->methodCallAsyncRead(
                        $cdn ? 'upload.getCdnFile' : 'upload.getFile',
                        $basic_param + $offset,
                        ['heavy' => true, 'file' => true, 'FloodWaitLimit' => 0, 'datacenter' => &$datacenter, 'postpone' => $postpone]
                    );
                    break;
                } catch (FloodWaitError $e) {
                    Tools::sleep(1);
                    continue;
                } catch (RPCErrorException $e) {
                    switch ($e->rpc) {
                        case 'FILE_TOKEN_INVALID':
                            $cdn = false;
                            continue 3;
                        default:
                            throw $e;
                    }
                }
            }

            if ($res['_'] === 'upload.fileCdnRedirect') {
                $cdn = true;
                $messageMedia['file_token'] = $res['file_token'];
                $messageMedia['cdn_key'] = $res['encryption_key'];
                $messageMedia['cdn_iv'] = $res['encryption_iv'];
                $old_dc = $datacenter;
                $datacenter = $res['dc_id'];
                if (!$this->datacenter->has($datacenter)) {
                    $this->config['expires'] = -1;
                    $this->getConfig();
                }
                $this->logger->logger('File is stored on CDN!', Logger::NOTICE);
                continue;
            } elseif ($res['_'] === 'upload.cdnFileReuploadNeeded') {
                $this->logger->logger('File is not stored on CDN, requesting reupload!', Logger::NOTICE);
                $this->config['expires'] = 0;
                $this->getConfig();
                try {
                    $this->addCdnHashes($messageMedia['file_token'], $this->methodCallAsyncRead('upload.reuploadCdnFile', ['file_token' => $messageMedia['file_token'], 'request_token' => $res['request_token']], ['heavy' => true, 'datacenter' => $old_dc]));
                } catch (RPCErrorException $e) {
                    switch ($e->rpc) {
                        case 'FILE_TOKEN_INVALID':
                        case 'REQUEST_TOKEN_INVALID':
                            $cdn = false;
                            continue 2;
                        default:
                            throw $e;
                    }
                }
                continue;
            }
            $res['bytes'] = (string) $res['bytes'];
            if ($cdn === false && $res['type']['_'] === 'storage.fileUnknown' && $res['bytes'] === '') {
                $datacenter = 0;
            }
            while ($cdn === false && $res['type']['_'] === 'storage.fileUnknown' && $res['bytes'] === '' && $this->datacenter->has(++$datacenter)) {
                $res = $this->methodCallAsyncRead('upload.getFile', $basic_param + $offset, ['heavy' => true, 'file' => true, 'FloodWaitLimit' => 0, 'datacenter' => $datacenter]);
            }
            if ($res['bytes'] === '') {
                return 0;
            }
            if (isset($messageMedia['cdn_key'])) {
                $ivec = \substr($messageMedia['cdn_iv'], 0, 12).\pack('N', $offset['offset'] >> 4);
                $res['bytes'] = Crypt::ctrEncrypt($res['bytes'], $messageMedia['cdn_key'], $ivec);
                $this->checkCdnHash($messageMedia['file_token'], $offset['offset'], $res['bytes'], $old_dc);
            }
            if (isset($messageMedia['key'])) {
                $res['bytes'] = $ige->decrypt($res['bytes']);
            }
            if ($offset['part_start_at'] || $offset['part_end_at'] !== $offset['limit']) {
                $res['bytes'] = \substr($res['bytes'], $offset['part_start_at'], $offset['part_end_at'] - $offset['part_start_at']);
            }
            if (!$seekable) {
                $offset['previous_promise'];
            }
            $len = \strlen($res['bytes']);
            $res = $callable($res['bytes'], $offset['offset'] + $offset['part_start_at']);
            if ($res instanceof Generator) {
                $res = Tools::consumeGenerator($res);
            }
            if ($res instanceof Future) {
                $res = $res->await();
            }
            $cb();
            return $len;
        } while (true);
    }
    /**
     * @var array<string, array<int, array{limit: int, hash: string}>>
     */
    private array $cdn_hashes = [];
    private function addCdnHashes(string $file, array $hashes): void
    {
        foreach ($hashes as $hash) {
            $this->cdn_hashes[$file] ??= [];
            $this->cdn_hashes[$file][$hash['offset']] = ['limit' => $hash['limit'], 'hash' => (string) $hash['hash']];
        }
    }
    private function checkCdnHash(string $file, int $offset, string $data, int &$datacenter): void
    {
        while (\strlen($data)) {
            if (!isset($this->cdn_hashes[$file][$offset])) {
                $this->addCdnHashes($file, $this->methodCallAsyncRead('upload.getCdnFileHashes', ['file_token' => $file, 'offset' => $offset], ['datacenter' => $datacenter]));
            }
            if (!isset($this->cdn_hashes[$file][$offset])) {
                throw new Exception('Could not fetch CDN hashes for offset '.$offset);
            }
            if (\hash('sha256', \substr($data, 0, $this->cdn_hashes[$file][$offset]['limit']), true) !== $this->cdn_hashes[$file][$offset]['hash']) {
                throw new SecurityException('CDN hash mismatch for offset '.$offset);
            }
            $data = \substr($data, $this->cdn_hashes[$file][$offset]['limit']);
            $offset += $this->cdn_hashes[$file][$offset]['limit'];
        }
    }

    private function clearCdnHashes(string $file): void
    {
        unset($this->cdn_hashes[$file]);
    }
}
