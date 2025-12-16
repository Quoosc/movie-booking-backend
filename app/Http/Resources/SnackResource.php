<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SnackResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'snackId'           => (string) $this->snack_id,
            'cinemaId'          => (string) $this->cinema_id,
            'name'              => $this->name,
            'description'       => $this->description,
            'price'             => $this->price !== null ? (float) $this->price : null,
            'type'              => $this->type,
            'imageUrl'          => $this->image_url,
            'imageCloudinaryId' => $this->image_cloudinary_id,
            'createdAt'         => $this->created_at,
            'updatedAt'         => $this->updated_at,
        ];
    }
}
