<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Infrastructure\Http;

use Exception;
use FormaFlow\Forms\Application\AddField\AddFieldCommand;
use FormaFlow\Forms\Application\AddField\AddFieldCommandHandler;
use FormaFlow\Forms\Application\Create\CreateFormCommand;
use FormaFlow\Forms\Application\Create\CreateFormCommandHandler;
use FormaFlow\Forms\Application\Find\FindFormByIdQuery;
use FormaFlow\Forms\Application\Find\FindFormByIdQueryHandler;
use FormaFlow\Forms\Application\Find\FindFormsByUserIdQuery;
use FormaFlow\Forms\Application\Find\FindFormsByUserIdQueryHandler;
use FormaFlow\Forms\Application\Publish\PublishFormCommand;
use FormaFlow\Forms\Application\Publish\PublishFormCommandHandler;
use Illuminate\Http\Request;
use Shared\Infrastructure\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class FormHttpController
{
    public function index(
        Request $request,
        FindFormsByUserIdQueryHandler $handler,
    ): Response {
        $query = new FindFormsByUserIdQuery($request->user()->id);
        $result = $handler->handle($query);

        return response()->json($result);
    }

    public function store(
        Request $request,
        CreateFormCommandHandler $handler,
    ): Response {
        $validated = $request->validate([
            'name' => 'required|string|min:3|max:255',
            'description' => 'nullable|string',
        ]);

        $command = new CreateFormCommand(
            id: Uuid::generate(),
            userId: $request->user()->id,
            name: $validated['name'],
            description: $validated['description'] ?? null,
        );

        $handler->handle($command);

        return response()->json(['id' => $command->id()], Response::HTTP_CREATED);
    }

    public function show(
        string $id,
        FindFormByIdQueryHandler $handler,
    ): Response {
        $query = new FindFormByIdQuery($id);
        $form = $handler->handle($query);

        if (!$form) {
            return response()->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'id' => $form->id()->value(),
            'name' => $form->name()->value(),
            'description' => $form->description(),
            'published' => $form->isPublished(),
            'fields_count' => count($form->fields()),
        ]);
    }

    public function publish(
        string $id,
        PublishFormCommandHandler $handler,
    ): Response {
        try {
            $command = new PublishFormCommand($id);
            $handler->handle($command);
            return response()->json(['message' => 'Form published']);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    public function addField(
        string $id,
        Request $request,
        AddFieldCommandHandler $handler,
    ): Response {
        $validated = $request->validate([
            'name' => 'required|string',
            'label' => 'required|string',
            'type' => 'required|in:text,number,date,boolean,select,currency,email',
            'required' => 'boolean',
            'options' => 'nullable|array',
            'unit' => 'nullable|string',
            'category' => 'nullable|string',
            'order' => 'integer',
        ]);

        try {
            $command = new AddFieldCommand(
                formId: $id,
                fieldId: Uuid::generate(),
                name: $validated['name'],
                label: $validated['label'],
                type: $validated['type'],
                required: $validated['required'] ?? false,
                options: $validated['options'] ?? null,
                unit: $validated['unit'] ?? null,
                category: $validated['category'] ?? null,
                order: $validated['order'] ?? 0,
            );

            $handler->handle($command);
            return response()->json(['message' => 'Field added'], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
