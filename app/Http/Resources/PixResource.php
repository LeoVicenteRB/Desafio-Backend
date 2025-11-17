<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PixResource extends JsonResource
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
            'external_pix_id' => $this->external_pix_id,
            'subadquirer' => $this->subadquirer,
            'amount' => (float) $this->amount,
            'status' => $this->status,
            'payer_name' => $this->payer_name,
            'payer_document' => $this->payer_document,
            'reference' => $this->reference,
            'payment_date' => $this->payment_date?->toIso8601String(),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}

