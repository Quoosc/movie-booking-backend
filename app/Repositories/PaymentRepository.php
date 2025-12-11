<?php

namespace App\Repositories;

use App\Models\Payment;
use Illuminate\Support\Collection;

class PaymentRepository
{
    public function create(array $data): Payment
    {
        return Payment::create($data);
    }

    public function save(Payment $payment): void
    {
        $payment->save();
    }

    public function findById(string $id): ?Payment
    {
        return Payment::with(['booking', 'user'])->find($id);
    }

    public function findByOrderId(string $orderId): ?Payment
    {
        return Payment::with(['booking', 'user'])
            ->where('order_id', $orderId)
            ->first();
    }

    public function findByTransactionId(string $txnRef): ?Payment
    {
        return Payment::with(['booking', 'user'])
            ->where('txn_ref', $txnRef)
            ->first();
    }

    public function searchPaymentsByUser(string $userId, array $filters = []): Collection
    {
        $query = Payment::where('user_id', $userId);

        if (isset($filters['bookingId'])) {
            $query->where('booking_id', $filters['bookingId']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['method'])) {
            $query->where('method', $filters['method']);
        }

        if (isset($filters['startDate'])) {
            $query->whereDate('created_at', '>=', $filters['startDate']);
        }

        if (isset($filters['endDate'])) {
            $query->whereDate('created_at', '<=', $filters['endDate']);
        }

        return $query->with(['booking'])->orderBy('created_at', 'desc')->get();
    }
}
