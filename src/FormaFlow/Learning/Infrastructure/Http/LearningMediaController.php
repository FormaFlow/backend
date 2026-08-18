<?php

declare(strict_types=1);

namespace FormaFlow\Learning\Infrastructure\Http;

use FormaFlow\Workspaces\Infrastructure\Persistence\Eloquent\WorkspaceMembershipModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class LearningMediaController
{
    public function store(Request $request, string $workspaceId): JsonResponse
    {
        $allowed = WorkspaceMembershipModel::query()->where([
            'workspace_id' => $workspaceId, 'user_id' => $request->user()->id, 'status' => 'active',
        ])->whereIn('role', ['owner', 'admin'])->exists();
        if (!$allowed) {
            return response()->json(['message' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }
        $validated = $request->validate([
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'alt_text' => ['required', 'string', 'max:255'],
        ]);
        $file = $validated['file'];
        $checksum = hash_file('sha256', $file->getRealPath());
        $existing = DB::table('media_assets')->where([
            'workspace_id' => $workspaceId, 'checksum' => $checksum,
        ])->first();
        if ($existing !== null) {
            return response()->json(['asset' => $this->serialize($existing)]);
        }
        $extension = $file->guessExtension() ?: 'bin';
        $path = $file->storeAs('learning/' . $workspaceId, $checksum . '.' . $extension, 'public');
        $id = (string)Str::uuid();
        DB::table('media_assets')->insert([
            'id' => $id, 'workspace_id' => $workspaceId, 'uploaded_by_user_id' => $request->user()->id,
            'disk' => 'public', 'path' => $path, 'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(), 'checksum' => $checksum, 'alt_text' => $validated['alt_text'],
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return response()->json(['asset' => $this->serialize(DB::table('media_assets')->where('id', $id)->first())], Response::HTTP_CREATED);
    }

    private function serialize(object $asset): array
    {
        return [
            'id' => $asset->id,
            'url' => Storage::disk($asset->disk)->url($asset->path),
            'mime_type' => $asset->mime_type,
            'alt_text' => $asset->alt_text,
        ];
    }
}
