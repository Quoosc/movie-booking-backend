<?php

namespace App\Modules\HeroSlide\Services;

use App\Models\HeroSlide;
use RuntimeException;

class HeroSlideService
{
    private function findById(string $heroSlideId): HeroSlide
    {
        $slide = HeroSlide::find($heroSlideId);

        if (!$slide) {
            throw new RuntimeException("Hero slide not found with id: {$heroSlideId}");
        }

        return $slide;
    }

    public function getPublicSlides()
    {
        return HeroSlide::where('is_active', true)
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get();
    }

    public function getAllSlides()
    {
        return HeroSlide::orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get();
    }

    public function getSlide(string $heroSlideId): HeroSlide
    {
        return $this->findById($heroSlideId);
    }

    public function createSlide(array $data): HeroSlide
    {
        $slide = new HeroSlide();
        $slide->title = $data['title'];
        $slide->alt_text = $data['altText'] ?? null;
        $slide->image_url = $data['imageUrl'];
        $slide->image_cloudinary_id = $data['imageCloudinaryId'] ?? null;
        $slide->sort_order = $data['sortOrder'] ?? 0;
        $slide->is_active = $data['isActive'] ?? true;
        $slide->save();

        return $slide->refresh();
    }

    public function updateSlide(string $heroSlideId, array $data): HeroSlide
    {
        $slide = $this->findById($heroSlideId);

        if (array_key_exists('title', $data)) {
            $slide->title = $data['title'];
        }
        if (array_key_exists('altText', $data)) {
            $slide->alt_text = $data['altText'];
        }
        if (array_key_exists('imageUrl', $data)) {
            $slide->image_url = $data['imageUrl'];
        }
        if (array_key_exists('imageCloudinaryId', $data)) {
            $slide->image_cloudinary_id = $data['imageCloudinaryId'];
        }
        if (array_key_exists('sortOrder', $data)) {
            $slide->sort_order = $data['sortOrder'];
        }
        if (array_key_exists('isActive', $data)) {
            $slide->is_active = (bool) $data['isActive'];
        }

        $slide->save();

        return $slide->refresh();
    }

    public function deleteSlide(string $heroSlideId): void
    {
        $slide = $this->findById($heroSlideId);
        $slide->delete();
    }
}
