<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SnackResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'snackId'          => $this->snack_id,
            'cinemaId'         => $this->cinema_id,
            'name'             => $this->name,
            'description'      => $this->description,
            'price'            => $this->price,
            'type'             => $this->type,
            'imageUrl'         => $this->image_url,
            'imageCloudinaryId'=> $this->image_cloudinary_id,
            'createdAt'        => $this->created_at,
            'updatedAt'        => $this->updated_at,
        ];
    }
}
