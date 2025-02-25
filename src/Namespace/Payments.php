<?php declare(strict_types=1);
/**
 * This file is automatic generated by build_docs.php file
 * and is used only for autocomplete in multiple IDE
 * don't modify manually.
 */

namespace danog\MadelineProto\Namespace;

interface Payments
{
    /**
     * Get a payment form.
     *
     * @param array{_: 'inputInvoiceMessage', peer: array|int|string, msg_id?: int}|array{_: 'inputInvoiceSlug', slug?: string} $invoice Invoice @see https://docs.madelineproto.xyz/API_docs/types/InputInvoice.html
     * @param mixed $theme_params Any JSON-encodable data
     * @return array{_: 'payments.paymentForm', invoice: array{_: 'invoice', test: bool, name_requested: bool, phone_requested: bool, email_requested: bool, shipping_address_requested: bool, flexible: bool, phone_to_provider: bool, email_to_provider: bool, recurring: bool, currency: string, prices: list<array{_: 'labeledPrice', label: string, amount: int}>, max_tip_amount: int, suggested_tip_amounts: list<int>, recurring_terms_url: string}, can_save_credentials: bool, password_missing: bool, form_id: int, bot_id: int, title: string, description: string, photo?: array{_: 'webDocument', url: string, access_hash: int, size: int, mime_type: string, attributes: list<array{_: 'documentAttributeImageSize', w: int, h: int}|array{_: 'documentAttributeAnimated'}|array{_: 'documentAttributeSticker', stickerset: array{_: 'inputStickerSetEmpty'}|array{_: 'inputStickerSetID', id: int, access_hash: int}|array{_: 'inputStickerSetShortName', short_name: string}|array{_: 'inputStickerSetAnimatedEmoji'}|array{_: 'inputStickerSetDice', emoticon: string}|array{_: 'inputStickerSetAnimatedEmojiAnimations'}|array{_: 'inputStickerSetPremiumGifts'}|array{_: 'inputStickerSetEmojiGenericAnimations'}|array{_: 'inputStickerSetEmojiDefaultStatuses'}|array{_: 'inputStickerSetEmojiDefaultTopicIcons'}, mask: bool, alt: string, mask_coords?: array{_: 'maskCoords', x: float, y: float, zoom: float, n: int}}|array{_: 'documentAttributeVideo', round_message: bool, supports_streaming: bool, duration: int, w: int, h: int}|array{_: 'documentAttributeAudio', voice: bool, duration: int, title: string, performer: string, waveform: string}|array{_: 'documentAttributeFilename', file_name: string}|array{_: 'documentAttributeHasStickers'}|array{_: 'documentAttributeCustomEmoji', stickerset: array{_: 'inputStickerSetEmpty'}|array{_: 'inputStickerSetID', id: int, access_hash: int}|array{_: 'inputStickerSetShortName', short_name: string}|array{_: 'inputStickerSetAnimatedEmoji'}|array{_: 'inputStickerSetDice', emoticon: string}|array{_: 'inputStickerSetAnimatedEmojiAnimations'}|array{_: 'inputStickerSetPremiumGifts'}|array{_: 'inputStickerSetEmojiGenericAnimations'}|array{_: 'inputStickerSetEmojiDefaultStatuses'}|array{_: 'inputStickerSetEmojiDefaultTopicIcons'}, free: bool, text_color: bool, alt: string}|array{_: 'documentAttributeSticker'}|array{_: 'documentAttributeVideo', duration: int, w: int, h: int}|array{_: 'documentAttributeAudio', duration: int}|array{_: 'documentAttributeSticker', stickerset: array{_: 'inputStickerSetEmpty'}|array{_: 'inputStickerSetID', id: int, access_hash: int}|array{_: 'inputStickerSetShortName', short_name: string}|array{_: 'inputStickerSetAnimatedEmoji'}|array{_: 'inputStickerSetDice', emoticon: string}|array{_: 'inputStickerSetAnimatedEmojiAnimations'}|array{_: 'inputStickerSetPremiumGifts'}|array{_: 'inputStickerSetEmojiGenericAnimations'}|array{_: 'inputStickerSetEmojiDefaultStatuses'}|array{_: 'inputStickerSetEmojiDefaultTopicIcons'}, alt: string}|array{_: 'documentAttributeAudio', duration: int, title: string, performer: string}>}|array{_: 'webDocumentNoProxy', url: string, size: int, mime_type: string, attributes: list<array{_: 'documentAttributeImageSize', w: int, h: int}|array{_: 'documentAttributeAnimated'}|array{_: 'documentAttributeSticker', stickerset: array{_: 'inputStickerSetEmpty'}|array{_: 'inputStickerSetID', id: int, access_hash: int}|array{_: 'inputStickerSetShortName', short_name: string}|array{_: 'inputStickerSetAnimatedEmoji'}|array{_: 'inputStickerSetDice', emoticon: string}|array{_: 'inputStickerSetAnimatedEmojiAnimations'}|array{_: 'inputStickerSetPremiumGifts'}|array{_: 'inputStickerSetEmojiGenericAnimations'}|array{_: 'inputStickerSetEmojiDefaultStatuses'}|array{_: 'inputStickerSetEmojiDefaultTopicIcons'}, mask: bool, alt: string, mask_coords?: array{_: 'maskCoords', x: float, y: float, zoom: float, n: int}}|array{_: 'documentAttributeVideo', round_message: bool, supports_streaming: bool, duration: int, w: int, h: int}|array{_: 'documentAttributeAudio', voice: bool, duration: int, title: string, performer: string, waveform: string}|array{_: 'documentAttributeFilename', file_name: string}|array{_: 'documentAttributeHasStickers'}|array{_: 'documentAttributeCustomEmoji', stickerset: array{_: 'inputStickerSetEmpty'}|array{_: 'inputStickerSetID', id: int, access_hash: int}|array{_: 'inputStickerSetShortName', short_name: string}|array{_: 'inputStickerSetAnimatedEmoji'}|array{_: 'inputStickerSetDice', emoticon: string}|array{_: 'inputStickerSetAnimatedEmojiAnimations'}|array{_: 'inputStickerSetPremiumGifts'}|array{_: 'inputStickerSetEmojiGenericAnimations'}|array{_: 'inputStickerSetEmojiDefaultStatuses'}|array{_: 'inputStickerSetEmojiDefaultTopicIcons'}, free: bool, text_color: bool, alt: string}|array{_: 'documentAttributeSticker'}|array{_: 'documentAttributeVideo', duration: int, w: int, h: int}|array{_: 'documentAttributeAudio', duration: int}|array{_: 'documentAttributeSticker', stickerset: array{_: 'inputStickerSetEmpty'}|array{_: 'inputStickerSetID', id: int, access_hash: int}|array{_: 'inputStickerSetShortName', short_name: string}|array{_: 'inputStickerSetAnimatedEmoji'}|array{_: 'inputStickerSetDice', emoticon: string}|array{_: 'inputStickerSetAnimatedEmojiAnimations'}|array{_: 'inputStickerSetPremiumGifts'}|array{_: 'inputStickerSetEmojiGenericAnimations'}|array{_: 'inputStickerSetEmojiDefaultStatuses'}|array{_: 'inputStickerSetEmojiDefaultTopicIcons'}, alt: string}|array{_: 'documentAttributeAudio', duration: int, title: string, performer: string}>}, provider_id: int, url: string, native_provider: string, native_params?: mixed, additional_methods: list<array{_: 'paymentFormMethod', url: string, title: string}>, saved_info?: array{_: 'paymentRequestedInfo', name: string, phone: string, email: string, shipping_address?: array{_: 'postAddress', street_line1: string, street_line2: string, city: string, state: string, country_iso2: string, post_code: string}}, saved_credentials: list<array{_: 'paymentSavedCredentialsCard', id: string, title: string}>, users: list<array|int|string>} @see https://docs.madelineproto.xyz/API_docs/types/payments.PaymentForm.html
     */
    public function getPaymentForm(array $invoice, mixed $theme_params = null): array;

