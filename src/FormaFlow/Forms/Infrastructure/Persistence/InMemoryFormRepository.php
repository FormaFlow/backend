<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Infrastructure\Persistence;

use FormaFlow\Forms\Domain\FormAggregate;
use FormaFlow\Forms\Domain\FormId;
use FormaFlow\Forms\Domain\FormRepository;
use FormaFlow\Forms\Domain\FormSummary;
use Shared\Domain\AggregateRoot;

final class InMemoryFormRepository implements FormRepository
{
    /** @var FormAggregate[] */
    private array $forms = [];

    public function save(AggregateRoot $aggregate): void
    {
        $this->forms[$aggregate->id()->value()] = $aggregate;
    }

    public function delete(FormAggregate|AggregateRoot $aggregate): void
    {
        unset($this->forms[$aggregate->id()->value()]);
    }

    public function findById(FormId $id): ?FormAggregate
    {
        return $this->forms[$id->value()] ?? null;
    }

    /** @return FormAggregate[] */
    public function findByUserId(string $userId, ?bool $isQuiz = null): array
    {
        return array_filter(
            $this->forms,
            static fn(FormAggregate $form): bool => $form->userId() === $userId
        );
    }

    /** @return array{0: FormSummary[], 1: int} */
    public function findSummariesByUserId(
        string $userId,
        ?bool $isQuiz,
        ?string $search,
        int $limit,
        int $offset,
    ): array {
        $forms = array_values(array_filter(
            $this->forms,
            static function (FormAggregate $form) use ($userId, $isQuiz, $search): bool {
                if ($form->userId() !== $userId || ($isQuiz !== null && $form->isQuiz() !== $isQuiz)) {
                    return false;
                }

                if ($search === null || $search === '') {
                    return true;
                }

                $haystack = mb_strtolower($form->name()->value() . ' ' . ($form->description() ?? ''));
                return str_contains($haystack, mb_strtolower($search));
            }
        ));

        usort(
            $forms,
            static fn(FormAggregate $left, FormAggregate $right): int => $right->createdAt() <=> $left->createdAt()
        );

        $total = count($forms);
        $summaries = array_map(
            static fn(FormAggregate $form): FormSummary => new FormSummary(
                id: $form->id()->value(),
                userId: $form->userId(),
                name: $form->name()->value(),
                description: $form->description(),
                published: $form->isPublished(),
                isQuiz: $form->isQuiz(),
                singleSubmission: $form->isSingleSubmission(),
                quickEntryFavorite: $form->isQuickEntryFavorite(),
                fieldsCount: count($form->fields()),
                entriesCount: 0,
                createdAt: $form->createdAt(),
                updatedAt: $form->createdAt(),
            ),
            array_slice($forms, $offset, $limit)
        );

        return [$summaries, $total];
    }
}
