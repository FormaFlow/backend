<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Infrastructure\Http;

use FormaFlow\Entries\Application\Find\FindEntryByIdQuery;
use FormaFlow\Entries\Application\Find\FindEntryByIdQueryHandler;
use FormaFlow\Entries\Infrastructure\Http\Resources\EntryResource;
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
        $entry = $this->handler->handle(new FindEntryByIdQuery($id));

        if ($entry === null) {
            return response()->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(new EntryResource($entry));
    }
}
