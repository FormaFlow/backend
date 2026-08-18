<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Infrastructure\Http;

use FormaFlow\Entries\Application\Find\FindEntryByIdQuery;
use FormaFlow\Entries\Application\Find\FindEntryByIdQueryHandler;
use FormaFlow\Entries\Infrastructure\Http\Resources\EntryResource;
use FormaFlow\Entries\Infrastructure\Persistence\Eloquent\EntryModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class PublicApiEntryController
{
    public function __construct(
        private FindEntryByIdQueryHandler $handler,
    ) {
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $model = EntryModel::query()->find($id);
        $token = (string)$request->query('share_token', '');
        if ($model === null || !$this->validShare($model, $token)) {
            return response()->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }
        $entry = $this->handler->handle(new FindEntryByIdQuery($id));

        if ($entry === null) {
            return response()->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(new EntryResource($entry));
    }

    private function validShare(EntryModel $entry, string $token): bool
    {
        return $token !== ''
            && $entry->public_share_token_hash !== null
            && $entry->public_share_expires_at?->isFuture()
            && hash_equals($entry->public_share_token_hash, hash('sha256', $token));
    }
}
