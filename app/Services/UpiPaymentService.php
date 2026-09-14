<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GroupMember;
use App\Models\Settlement;
use App\Support\Money;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Str;

/**
 * Builds UPI payment requests so a settle-up can be paid in Google Pay, PhonePe, Paytm
 * or any other UPI app.
 *
 * What this does and does not do, because it matters:
 *
 *  - It DOES generate a standard NPCI UPI intent URI and a QR code for it. On a phone,
 *    tapping the link opens the user's UPI app with the payee, amount and reference
 *    already filled in. They confirm with their PIN. This is the supported route for
 *    person-to-person payments and needs no registration, no API key, no merchant account.
 *  - It does NOT move money by itself, and it cannot confirm that a payment happened.
 *    Google Pay has no public peer-to-peer API. Programmatic collection and automatic
 *    reconciliation require a licensed PSP (Razorpay, Cashfree, PhonePe Business and the
 *    like) acting as merchant, which is the wrong shape for splitting a dinner bill.
 *
 * So the flow is: generate the intent, the payer pays in their own app, then either party
 * marks the settlement confirmed. See Settlement::confirm().
 */
final class UpiPaymentService
{
    /**
     * A generic UPI intent. Android shows an app chooser; iOS opens the default handler.
     */
    public function intentUri(
        string $payeeVpa,
        string $payeeName,
        Money $amount,
        string $note,
        string $reference,
    ): string {
        $query = [
            'pa' => $payeeVpa,
            'pn' => $payeeName,
            'am' => $amount->toMajor(),
            'cu' => $amount->currency,
            'tn' => Str::limit($note, 50, ''),
            'tr' => $reference,
        ];

        return 'upi://pay?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * The same request addressed straight at Google Pay, skipping the app chooser.
     * Falls back to the generic intent on any device where the scheme is unhandled.
     */
    public function googlePayIntentUri(
        string $payeeVpa,
        string $payeeName,
        Money $amount,
        string $note,
        string $reference,
    ): string {
        return str_replace(
            'upi://pay?',
            'tez://upi/pay?',
            $this->intentUri($payeeVpa, $payeeName, $amount, $note, $reference),
        );
    }

    /** A scannable QR for the same request, for when the payer is on a different device. */
    public function qrCodeDataUri(string $intentUri): string
    {
        return (new QRCode(new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => QRCode::ECC_M,
            'scale' => 8,
            'imageBase64' => true,
        ])))->render($intentUri);
    }

    /**
     * Everything the settle-up screen needs for one payment.
     *
     * @return array{reference:string, upi:string, gpay:string, qr:string}|null
     */
    public function requestFor(GroupMember $payee, Money $amount, string $note): ?array
    {
        $vpa = $payee->upi_vpa ?? $payee->user?->upi_vpa;

        if (blank($vpa)) {
            return null;
        }

        $reference = 'EXP'.strtoupper(Str::random(10));
        $intent = $this->intentUri($vpa, $payee->display_name, $amount, $note, $reference);

        return [
            'reference' => $reference,
            'upi' => $intent,
            'gpay' => $this->googlePayIntentUri($vpa, $payee->display_name, $amount, $note, $reference),
            'qr' => $this->qrCodeDataUri($intent),
        ];
    }

    /** Attach a freshly generated request to a settlement so the reference survives a reload. */
    public function attachTo(Settlement $settlement): Settlement
    {
        $request = $this->requestFor(
            $settlement->toMember,
            $settlement->amount,
            "Settle up: {$settlement->group?->name}",
        );

        if ($request !== null) {
            $settlement->forceFill([
                'payment_reference' => $request['reference'],
                'upi_intent_uri' => $request['upi'],
            ])->save();
        }

        return $settlement;
    }
}
