<?php
// src/FormaFlow/Entries/Infrastructure/Persistence/EloquentEntryRepository.php

declare(strict_types=1);

namespace FormaFlow\Entries\Infrastructure\Persistence;

use FormaFlow\Entries\Domain\EntryAggregate;
use FormaFlow\Entries\Domain\EntryId;
use FormaFlow\Entries\Domain\EntryRepository;
use FormaFlow\Entries\Infrastructure\Persistence\Eloquent\EntryModel;
use FormaFlow\Forms\Domain\FormId;
use InvalidArgumentException;
use Shared\Domain\AggregateRoot;

final class EloquentEntryRepository implements EntryRepository
{
    public function save(AggregateRoot|EntryAggregate $aggregate): void
    {
        if (!$aggregate instanceof EntryAggregate) {
            throw new InvalidArgumentException('Unsupported aggregate');
        }

        EntryModel::updateOrCreate(
            ['id' => $aggregate->id()->value()],
            [
                'form_id' => $aggregate->formId()->value(),
                'user_id' => $aggregate->userId(),
                'data' => $aggregate->data(),
            ]
        );
    }

    public function delete(AggregateRoot|EntryAggregate $aggregate): void
    {
        if (!$aggregate instanceof EntryAggregate) {
            throw new InvalidArgumentException('Unsupported aggregate');
        }

        EntryModel::where('id', $aggregate->id()->value())->delete();
    }

    public function findById(EntryId $id): ?EntryAggregate
    {
        $model = EntryModel::find($id->value());

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
        $query = EntryModel::where('user_id', $userId);

        // Фильтр по форме
        if (isset($filters['form_id'])) {
            $query->where('form_id', $filters['form_id']);
        }

        // Фильтр по диапазону дат (created_at)
        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        // Фильтр по тегам
        if (isset($filters['tags'])) {
            $tags = is_array($filters['tags']) ? $filters['tags'] : [$filters['tags']];
            $query->whereHas('tags', function ($q) use ($tags) {
                $q->whereIn('tag', $tags);
            });
        }

        // Сортировка
        if (isset($filters['sort_by'])) {
            $sortOrder = $filters['sort_order'] ?? 'asc';

            // Если сортируем по полю в JSON data
            if (str_starts_with($filters['sort_by'], 'data.')) {
                $field = str_replace('data.', '', $filters['sort_by']);
                $query->orderByRaw("json_extract(data, '$.{$field}') {$sortOrder}");
            } else {
                $query->orderBy($filters['sort_by'], $sortOrder);
            }
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $models = $query
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

    public function findByFormId(string $formId, int $limit = 15, int $offset = 0): array
    {
        $models = EntryModel::where('form_id', $formId)
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
}
