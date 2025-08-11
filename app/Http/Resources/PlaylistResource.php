<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlaylistResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_formatted' => $this->type_formatted,
            'title' => $this->title,
            'description' => $this->description,
            'chart_date' => $this->chart_date?->format('Y-m-d'),
            'social_links' => $this->social_links,
            'dj_photo' => $this->dj_photo,
            'dj_photo_url' => $this->dj_photo_url,
            'is_active' => $this->is_active,
            'position' => $this->position,
            'tracks_count' => $this->whenCounted('tracks'),
            'tracks' => PlaylistTrackResource::collection($this->whenLoaded('tracksWithProducts')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
