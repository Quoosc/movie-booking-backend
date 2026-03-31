<?php

namespace App\Modules\HeroSlide\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class HeroSlideResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'heroSlideId' => (string) $this->hero_slide_id,
            'title' => (string) $this->title,
            'altText' => $this->alt_text,
            'imageUrl' => (string) $this->image_url,
            'imageCloudinaryId' => $this->image_cloudinary_id,
            'sortOrder' => (int) $this->sort_order,
            'isActive' => (bool) $this->is_active,
            'createdAt' => $this->created_at ? $this->created_at->toISOString() : null,
            'updatedAt' => $this->updated_at ? $this->updated_at->toISOString() : null,
        ];
    }
}
