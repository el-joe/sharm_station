<?php

namespace App\Http\Resources\V1;

use App\Models\GoogleReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GoogleReview
 */
class GoogleReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author_name' => $this->author_name,
            'author_photo_url' => $this->author_photo_url,
            'author_url' => $this->author_url,
            'author_country' => $this->author_country,
            'rating' => $this->rating,
            'text' => $this->text,
            'language' => $this->language,
            'published_at' => $this->published_at?->toIso8601String(),
            'relative_time' => $this->published_at?->diffForHumans(),
        ];
    }
}
