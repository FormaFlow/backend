<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Infrastructure\Persistence;

use FormaFlow\Forms\Domain\Field;
use FormaFlow\Forms\Domain\FieldType;
use FormaFlow\Forms\Domain\FormAggregate;
use FormaFlow\Forms\Domain\FormId;
use FormaFlow\Forms\Domain\FormName;
use FormaFlow\Forms\Domain\FormRepository;
use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormModel;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Shared\Domain\AggregateRoot;
use Throwable;

final class EloquentFormRepository implements FormRepository
{
    /**
     * @throws Throwable
     */
    public function save(FormAggregate|AggregateRoot $aggregate): void
    {
        if (!$aggregate instanceof FormAggregate) {
            throw new InvalidArgumentException('Unsupported aggregate');
        }

        DB::transaction(static function () use ($aggregate): void {
            $form = FormModel::query()->updateOrCreate(
                ['id' => $aggregate->id()->value()],
                [
                    'user_id' => $aggregate->userId(),
                    'name' => $aggregate->name()->value(),
                    'description' => $aggregate->description(),
                    'published' => $aggregate->isPublished(),
                    'version' => $aggregate->getVersion(),
                    'is_quiz' => $aggregate->isQuiz(),
                    'single_submission' => $aggregate->isSingleSubmission(),
                ]
            );

            $existingIds = $form->fields()->pluck('id')->all();
            $kept = [];

            foreach ($aggregate->fields() as $f) {
                $kept[] = $f->id();

                $form->fields()->updateOrCreate(
                    ['id' => $f->id()],
                    [
                        'form_id' => $form->id,
                        'label' => $f->label(),
                        'type' => $f->type()->value(),
                        'required' => $f->isRequired(),
                        'options' => $f->options(),
                        'unit' => $f->unit(),
                        'category' => $f->category(),
                        'order' => $f->order(),
                        'correct_answer' => $f->correctAnswer(),
                        'points' => $f->points(),
                    ]
                );
            }

            $toDelete = array_diff($existingIds, $kept);
            if ($toDelete) {
                $form->fields()->whereIn('id', $toDelete)->delete();
            }
        });
    }

    /**
     * @throws Throwable
     */
    public function delete(FormAggregate|AggregateRoot $aggregate): void
    {
        if (!$aggregate instanceof FormAggregate) {
            throw new InvalidArgumentException('Unsupported aggregate');
        }

        DB::transaction(static function () use ($aggregate): void {
            $model = FormModel::query()->find($aggregate->id()->value());
            if ($model) {
                $model->fields()->delete();
                $model->delete();
            }
        });
    }

    public function findById(FormId $id): ?FormAggregate
    {
        $model = FormModel::query()->with('fields')->find($id->value());
        if (!$model) {
            return null;
        }

        $fields = [];
        foreach ($model->fields as $fm) {
            $fields[] = new Field(
                id: (string)$fm->id,
                label: (string)$fm->label,
                type: new FieldType((string)$fm->type),
                required: (bool)$fm->required,
                options: $fm->options ? (array)$fm->options : null,
                unit: $fm->unit,
                category: $fm->category,
                order: (int)$fm->order,
                correctAnswer: $fm->correct_answer,
                points: (int)$fm->points,
            );
        }

        return FormAggregate::fromPrimitives(
            id: new FormId((string)$model->id),
            userId: (string)$model->user_id,
            name: new FormName((string)$model->name),
            description: $model->description,
            published: (bool)$model->published,
            version: (int)$model->version,
            createdAt: $model->created_at,
            fields: $fields,
            isQuiz: (bool)$model->is_quiz,
            singleSubmission: (bool)$model->single_submission,
        );
    }

    /** @return FormAggregate[] */
    public function findByUserId(string $userId): array
    {
        $models = FormModel::query()
            ->with('fields')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get();

        $result = [];
        foreach ($models as $model) {
            $fields = [];
            foreach ($model->fields as $fm) {
                $fields[] = new Field(
                    id: (string)$fm->id,
                    label: (string)$fm->label,
                    type: new FieldType((string)$fm->type),
                    required: (bool)$fm->required,
                    options: $fm->options ? (array)$fm->options : null,
                    unit: $fm->unit,
                    category: $fm->category,
                    order: (int)$fm->order,
                    correctAnswer: $fm->correct_answer,
                    points: (int)$fm->points,
                );
            }

            $result[] = FormAggregate::fromPrimitives(
                id: new FormId((string)$model->id),
                userId: (string)$model->user_id,
                name: new FormName((string)$model->name),
                description: $model->description,
                published: (bool)$model->published,
                version: (int)$model->version,
                createdAt: $model->created_at,
                fields: $fields,
                isQuiz: (bool)$model->is_quiz,
                singleSubmission: (bool)$model->single_submission,
            );
        }

        return $result;
    }
}