    /**
     * Get payment receipt.
     *
     * @param array|int|string $peer The peer where the payment receipt was sent @see https://docs.madelineproto.xyz/API_docs/types/InputPeer.html
     * @param int $msg_id Message ID of receipt
     * @return array{_: 'payments.paymentReceipt', invoice: array{_: 'invoice', test: bool, name_requested: bool, phone_requested: bool, email_requested: bool, shipping_address_requested: bool, flexible: bool, phone_to_provider: bool, email_to_provider: bool, recurring: bool, currency: string, prices: list<array{_: 'labeledPrice', label: string, amount: int}>, max_tip_amount: int, suggested_tip_amounts: list<int>, recurring_terms_url: string}, date: int, bot_id: int, provider_id: int, title: string, description: string, photo?: array{_: 'webDocument', url: string, access_hash: int, size: int, mime_type: string, attributes: list<array{_: 'documentAttributeImageSize', w: int, h: int}|array{_: 'documentAttributeAnimated'}|array{_: 'documentAttributeSticker', stickerset: array{_: 'inputStickerSetEmpty'}|array{_: 'inputStickerSetID', id: int, access_hash: int}|array{_: 'inputStickerSetShortName', short_name: string}|array{_: 'inputStickerSetAnimatedEmoji'}|array{_: 'inputStickerSetDice', emoticon: string}|array{_: 'inputStickerSetAnimatedEmojiAnimations'}|array{_: 'inputStickerSetPremiumGifts'}|array{_: 'inputStickerSetEmojiGenericAnimations'}|array{_: 'inputStickerSetEmojiDefaultStatuses'}|array{_: 'inputStickerSetEmojiDefaultTopicIcons'}, mask: bool, alt: string, mask_coords?: array{_: 'maskCoords', x: float, y: float, zoom: float, n: int}}|array{_: 'documentAttributeVideo', round_message: bool, supports_streaming: bool, duration: int, w: int, h: int}|array{_: 'documentAttributeAudio', voice: bool, duration: int, title: string, performer: string, waveform: string}|array{_: 'documentAttributeFilename', file_name: string}|array{_: 'documentAttributeHasStickers'}|array{_: 'documentAttributeCustomEmoji', stickerset: array{_: 'inputStickerSetEmpty'}|array{_: 'inputStickerSetID', id: int, access_hash: int}|array{_: 'inputStickerSetShortName', short_name: string}|array{_: 'inputStickerSetAnimatedEmoji'}|array{_: 'inputStickerSetDice', emoticon: string}|array{_: 'inputStickerSetAnimatedEmojiAnimations'}|array{_: 'inputStickerSetPremiumGifts'}|array{_: 'inputStickerSetEmojiGenericAnimations'}|array{_: 'inputStickerSetEmojiDefaultStatuses'}|array{_: 'inputStickerSetEmojiDefaultTopicIcons'}, free: bool, text_color: bool, alt: string}|array{_: 'documentAttributeSticker'}|array{_: 'documentAttributeVideo', duration: int, w: int, h: int}|array{_: 'documentAttributeAudio', duration: int}|array{_: 'documentAttributeSticker', stickerset: array{_: 'inputStickerSetEmpty'}|array{_: 'inputStickerSetID', id: int, access_hash: int}|array{_: 'inputStickerSetShortName', short_name: string}|array{_: 'inputStickerSetAnimatedEmoji'}|array{_: 'inputStickerSetDice', emoticon: string}|array{_: 'inputStickerSetAnimatedEmojiAnimations'}|array{_: 'inputStickerSetPremiumGifts'}|array{_: 'inputStickerSetEmojiGenericAnimations'}|array{_: 'inputStickerSetEmojiDefaultStatuses'}|array{_: 'inputStickerSetEmojiDefaultTopicIcons'}, alt: string}|array{_: 'documentAttributeAudio', duration: int, title: string, performer: string}>}|array{_: 'webDocumentNoProxy', url: string, size: int, mime_type: string, attributes: list<array{_: 'documentAttributeImageSize', w: int, h: int}|array{_: 'documentAttributeAnimated'}|array{_: 'documentAttributeSticker', stickerset: array{_: 'inputStickerSetEmpty'}|array{_: 'inputStickerSetID', id: int, access_hash: int}|array{_: 'inputStickerSetShortName', short_name: string}|array{_: 'inputStickerSetAnimatedEmoji'}|array{_: 'inputStickerSetDice', emoticon: string}|array{_: 'inputStickerSetAnimatedEmojiAnimations'}|array{_: 'inputStickerSetPremiumGifts'}|array{_: 'inputStickerSetEmojiGenericAnimations'}|array{_: 'inputStickerSetEmojiDefaultStatuses'}|array{_: 'inputStickerSetEmojiDefaultTopicIcons'}, mask: bool, alt: string, mask_coords?: array{_: 'maskCoords', x: float, y: float, zoom: float, n: int}}|array{_: 'documentAttributeVideo', round_message: bool, supports_streaming: bool, duration: int, w: int, h: int}|array{_: 'documentAttributeAudio', voice: bool, duration: int, title: string, performer: string, waveform: string}|array{_: 'documentAttributeFilename', file_name: string}|array{_: 'documentAttributeHasStickers'}|array{_: 'documentAttributeCustomEmoji', stickerset: array{_: 'inputStickerSetEmpty'}|array{_: 'inputStickerSetID', id: int, access_hash: int}|array{_: 'inputStickerSetShortName', short_name: string}|array{_: 'inputStickerSetAnimatedEmoji'}|array{_: 'inputStickerSetDice', emoticon: string}|array{_: 'inputStickerSetAnimatedEmojiAnimations'}|array{_: 'inputStickerSetPremiumGifts'}|array{_: 'inputStickerSetEmojiGenericAnimations'}|array{_: 'inputStickerSetEmojiDefaultStatuses'}|array{_: 'inputStickerSetEmojiDefaultTopicIcons'}, free: bool, text_color: bool, alt: string}|array{_: 'documentAttributeSticker'}|array{_: 'documentAttributeVideo', duration: int, w: int, h: int}|array{_: 'documentAttributeAudio', duration: int}|array{_: 'documentAttributeSticker', stickerset: array{_: 'inputStickerSetEmpty'}|array{_: 'inputStickerSetID', id: int, access_hash: int}|array{_: 'inputStickerSetShortName', short_name: string}|array{_: 'inputStickerSetAnimatedEmoji'}|array{_: 'inputStickerSetDice', emoticon: string}|array{_: 'inputStickerSetAnimatedEmojiAnimations'}|array{_: 'inputStickerSetPremiumGifts'}|array{_: 'inputStickerSetEmojiGenericAnimations'}|array{_: 'inputStickerSetEmojiDefaultStatuses'}|array{_: 'inputStickerSetEmojiDefaultTopicIcons'}, alt: string}|array{_: 'documentAttributeAudio', duration: int, title: string, performer: string}>}, info?: array{_: 'paymentRequestedInfo', name: string, phone: string, email: string, shipping_address?: array{_: 'postAddress', street_line1: string, street_line2: string, city: string, state: string, country_iso2: string, post_code: string}}, shipping?: array{_: 'shippingOption', id: string, title: string, prices: list<array{_: 'labeledPrice', label: string, amount: int}>}, tip_amount: int, currency: string, total_amount: int, credentials_title: string, users: list<array|int|string>} @see https://docs.madelineproto.xyz/API_docs/types/payments.PaymentReceipt.html
     */
    public function getPaymentReceipt(array|int|string $peer, int $msg_id = 0): array;

