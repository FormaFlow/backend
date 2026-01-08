<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Infrastructure\Http;

use Exception;
use FormaFlow\Entries\Application\Import\ImportEntriesCommand;
use FormaFlow\Entries\Application\Import\ImportEntriesCommandHandler;
use FormaFlow\Forms\Application\AddField\AddFieldCommand;
use FormaFlow\Forms\Application\AddField\AddFieldCommandHandler;
use FormaFlow\Forms\Application\Create\CreateFormCommand;
use FormaFlow\Forms\Application\Create\CreateFormCommandHandler;
use FormaFlow\Forms\Application\Delete\DeleteFormCommand;
use FormaFlow\Forms\Application\Delete\DeleteFormCommandHandler;
use FormaFlow\Forms\Application\Find\FindFormByIdQuery;
use FormaFlow\Forms\Application\Find\FindFormByIdQueryHandler;
use FormaFlow\Forms\Application\Find\FindFormsByUserIdQuery;
use FormaFlow\Forms\Application\Find\FindFormsByUserIdQueryHandler;
use FormaFlow\Forms\Application\Publish\PublishFormCommand;
use FormaFlow\Forms\Application\Publish\PublishFormCommandHandler;
use FormaFlow\Forms\Application\RemoveField\RemoveFieldCommand;
use FormaFlow\Forms\Application\RemoveField\RemoveFieldCommandHandler;
use FormaFlow\Forms\Application\Update\UpdateFormCommand;
use FormaFlow\Forms\Application\Update\UpdateFormCommandHandler;
use FormaFlow\Forms\Application\UpdateField\UpdateFieldCommand;
use FormaFlow\Forms\Application\UpdateField\UpdateFieldCommandHandler;
use FormaFlow\Forms\Domain\FormId;
use FormaFlow\Forms\Domain\FormRepository;
use FormaFlow\Forms\Infrastructure\Http\Resources\FormResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Shared\Infrastructure\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class FormController
{
    public function __construct(
        private FormRepository $formRepository,
    ) {
    }

    public function index(
        Request $request,
        FindFormsByUserIdQueryHandler $handler,
    ): JsonResponse {
        $query = new FindFormsByUserIdQuery($request->user()->id);
        $result = $handler->handle($query);

        $transformedForms = array_map(
            fn($form) => new FormResource($form),
            $result['forms']
        );

        return response()->json([
            'forms' => $transformedForms,
            'total' => $result['total'],
            'limit' => $result['limit'],
            'offset' => $result['offset'],
        ]);
    }

    public function store(
        Request $request,
        CreateFormCommandHandler $handler,
    ): JsonResponse {
        $validated = $request->validate([
            'name' => 'required|string|min:3|max:255',
            'description' => 'nullable|string',
            'is_quiz' => 'boolean',
            'single_submission' => 'boolean',
        ]);

        $command = new CreateFormCommand(
            id: Uuid::generate(),
            userId: $request->user()->id,
            name: $validated['name'],
            description: $validated['description'] ?? null,
            isQuiz: $validated['is_quiz'] ?? false,
            singleSubmission: $validated['single_submission'] ?? false,
        );

        $handler->handle($command);

        return response()->json(['id' => $command->id()], Response::HTTP_CREATED);
    }

    public function show(
        Request $request,
        string $id,
        FindFormByIdQueryHandler $handler,
    ): JsonResponse {
        $query = new FindFormByIdQuery($id);
        $form = $handler->handle($query);

        if ($form === null) {
            return response()->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        if ($form->userId() !== $request->user()->id && !$form->isPublished()) {
            return response()->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        return response()->json(new FormResource($form));
    }

    public function publish(
        Request $request,
        string $id,
        PublishFormCommandHandler $handler,
    ): JsonResponse {
        $form = $this->formRepository->findById(new FormId($id));

        if ($form === null) {
            return response()->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        if ($form->userId() !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        try {
            $command = new PublishFormCommand($id);
            $handler->handle($command);
            return response()->json(['message' => 'Form published']);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    public function addField(
        Request $request,
        string $id,
        AddFieldCommandHandler $handler,
    ): JsonResponse {
        $form = $this->formRepository->findById(new FormId($id));

        if ($form === null) {
            return response()->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        if ($form->userId() !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'label' => 'required|string',
            'type' => 'required|in:text,number,date,boolean,select,currency,email',
            'required' => 'boolean',
            'options' => 'nullable|array',
            'unit' => 'nullable|string',
            'category' => 'nullable|string',
            'order' => 'integer',
            'correctAnswer' => 'nullable|string',
            'points' => 'integer',
        ]);

        try {
            $command = new AddFieldCommand(
                formId: $id,
                fieldId: Uuid::generate(),
                label: $validated['label'],
                type: $validated['type'],
                required: $validated['required'] ?? false,
                options: $validated['options'] ?? null,
                unit: $validated['unit'] ?? null,
                category: $validated['category'] ?? null,
                order: $validated['order'] ?? 0,
                correctAnswer: $validated['correctAnswer'] ?? null,
                points: $validated['points'] ?? 0,
            );

            $handler->handle($command);
            return response()->json(['message' => 'Field added'], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    public function updateField(
        Request $request,
        string $formId,
        string $fieldId,
        UpdateFieldCommandHandler $handler,
    ): JsonResponse {
        $form = $this->formRepository->findById(new FormId($formId));
        if (!$form) {
            return response()->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }
        if ($form->userId() !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'label' => 'sometimes|string',
            'type' => 'sometimes|in:text,number,date,boolean,select,currency,email',
            'required' => 'sometimes|boolean',
            'options' => 'sometimes|nullable|array',
            'unit' => 'sometimes|nullable|string',
            'category' => 'sometimes|nullable|string',
            'order' => 'sometimes|integer',
            'correctAnswer' => 'sometimes|nullable|string',
            'points' => 'sometimes|integer',
        ]);

        try {
            $command = new UpdateFieldCommand($formId, $fieldId, $validated);

            $handler->handle($command);
            return response()->json(['message' => 'Field updated']);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    public function update(
        Request $request,
        string $id,
        UpdateFormCommandHandler $handler
    ): JsonResponse {
        $validated = $request->validate([
            'name' => 'sometimes|string|min:3|max:255',
            'description' => 'nullable|string',
            'is_quiz' => 'sometimes|boolean',
            'single_submission' => 'sometimes|boolean',
        ]);

        try {
            $command = new UpdateFormCommand(
                id: $id,
                userId: $request->user()->id,
                name: $validated['name'] ?? null,
                description: array_key_exists('description', $validated) ? $validated['description'] : null,
                isQuiz: $validated['is_quiz'] ?? null,
                singleSubmission: $validated['single_submission'] ?? null,
            );

            $handler->handle($command);

            return response()->json(['message' => 'Form updated'], Response::HTTP_OK);
        } catch (RuntimeException $e) {
            $status = match ($e->getMessage()) {
                'Not found' => Response::HTTP_NOT_FOUND,
                'Forbidden' => Response::HTTP_FORBIDDEN,
                default => Response::HTTP_INTERNAL_SERVER_ERROR,
            };
            return response()->json(['error' => $e->getMessage()], $status);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function importEntries(
        Request $request,
        string $id,
        ImportEntriesCommandHandler $handler
    ): JsonResponse {
        try {
            $command = new ImportEntriesCommand(
                userId: $request->user()->id,
                formId: $id,
                csvData: (string)$request->input('csv_data'),
                delimiter: (string)$request->input('delimiter', ',')
            );

            $result = $handler->handle($command);

            return response()->json($result);
        } catch (RuntimeException $e) {
            $status = match ($e->getMessage()) {
                'Form not found' => Response::HTTP_NOT_FOUND,
                'Forbidden' => Response::HTTP_FORBIDDEN,
                'Form must be published' => Response::HTTP_BAD_REQUEST,
                default => Response::HTTP_INTERNAL_SERVER_ERROR,
            };
            return response()->json(['error' => $e->getMessage()], $status);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(
        Request $request,
        string $id,
        DeleteFormCommandHandler $handler
    ): JsonResponse {
        $form = $this->formRepository->findById(new FormId($id));
        if (!$form) {
            return response()->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }
        if ($form->userId() !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }
        $handler->handle(new DeleteFormCommand($id));
        return response()->json(['message' => 'Form deleted'], Response::HTTP_OK);
    }

    public function removeField(
        Request $request,
        string $formId,
        string $fieldId,
        RemoveFieldCommandHandler $handler
    ): JsonResponse {
        $form = $this->formRepository->findById(new FormId($formId));
        if (!$form) {
            return response()->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }
        if ($form->userId() !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }
        try {
            $handler->handle(new RemoveFieldCommand($formId, $fieldId));
            return response()->json(['message' => 'Field removed']);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
