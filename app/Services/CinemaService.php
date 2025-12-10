<?php

namespace App\Services;

use App\Models\Cinema;
use App\Models\Room;
use App\Models\Snack;
use Illuminate\Support\Facades\DB;
use RuntimeException;


class CinemaService
{
    // ================== SNACK HELPERS ==================
    private function findSnackById(string $snackId): Snack
    {
        $snack = Snack::find($snackId);

        if (!$snack) {
            throw new RuntimeException("Snack not found with id: {$snackId}");
        }

        return $snack;
    }

    /**
     * Add a new snack to a cinema
     * (Spring: CinemaService.addSnack)
     */
    public function addSnack(array $data): Snack
    {
        $cinemaId = $data['cinemaId'];

        $cinema = Cinema::where('cinema_id', $cinemaId)->first();
        if (!$cinema) {
            throw new RuntimeException("Cinema not found with id: {$cinemaId}");
        }

        // duplicate name trong cùng cinema
        $exists = Snack::where('cinema_id', $cinemaId)
            ->where('name', $data['name'])
            ->exists();

        if ($exists) {
            throw new RuntimeException(
                sprintf("Snack '%s' already exists in this cinema", $data['name'])
            );
        }

        $snack = new Snack();
        $snack->cinema_id          = $cinemaId;
        $snack->name               = $data['name'];
        $snack->description        = $data['description'] ?? null;
        $snack->price              = $data['price'];
        $snack->type               = $data['type'];
        $snack->image_url          = $data['imageUrl'] ?? null;
        $snack->image_cloudinary_id = $data['imageCloudinaryId'] ?? null;

        $snack->save();

        return $snack->refresh();
    }

    /**
     * Update snack information
     * (Spring: CinemaService.updateSnack)
     */
    public function updateSnack(string $snackId, array $data): Snack
    {
        $snack = $this->findSnackById($snackId);

        if (!empty($data['name'])) {
            // check duplicate name trong cùng cinema nếu đổi tên
            $exists = Snack::where('cinema_id', $snack->cinema_id)
                ->where('name', $data['name'])
                ->where('snack_id', '!=', $snackId)
                ->exists();

            if ($exists) {
                throw new RuntimeException(
                    sprintf("Snack '%s' already exists in cinema", $data['name'])
                );
            }

            $snack->name = $data['name'];
        }

        if (array_key_exists('description', $data)) {
            $snack->description = $data['description'];
        }

        if (array_key_exists('price', $data) && $data['price'] !== null) {
            $snack->price = $data['price'];
        }

        if (array_key_exists('type', $data) && $data['type'] !== null) {
            $snack->type = $data['type'];
        }

        if (array_key_exists('imageUrl', $data)) {
            $snack->image_url = $data['imageUrl'];
        }

        if (array_key_exists('imageCloudinaryId', $data)) {
            $snack->image_cloudinary_id = $data['imageCloudinaryId'];
        }

        $snack->save();

        return $snack->refresh();
    }

    /**
     * Delete a snack
     * (Spring: CinemaService.deleteSnack)
     */
    public function deleteSnack(string $snackId): void
    {
        $snack = $this->findSnackById($snackId);

        // nếu đã có booking_snacks dùng snack này -> không cho xoá
        $usedCount = DB::table('booking_snacks')
            ->where('snack_id', $snackId)
            ->count();

        if ($usedCount > 0) {
            throw new RuntimeException('Cannot delete snack with existing bookings');
        }

        $snack->delete();
    }

    /**
     * Get snack by ID
     */
    public function getSnack(string $snackId): Snack
    {
        return $this->findSnackById($snackId);
    }

    /**
     * Get all snacks (admin)
     */
    public function getAllSnacks()
    {
        return Snack::orderBy('name')->get();
    }

    /**
     * Get snacks by cinema (public dùng cho booking)
     * /cinemas/{cinemaId}/snacks
     */
    public function getSnacksByCinema(string $cinemaId)
    {
        $cinema = Cinema::where('cinema_id', $cinemaId)->first();
        if (!$cinema) {
            throw new RuntimeException("Cinema not found with id: {$cinemaId}");
        }

        return Snack::where('cinema_id', $cinemaId)
            ->orderBy('name')
            ->get();
    }
}