    /**
     * Submit requested order information for validation.
     *
     * @param array{_: 'inputInvoiceMessage', peer: array|int|string, msg_id?: int}|array{_: 'inputInvoiceSlug', slug?: string} $invoice Invoice @see https://docs.madelineproto.xyz/API_docs/types/InputInvoice.html
     * @param array{_: 'paymentRequestedInfo', name?: string, phone?: string, email?: string, shipping_address?: array{_: 'postAddress', street_line1?: string, street_line2?: string, city?: string, state?: string, country_iso2?: string, post_code?: string}} $info Requested order information @see https://docs.madelineproto.xyz/API_docs/types/PaymentRequestedInfo.html
     * @param bool $save Save order information to re-use it for future orders
     * @return array{_: 'payments.validatedRequestedInfo', id: string, shipping_options: list<array{_: 'shippingOption', id: string, title: string, prices: list<array{_: 'labeledPrice', label: string, amount: int}>}>} @see https://docs.madelineproto.xyz/API_docs/types/payments.ValidatedRequestedInfo.html
     */
    public function validateRequestedInfo(array $invoice, array $info, bool $save = false): array;

    /**
     * Send compiled payment form.
     *
     * @param array{_: 'inputInvoiceMessage', peer: array|int|string, msg_id?: int}|array{_: 'inputInvoiceSlug', slug?: string} $invoice Invoice @see https://docs.madelineproto.xyz/API_docs/types/InputInvoice.html
     * @param array{_: 'inputPaymentCredentialsSaved', id?: string, tmp_password?: string}|array{_: 'inputPaymentCredentials', data: mixed, save?: bool}|array{_: 'inputPaymentCredentialsApplePay', payment_data: mixed}|array{_: 'inputPaymentCredentialsGooglePay', payment_token: mixed} $credentials Payment credentials @see https://docs.madelineproto.xyz/API_docs/types/InputPaymentCredentials.html
     * @param int $form_id Form ID
     * @param string $requested_info_id ID of saved and validated [order info](https://docs.madelineproto.xyz/API_docs/constructors/payments.validatedRequestedInfo.html)
     * @param string $shipping_option_id Chosen shipping option ID
     * @param int $tip_amount Tip, in the smallest units of the currency (integer, not float/double). For example, for a price of `US$ 1.45` pass `amount = 145`. See the exp parameter in [currencies.json](https://core.telegram.org/bots/payments/currencies.json), it shows the number of digits past the decimal point for each currency (2 for the majority of currencies).
     * @return array{_: 'payments.paymentResult', updates: array}|array{_: 'payments.paymentVerificationNeeded', url: string} @see https://docs.madelineproto.xyz/API_docs/types/payments.PaymentResult.html
     */
    public function sendPaymentForm(array $invoice, array $credentials, int $form_id = 0, string $requested_info_id = '', string $shipping_option_id = '', int $tip_amount = 0): array;

