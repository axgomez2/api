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
        // Lógica de preço promocional - seguindo VinylCard.vue
        $basePrice = $this->price ?? $this->productable?->vinylSec?->price ?? 0;
        $promotionalPrice = null;
        $isPromotional = false;
        
        if ($this->productable_type === 'App\\Models\\VinylMaster' && $this->productable?->vinylSec) {
            $vinylSec = $this->productable->vinylSec;
            if ($vinylSec->is_promotional && $vinylSec->promotional_price) {
                $promotionalPrice = $vinylSec->promotional_price;
                $isPromotional = true;
            }
        }
        
        // Lógica de imagem - usando cover_image do vinylmasters (productable)
        $imageUrl = null;
        if ($this->productable && $this->productable->cover_image) {
            $imageUrl = $this->productable->cover_image;
        } elseif ($this->productable?->vinylSec?->image_url) {
            $imageUrl = $this->productable->vinylSec->image_url;
        } elseif ($this->image_url) {
            $imageUrl = $this->image_url;
        }
        
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $basePrice,
            'promotional_price' => $promotionalPrice,
            'is_promotional' => $isPromotional,
            'final_price' => $promotionalPrice ?? $basePrice,
            'image_url' => $imageUrl,
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