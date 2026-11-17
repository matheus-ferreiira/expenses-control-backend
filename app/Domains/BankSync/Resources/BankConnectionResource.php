<?php

namespace App\Domains\BankSync\Resources;

use App\Domains\BankSync\Models\BankConnection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BankConnection */
class BankConnectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'name' => $this->displayName(),
            'custom_name' => $this->name,
            'connector' => [
                'id' => $this->connector_id,
                'name' => $this->connector_name,
                'image_url' => $this->connector_image_url,
                'color' => $this->connector_color ? '#'.ltrim($this->connector_color, '#') : null,
            ],
            'status' => $this->status,
            'execution_status' => $this->execution_status,
            'needs_action' => $this->needsAction(),
            'consent_expires_at' => $this->consent_expires_at?->toIso8601String(),
            'provider_updated_at' => $this->provider_updated_at?->toIso8601String(),
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'last_sync_error' => $this->last_sync_error,
            'accounts' => BankConnectionAccountResource::collection($this->whenLoaded('accounts')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
