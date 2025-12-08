<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MovieResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'movieId'             => $this->movie_id,
            'title'               => $this->title,
            'genre'               => $this->genre,
            'description'         => $this->description,
            'duration'            => $this->duration,
            'minimumAge'          => $this->minimum_age,
            'director'            => $this->director,
            'actors'              => $this->actors,
            'posterUrl'           => $this->poster_url,
            'posterCloudinaryId'  => $this->poster_cloudinary_id,
            'trailerUrl'          => $this->trailer_url,
            'status'              => $this->status,
            'language'            => $this->language,
            'createdAt'           => $this->created_at,
            'updatedAt'           => $this->updated_at,
        ];
    }
}
