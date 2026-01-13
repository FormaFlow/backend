<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Infrastructure\Http;

use DateInvalidTimeZoneException;
use DateMalformedStringException;
use DateTimeInterface;
use FormaFlow\Entries\Application\Create\CreateEntryCommand;
use FormaFlow\Entries\Application\Create\CreateEntryCommandHandler;
use FormaFlow\Entries\Application\Stats\GetEntriesStatsQuery;
use FormaFlow\Entries\Application\Stats\GetEntriesStatsQueryHandler;
use FormaFlow\Entries\Application\Update\UpdateEntryCommand;
use FormaFlow\Entries\Application\Update\UpdateEntryCommandHandler;
use FormaFlow\Entries\Domain\EntryId;
use FormaFlow\Entries\Domain\EntryRepository;
use FormaFlow\Entries\Infrastructure\Persistence\Eloquent\EntryTagModel;
use FormaFlow\Forms\Domain\FormId;
use FormaFlow\Forms\Domain\FormRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Shared\Infrastructure\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class EntryController extends Controller
{
    public function __construct(
        private readonly EntryRepository $entryRepository,
        private readonly FormRepository $formRepository,
        private readonly CreateEntryCommandHandler $createHandler,
        private readonly UpdateEntryCommandHandler $updateHandler,
        private readonly GetEntriesStatsQueryHandler $statsHandler,
    ) {
    }


    public function index(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 15);

        if ($limit > 100) {
            $limit = 100;
        }

        $offset = $request->input('offset', 0);

        $filters = [];

        if ($request->has('form_id')) {
            $filters['form_id'] = $request->input('form_id');
        }

        if ($request->has('date_from')) {
            $filters['date_from'] = $request->input('date_from');
        }

        if ($request->has('date_to')) {
            $filters['date_to'] = $request->input('date_to');
        }

        if ($request->has('tags')) {
            $filters['tags'] = $request->input('tags');
        }

        if ($request->has('sort_by')) {
            $filters['sort_by'] = $request->input('sort_by');

            $filters['sort_order'] = $request->input('sort_order', 'asc');
        }

        [$entries, $total] = $this->entryRepository->findWithFormByUserId(
            $request->user()->id,
            $filters,
            (int)$limit,
            (int)$offset,
        );

        return response()->json([
            'entries' => array_map(fn($entry) => array_merge($entry, [
                'created_at' => $entry['created_at'] instanceof DateTimeInterface
                    ? $entry['created_at']->format('c')
                    : $entry['created_at'],
            ]), $entries),
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }


    /**
     * @throws DateInvalidTimeZoneException
     * @throws DateMalformedStringException
     */
    public function stats(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'form_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $query = new GetEntriesStatsQuery(
            formId: $request->input('form_id'),
            userId: $request->user()->id,
        );

        $result = $this->statsHandler->handle($query);

        return response()->json([
            'stats' => $result->stats,
        ]);
    }


    public function show(Request $request, string $id): JsonResponse
    {
        $entry = $this->entryRepository->findById(new EntryId($id));

        if ($entry === null) {
            return response()->json(['error' => 'Entry not found'], Response::HTTP_NOT_FOUND);
        }

        if ($entry->userId() !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $tags = EntryTagModel::query()
            ->where('entry_id', $id)
            ->pluck('tag')
            ->toArray();

        return response()->json([
            'id' => $entry->id()->value(),
            'form_id' => $entry->formId()->value(),
            'data' => $entry->data(),
            'tags' => $tags,
            'score' => $entry->score(),
            'created_at' => $entry->createdAt()->format('c'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $form = $this->formRepository->findById(new FormId($request->input('form_id')));

        if ($form === null) {
            return response()->json(['error' => 'Form not found'], Response::HTTP_NOT_FOUND);
        }

        $rules = [];
        foreach ($form->fields() as $field) {
            $fieldRules = [];

            if ($field->isRequired()) {
                $fieldRules[] = 'required';
            }

            switch ($field->type()->value()) {
                case 'number':
                case 'currency':
                    $fieldRules[] = 'numeric';
                    break;
                case 'email':
                    $fieldRules[] = 'email';
                    break;
                case 'date':
                    $fieldRules[] = 'date';
                    break;
                case 'boolean':
                    $fieldRules[] = 'boolean';
                    break;
            }

            $rules['data.' . $field->id()] = $fieldRules;
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $id = Uuid::generate();

        try {
            $this->createHandler->handle(
                new CreateEntryCommand(
                    id: $id,
                    formId: $request->input('form_id'),
                    userId: $request->user()->id,
                    data: $request->input('data'),
                    duration: $request->has('duration') ? (int)$request->input('duration') : null,
                )
            );
        } catch (Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => [$exception->getMessage()],
            ], Response::HTTP_BAD_REQUEST);
        }

        // TODO refactor
        if ($request->has('tags')) {
            $entryId = $id;
            foreach ($request->input('tags') as $tag) {
                EntryTagModel::query()->create([
                    'entry_id' => $entryId,
                    'tag' => $tag,
                ]);
            }
        }

        $entry = $this->entryRepository->findById(new EntryId($id));

        if (null === $entry) {
            return response()->json(['error' => 'Entry not found'], Response::HTTP_NOT_FOUND);
        }

        $response = [
            'id' => $entry->id()->value(),
            'form_id' => $entry->formId()->value(),
            'data' => $entry->data(),
            'score' => $entry->score(),
            'created_at' => $entry->createdAt()->format('c'),
        ];

        if ($form->isQuiz()) {
            $results = [];
            foreach ($form->fields() as $field) {
                $userAnswer = $entry->data()[$field->id()] ?? null;
                $correctAnswer = $field->correctAnswer();

                $isCorrect = false;
                if ($userAnswer !== null && $correctAnswer !== null) {
                    $u = is_string($userAnswer) ? trim($userAnswer) : $userAnswer;
                    $c = is_string($correctAnswer) ? trim($correctAnswer) : $correctAnswer;
                    if (is_string($u) && is_string($c)) {
                        $isCorrect = mb_strtolower($u) === mb_strtolower($c);
                    } else {
                        $isCorrect = $u == $c;
                    }
                }

                $results[] = [
                    'label' => $field->label(),
                    'user_answer' => $userAnswer,
                    'correct_answer' => $correctAnswer,
                    'is_correct' => $isCorrect,
                    'points' => $field->points(),
                ];
            }
            $response['quiz_results'] = $results;
        }

        return response()->json($response, Response::HTTP_CREATED);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $entry = $this->entryRepository->findById(new EntryId($id));

        if ($entry === null) {
            return response()->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        if ($entry->userId() !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $form = $this->formRepository->findById($entry->formId());
        if ($form === null) {
            return response()->json(['error' => 'Form not found'], Response::HTTP_NOT_FOUND);
        }

        $rules = [];
        foreach ($form->fields() as $field) {
            $fieldRules = [];

            if ($field->isRequired()) {
                $fieldRules[] = 'required';
            }

            switch ($field->type()->value()) {
                case 'number':
                case 'currency':
                    $fieldRules[] = 'numeric';
                    break;
                case 'email':
                    $fieldRules[] = 'email';
                    break;
                case 'date':
                    $fieldRules[] = 'date';
                    break;
                case 'boolean':
                    $fieldRules[] = 'boolean';
                    break;
            }

            $rules['data.' . $field->id()] = $fieldRules;
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $command = new UpdateEntryCommand(
            id: $id,
            data: $request->input('data'),
        );

        try {
            $this->updateHandler->handle($command);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Cannot update entry',
                'errors' => [$exception->getMessage()],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $updated = $this->entryRepository->findById(new EntryId($id));

        if (null === $updated) {
            return response()->json(['error' => 'Entry not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'id' => $updated->id()->value(),
            'form_id' => $updated->formId()->value(),
            'data' => $updated->data(),
            'score' => $updated->score(),
            'created_at' => $updated->createdAt()->format('c'),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $entry = $this->entryRepository->findById(new EntryId($id));

        if ($entry === null) {
            return response()->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        if ($entry->userId() !== $request->user()->id) {
            return response()->json(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $this->entryRepository->delete($entry);

        return response()->json(['message' => 'Entry deleted'], Response::HTTP_NO_CONTENT);
    }
}
