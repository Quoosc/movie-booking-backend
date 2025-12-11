<?php
// app/DTO/Payments/IpnResponse.php
namespace App\DTO\Payments;

class IpnResponse
{
    public function __construct(
        public int    $resultCode,
        public string $message,
    ) {}

    public static function invalidChecksum(): self
    {
        return new self(-1, 'Invalid checksum');
    }

    public static function orderNotFound(): self
    {
        return new self(1, 'Order not found');
    }

    public static function amountInvalid(): self
    {
        return new self(2, 'Amount invalid');
    }

    public static function orderAlreadyConfirmed(): self
    {
        return new self(0, 'Order already confirmed');
    }

    public static function ok(): self
    {
        return new self(0, 'OK');
    }

    public static function paymentFailed(): self
    {
        return new self(3, 'Payment failed');
    }
}