    /**
     * Get saved payment information.
     *
     * @return array{_: 'payments.savedInfo', has_saved_credentials: bool, saved_info?: array{_: 'paymentRequestedInfo', name: string, phone: string, email: string, shipping_address?: array{_: 'postAddress', street_line1: string, street_line2: string, city: string, state: string, country_iso2: string, post_code: string}}} @see https://docs.madelineproto.xyz/API_docs/types/payments.SavedInfo.html
     */
    public function getSavedInfo(): array;

    /**
     * Clear saved payment information.
     *
     * @param bool $credentials Remove saved payment credentials
     * @param bool $info Clear the last order settings saved by the user
     */
    public function clearSavedInfo(bool $credentials = false, bool $info = false): bool;

    /**
     * Get info about a credit card.
     *
     * @param string $number Credit card number
     * @return array{_: 'payments.bankCardData', title: string, open_urls: list<array{_: 'bankCardOpenUrl', url: string, name: string}>} @see https://docs.madelineproto.xyz/API_docs/types/payments.BankCardData.html
     */
    public function getBankCardData(string $number = ''): array;

    /**
     * Generate an [invoice deep link](https://core.telegram.org/api/links#invoice-links).
     *
     * @param string|array $invoice_media Invoice @see https://docs.madelineproto.xyz/API_docs/types/InputMedia.html
     * @return array{_: 'payments.exportedInvoice', url: string} @see https://docs.madelineproto.xyz/API_docs/types/payments.ExportedInvoice.html
     */
    public function exportInvoice(array|string $invoice_media): array;

