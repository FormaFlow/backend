<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Infrastructure\Http;

use FormaFlow\Forms\Application\Find\FindFormByIdQuery;
use FormaFlow\Forms\Application\Find\FindFormByIdQueryHandler;
use FormaFlow\Forms\Application\Import\ImportFormFromJsonCommand;
use FormaFlow\Forms\Application\Import\ImportFormFromJsonCommandHandler;
use FormaFlow\Forms\Infrastructure\Http\Resources\FormResource;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class PublicApiFormController
{
    public function __construct(
        private FindFormByIdQueryHandler $handler,
        private ImportFormFromJsonCommandHandler $importHandler,
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

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'is_quiz' => 'boolean',
            'single_submission' => 'boolean',
            'published' => 'boolean',
            'fields' => 'array',
            'fields.*.label' => 'required|string',
            'fields.*.type' => 'required|string',
        ]);

        $user = UserModel::query()->first();
        if (!$user) {
            return response()->json(['error' => 'No users found to assign form'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $command = new ImportFormFromJsonCommand($request->all(), $user->id);
        $id = $this->importHandler->handle($command);

        return response()->json(['id' => $id, 'message' => 'Form imported successfully'], Response::HTTP_CREATED);
    }
}
