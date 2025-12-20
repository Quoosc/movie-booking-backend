<?php

namespace App\Transformers;

use App\Models\Payment;

class PaymentTransformer
{
    public static function mapStatusForResponse($status): ?string
    {
        if ($status === null) {
            return null;
        }

        $value = is_string($status) ? strtoupper($status) : (string) $status;
        if ($value === 'COMPLETED') {
            return 'SUCCESS';
        }

        return $value;
    }

    public static function toPaymentResponse(Payment $payment): array
    {
        $booking = $payment->booking;

        return [
            'paymentId'       => (string) $payment->payment_id,
            'bookingId'       => $booking?->booking_id ? (string) $booking->booking_id : null,
            'bookingStatus'   => $booking?->status?->value ?? $booking?->status,
            'qrPayload'       => $booking?->qr_payload,

            'status'          => self::mapStatusForResponse($payment->status?->value ?? $payment->status),
            'method'          => $payment->method?->value ?? $payment->method,
            'amount'          => $payment->amount,
            'currency'        => $payment->currency,
            'gatewayAmount'   => $payment->gateway_amount,
            'gatewayCurrency' => $payment->gateway_currency,
            'transactionId'   => $payment->transaction_id,
            'completedAt'     => optional($payment->completed_at)->toIso8601String(),
            'errorMessage'    => $payment->error_message,
        ];
    }
}
