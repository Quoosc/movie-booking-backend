<?php

namespace App\Repositories;

use App\Models\Refund;

class RefundRepository
{
    public function create(array $data): Refund
    {
        return Refund::create($data);
    }

    public function save(Refund $refund): void
    {
        $refund->save();
    }

    public function findById(string $id): ?Refund
    {
        return Refund::with(['payment', 'booking', 'user'])->find($id);
    }
}
