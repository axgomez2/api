<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'cpf' => $this->cpf,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'email_verified_at' => $this->email_verified_at,
            'formatted_cpf' => $this->formatted_cpf,
            'formatted_phone' => $this->formatted_phone,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relacionamentos opcionais
            'addresses' => AddressResource::collection($this->whenLoaded('addresses')),
            'default_address' => new AddressResource($this->whenLoaded('defaultAddress')),
        ];
    }
}