    /**
     * Informs server about a purchase made through the App Store: for official applications only.
     *
     * @param array{_: 'inputStorePaymentPremiumSubscription', restore?: bool, upgrade?: bool}|array{_: 'inputStorePaymentGiftPremium', user_id: array|int|string, currency?: string, amount?: int} $purpose Payment purpose @see https://docs.madelineproto.xyz/API_docs/types/InputStorePaymentPurpose.html
     * @param string $receipt Receipt
     * @return array @see https://docs.madelineproto.xyz/API_docs/types/Updates.html
     */
    public function assignAppStoreTransaction(array $purpose, string $receipt = ''): array;

    /**
     * Informs server about a purchase made through the Play Store: for official applications only.
     *
     * @param mixed $receipt Any JSON-encodable data
     * @param array{_: 'inputStorePaymentPremiumSubscription', restore?: bool, upgrade?: bool}|array{_: 'inputStorePaymentGiftPremium', user_id: array|int|string, currency?: string, amount?: int} $purpose Payment purpose @see https://docs.madelineproto.xyz/API_docs/types/InputStorePaymentPurpose.html
     * @return array @see https://docs.madelineproto.xyz/API_docs/types/Updates.html
     */
    public function assignPlayMarketTransaction(mixed $receipt, array $purpose): array;

    /**
     * Checks whether Telegram Premium purchase is possible. Must be called before in-store Premium purchase, official apps only.
     *
     * @param array{_: 'inputStorePaymentPremiumSubscription', restore?: bool, upgrade?: bool}|array{_: 'inputStorePaymentGiftPremium', user_id: array|int|string, currency?: string, amount?: int} $purpose Payment purpose @see https://docs.madelineproto.xyz/API_docs/types/InputStorePaymentPurpose.html
     */
    public function canPurchasePremium(array $purpose): bool;
}
