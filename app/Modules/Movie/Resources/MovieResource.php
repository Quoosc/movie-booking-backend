<?php

namespace App\Modules\Movie\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MovieResource extends JsonResource
{
    public function toArray(mixed $request)
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
            'premiereDate'        => $this->premiere_date?->format('Y-m-d'),
            'createdAt'           => $this->created_at,
            'updatedAt'           => $this->updated_at,
            'avgRating'           => $this->reviews_count > 0
                                        ? round((float)$this->reviews_avg_rating, 1)
                                        : 10.0,
            'reviewCount'         => (int)($this->reviews_count ?? 0),
        ];
    }
}
