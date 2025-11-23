<?php

namespace FormaFlow\Identity\Infrastructure\Http\Resources;

use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UserModel */
final class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'email_verified' => $this->email_verified_at !== null,
        ];
    }
}
