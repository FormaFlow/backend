<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Infrastructure\Http;

use FormaFlow\Forms\Application\Find\FindFormByIdQuery;
use FormaFlow\Forms\Application\Find\FindFormByIdQueryHandler;
use FormaFlow\Forms\Infrastructure\Http\Resources\FormResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class PublicApiFormController
{
    public function __construct(
        private FindFormByIdQueryHandler $handler,
    ) {
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $query = new FindFormByIdQuery($id);
        $form = $this->handler->handle($query);

        if ($form === null || !$form->isPublished()) {
            return response()->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(new FormResource($form));
    }
}
