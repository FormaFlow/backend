<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Infrastructure\Persistence;

use FormaFlow\Entries\Domain\EntryAggregate;
use FormaFlow\Entries\Domain\EntryId;
use FormaFlow\Entries\Domain\EntryRepository;
use FormaFlow\Entries\Infrastructure\Persistence\Eloquent\EntryModel;
use FormaFlow\Forms\Domain\FormId;
use FormaFlow\Forms\Domain\FormRepository;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Shared\Domain\AggregateRoot;

final class EloquentEntryRepository implements EntryRepository
{
    public function __construct(private readonly FormRepository $formRepository)
    {
    }
    public function save(EntryAggregate|AggregateRoot $aggregate): void
    {
        if (!$aggregate instanceof EntryAggregate) {
            throw new InvalidArgumentException('Unsupported aggregate');
        }

        EntryModel::query()->updateOrCreate(
            ['id' => $aggregate->id()->value()],
            [
                'form_id' => $aggregate->formId()->value(),
                'user_id' => $aggregate->userId(),
                'data' => $aggregate->data(),
            ],
        );
    }

    public function delete(EntryAggregate|AggregateRoot $aggregate): void
    {
        if (!$aggregate instanceof EntryAggregate) {
            throw new InvalidArgumentException('Unsupported aggregate');
        }

        EntryModel::query()->where('id', $aggregate->id()->value())->delete();
    }

    public function findById(EntryId $id): ?EntryAggregate
    {
        $model = EntryModel::query()->find($id->value());

        if ($model === null) {
            return null;
        }

        return EntryAggregate::fromPrimitives(
            id: new EntryId($model->id),
            formId: new FormId($model->form_id),
            userId: $model->user_id,
            data: $model->data,
            createdAt: $model->created_at,
        );
    }

    public function findByUserId(string $userId, array $filters = [], int $limit = 15, int $offset = 0): array
    {
        $query = EntryModel::query()->where('user_id', $userId);

        if (isset($filters['form_id'])) {
            $query->where('form_id', $filters['form_id']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        if (isset($filters['tags'])) {
            $tags = is_array($filters['tags']) ? $filters['tags'] : [$filters['tags']];
            $query->whereHas('tags', function ($q) use ($tags) {
                $q->whereIn('tag', $tags);
            });
        }

        if (isset($filters['sort_by'])) {
            $sortOrder = $filters['sort_order'] ?? 'asc';

            if (str_starts_with($filters['sort_by'], 'data.')) {
                $field = str_replace('data.', '', $filters['sort_by']);
                $query->orderByRaw("json_extract(data, '$.{$field}') {$sortOrder}");
            } else {
                $query->orderBy($filters['sort_by'], $sortOrder);
            }
        }
        else {
            $query->orderBy('created_at', 'desc');
        }

        $total = $query->count();

        $models = $query
            ->limit($limit)
            ->offset($offset)
            ->get();

        $entries = $models->map(fn($model) => EntryAggregate::fromPrimitives(
            id: new EntryId($model->id),
            formId: new FormId($model->form_id),
            userId: $model->user_id,
            data: $model->data,
            createdAt: $model->created_at,
        ))->toArray();
        
        return [$entries, $total];
    }

    public function findByFormId(string $formId, int $limit = 15, int $offset = 0): array
    {
        $models = EntryModel::query()->where('form_id', $formId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return $models->map(fn($model) => EntryAggregate::fromPrimitives(
            id: new EntryId($model->id),
            formId: new FormId($model->form_id),
            userId: $model->user_id,
            data: $model->data,
            createdAt: $model->created_at,
        ))->toArray();
    }

    public function getSumOfNumericFieldsByDateRange(
        FormId $formId,
        string $userId,
        \DateTimeImmutable $startDate,
        ?\DateTimeImmutable $endDate = null
    ): array {
        $form = $this->formRepository->findById($formId);

        if (null === $form) {
            return [];
        }

        $numericFields = [];
        foreach ($form->fields() as $field) {
            if (in_array($field->type()->value(), ['number', 'currency'])) {
                $numericFields[] = $field->name();
            }
        }

        if (empty($numericFields)) {
            return [];
        }

        $query = EntryModel::query()
            ->where('form_id', $formId->value())
            ->where('user_id', $userId);

        if ($endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        } else {
            $query->whereDate('created_at', $startDate);
        }

        $results = [];
        foreach ($numericFields as $field) {
            $sum = (clone $query)->sum(DB::raw("CAST(json_extract(data, '$.{$field}') AS DECIMAL(10, 2))"));
            $results[] = [
                'field' => $field,
                'total_sum' => (float)$sum,
            ];
        }

        return $results;
    }
}
