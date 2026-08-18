<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Infrastructure\Http;

use FormaFlow\Entries\Infrastructure\Persistence\Eloquent\EntryModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EntryShareController
{
    public function store(Request $request, string $id): JsonResponse
    {
        $entry = EntryModel::query()->find($id);
        if ($entry === null) {
            return response()->json(['message' => 'Not found'], Response::HTTP_NOT_FOUND);
        }
        if ($entry->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $token = bin2hex(random_bytes(32));
        $entry->forceFill([
            'public_share_token_hash' => hash('sha256', $token),
            'public_share_expires_at' => now()->addDays(7),
        ])->save();

        return response()->json([
            'share_token' => $token,
            'expires_at' => $entry->public_share_expires_at->format('c'),
            'url' => url("/shared/result/{$entry->id}?share_token={$token}"),
        ], Response::HTTP_CREATED);
    }
}
