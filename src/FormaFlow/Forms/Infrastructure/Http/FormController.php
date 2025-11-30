<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Infrastructure\Http;

use Exception;
use FormaFlow\Entries\Infrastructure\Persistence\Eloquent\EntryModel;
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
use FormaFlow\Forms\Domain\FormAggregate;
use FormaFlow\Forms\Domain\FormId;
use FormaFlow\Forms\Domain\FormName;
use FormaFlow\Forms\Domain\FormRepository;
use Illuminate\Http\Request;
use Shared\Infrastructure\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class FormController
{
    public function __construct(
        private readonly FormRepository $formRepository,
    ) {
    }

    public function index(
        Request $request,
        FindFormsByUserIdQueryHandler $handler,
    ): Response {
        $query = new FindFormsByUserIdQuery($request->user()->id);
        $result = $handler->handle($query);

        $transformedForms = [];
        foreach ($result['forms'] as $form) {
            $fieldsData = [];
            foreach ($form->fields() as $field) {
                $fieldsData[] = [
                    'id' => $field->id(),
                    'name' => $field->name(),
                    'label' => $field->label(),
                    'type' => $field->type()->value(),
                    'required' => $field->isRequired(),
                    'options' => $field->options(),
                    'unit' => $field->unit(),
                    'category' => $field->category(),
                    'order' => $field->order(),
                ];
            }

            $transformedForms[] = [
                'id' => $form->id()->value(),
                'name' => $form->name()->value(),
                'description' => $form->description(),
                'published' => $form->isPublished(),
                'fields_count' => count($form->fields()),
                'fields' => $fieldsData,
            ];
        }

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
        Request $request,
        string $id,
        FindFormByIdQueryHandler $handler,
    ): Response {
        $query = new FindFormByIdQuery($id);
        $form = $handler->handle($query);

        if ($form === null) {
            return response()->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        if ($form->userId() !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $fieldsData = [];
        foreach ($form->fields() as $field) {
            $fieldsData[] = [
                'id' => $field->id(),
                'name' => $field->name(),
                'label' => $field->label(),
                'type' => $field->type()->value(),
                'required' => $field->isRequired(),
                'options' => $field->options(),
                'unit' => $field->unit(),
                'category' => $field->category(),
                'order' => $field->order(),
            ];
        }

        return response()->json([
            'id' => $form->id()->value(),
            'name' => $form->name()->value(),
            'description' => $form->description(),
            'published' => $form->isPublished(),
            'fields_count' => count($form->fields()),
            'fields' => $fieldsData,
        ]);
    }

    public function publish(
        Request $request,
        string $id,
        PublishFormCommandHandler $handler,
    ): Response {
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
    ): Response {
        $form = $this->formRepository->findById(new FormId($id));

        if ($form === null) {
            return response()->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        if ($form->userId() !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

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

    public function update(
        Request $request,
        string $id
    ): Response {
        $validated = $request->validate([
            'name' => 'sometimes|string|min:3|max:255',
            'description' => 'nullable|string',
        ]);

        $formId = new FormId($id);
        $form = $this->formRepository->findById($formId);

        if ($form === null) {
            return response()->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        if ($form->userId() !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $updatedForm = FormAggregate::fromPrimitives(
            id: $formId,
            userId: $form->userId(),
            name: isset($validated['name']) ? new FormName($validated['name']) : $form->name(),
            description: $validated['description'] ?? $form->description(),
            published: $form->isPublished(),
            version: $form->isPublished() ? $form->getVersion() + 1 : $form->getVersion(),
            createdAt: $form->createdAt(),
            fields: $form->fields(),
        );

        $this->formRepository->save($updatedForm);

        return response()->json(['message' => 'Form updated'], Response::HTTP_OK);
    }

    public function importEntries(Request $request, string $id): Response
    {
        $form = $this->formRepository->findById(new FormId($id));

        if ($form === null) {
            return response()->json(['error' => 'Form not found'], Response::HTTP_NOT_FOUND);
        }

        if ($form->userId() !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        if (!$form->isPublished()) {
            return response()->json(['error' => 'Form must be published'], Response::HTTP_BAD_REQUEST);
        }

        $csvData = $request->input('csv_data');
        $delimiter = $request->input('delimiter', ',');

        $lines = explode("\n", trim($csvData));
        $headers = str_getcsv(array_shift($lines), $delimiter);

        $imported = 0;
        $errors = [];

        foreach ($lines as $index => $line) {
            $values = str_getcsv($line, $delimiter);
            $data = array_combine($headers, $values);

            $valid = true;
            foreach ($form->fields() as $field) {
                if ($field->isRequired() && empty($data[$field->name()])) {
                    $errors[] = "Row " . ($index + 2) . ": Missing required field '{$field->name()}'";
                    $valid = false;
                    break;
                }

                if (isset($data[$field->name()])) {
                    switch ($field->type()->value()) {
                        case 'number':
                        case 'currency':
                            if (!is_numeric($data[$field->name()])) {
                                $errors[] = "Row " . ($index + 2) . ": Invalid number for '{$field->name()}'";
                                $valid = false;
                            }
                            break;
                    }
                }
            }

            if ($valid) {
                $entryId = Uuid::generate();
                EntryModel::query()->create([
                    'id' => $entryId,
                    'form_id' => $id,
                    'user_id' => $request->user()->id,
                    'data' => $data,
                ]);
                $imported++;
            }
        }

        return response()->json([
            'imported' => $imported,
            'errors' => $errors,
        ]);
    }

    public function destroy(
        Request $request,
        string $id,
        DeleteFormCommandHandler $handler
    ): Response {
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
    ): Response {
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
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
