<?php

declare(strict_types=1);

/**
 * Login QR code.
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

namespace danog\MadelineProto\TL\Types;

use Amp\Cancellation;
use Amp\CancelledException;
use Amp\CompositeCancellation;
use Amp\DeferredFuture;
use Amp\TimeoutCancellation;
use AssertionError;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\PlainTextRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use danog\MadelineProto\Ipc\Client;
use danog\MadelineProto\MTProto;
use JsonSerializable;

/**
 * Represents a login QR code.
 * The `link` public readonly property contains the [QR code login link](https://core.telegram.org/api/links#qr-code-login-links).
 * The `expiry` public readonly property contains the expiry date of the link.
 */
final class LoginQrCode implements JsonSerializable
{
    private string $session;

    /** @internal */
    public function __construct(
        private MTProto|Client $API,
        /** @var non-empty-string The [QR code login link](https://core.telegram.org/api/links#qr-code-login-links) */
        public readonly string $link,
        /** @var positive-int The expiry date of the link */
        public readonly int $expiry
    ) {
        $this->session = $API->getWrapper()->getSession()->getSessionDirectoryPath();
    }

    /**
     * @internal
     */
    public function __sleep(): array
    {
        return ['link', 'expiry', 'session'];
    }

    /** @internal */
    public function jsonSerialize(): mixed
    {
        return [
            'link' => $this->link,
            'expiry' => $this->expiry,
        ];
    }

    /**
     * Returns true if the QR code has expired and a new one should be fetched.
     */
    public function isExpired(): bool
    {
        return $this->expiry <= \time();
    }

    /**
     * Returns the number of seconds until the QR code expires.
     *
     * @return non-negative-int
     */
    public function expiresIn(): int
    {
        return \max(0, $this->expiry - \time());
    }

    public function getExpirationCancellation(): Cancellation
    {
        return new TimeoutCancellation((float) $this->expiresIn(), "The QR code expired!");
    }

    public function getLoginCancellation(): Cancellation
    {
        $this->API ??= Client::giveInstanceBySession($this->session);
        return $this->API->getQrLoginCancellation();
    }

    /**
     * Waits for the user to login or for the QR code to expire.
     *
     * If the user logins, null is returned.
     *
     * If the QR code expires, the new QR code is returned.
     *
     * If cancellation is requested externally through $cancellation, a CancelledException is thrown.
     *
     * @throws CancelledException
     *
     * @param Cancellation|null $customCancellation Optional additional cancellation
     */
    public function waitForLoginOrQrCodeExpiration(?Cancellation $customCancellation = null): ?self
    {
        $expire = $this->getExpirationCancellation();
        if ($customCancellation) {
            $cancellation = new CompositeCancellation($expire, $customCancellation);
        } else {
            $cancellation = $expire;
        }
        $login = $this->getLoginCancellation();
        $cancellation = new CompositeCancellation($login, $cancellation);
        try {
            (new DeferredFuture)->getFuture()->await($cancellation);
        } catch (CancelledException) {
            $customCancellation?->throwIfRequested();
            return $this->API->qrLogin();
        }
        throw new AssertionError("Unreachable!");
    }

    /**
     * Render and return SVG version of QR code.
     */
    public function getQRSvg(int $size = 400, int $margin = 4): string
    {
        $writer = new Writer(new ImageRenderer(
            new RendererStyle($size, $margin),
            new SvgImageBackEnd
        ));
        return $writer->writeString($this->link);
    }

    /**
     * Render and return plain text version of QR code.
     *
     * @param non-negative-int $margin Text margin
     */
    public function getQRText(int $margin = 2): string
    {
        $writer = new Writer(new PlainTextRenderer($margin));
        return $writer->writeString($this->link);
    }
}
