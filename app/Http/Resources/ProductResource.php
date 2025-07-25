<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $this->when($this->productable_type === 'App\\Models\\VinylMaster', 
                $this->productable?->vinylSec?->price
            ),
            'stock' => $this->when($this->productable_type === 'App\\Models\\VinylMaster', 
                $this->productable?->vinylSec?->stock ?? 0
            ),
            'in_stock' => $this->in_stock,
            'type' => $this->productable_type,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Dados específicos do vinil quando aplicável
            'vinyl_data' => $this->when($this->productable_type === 'App\\Models\\VinylMaster', [
                'release_year' => $this->productable?->release_year,
                'catalog_number' => $this->productable?->catalog_number,
                'barcode' => $this->productable?->barcode,
                'format' => $this->productable?->format,
                'rpm' => $this->productable?->rpm,
                'size' => $this->productable?->size,
                'weight' => [
                    'value' => $this->productable?->vinylSec?->weight?->weight,
                    'unit' => $this->productable?->vinylSec?->weight?->unit,
                ],
                'dimensions' => [
                    'length' => $this->productable?->vinylSec?->dimension?->length,
                    'width' => $this->productable?->vinylSec?->dimension?->width,
                    'height' => $this->productable?->vinylSec?->dimension?->height,
                ],
                'condition' => [
                    'media' => $this->productable?->vinylSec?->midiaStatus?->status,
                    'cover' => $this->productable?->vinylSec?->coverStatus?->status,
                ],
            ]),
            
            // Relacionamentos
            'artists' => ArtistResource::collection($this->whenLoaded('productable.artists')),
            'record_label' => new RecordLabelResource($this->whenLoaded('productable.recordLabel')),
            'categories' => CategoryResource::collection($this->whenLoaded('productable.categories')),
            'tracks' => TrackResource::collection($this->whenLoaded('productable.tracks')),
            'media' => MediaResource::collection($this->whenLoaded('productable.media')),
        ];
    }
}