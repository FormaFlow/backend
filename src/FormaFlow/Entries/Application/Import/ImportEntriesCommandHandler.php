<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Application\Import;

use DateTime;
use FormaFlow\Entries\Domain\EntryAggregate;
use FormaFlow\Entries\Domain\EntryId;
use FormaFlow\Entries\Domain\EntryRepository;
use FormaFlow\Forms\Domain\FormId;
use FormaFlow\Forms\Domain\FormRepository;
use RuntimeException;
use Shared\Infrastructure\Uuid;

final readonly class ImportEntriesCommandHandler
{
    public function __construct(
        private FormRepository $formRepository,
        private EntryRepository $entryRepository,
    ) {
    }

    /**
     * @return array{imported: int, errors: string[]}
     */
    public function handle(ImportEntriesCommand $command): array
    {
        $formId = new FormId($command->formId);
        $form = $this->formRepository->findById($formId);

        if ($form === null) {
            throw new RuntimeException('Form not found');
        }

        if ($form->userId() !== $command->userId) {
            throw new RuntimeException('Forbidden');
        }

        if (!$form->isPublished()) {
            throw new RuntimeException('Form must be published');
        }

        $lines = explode("\n", trim($command->csvData));
        if (empty($lines)) {
            return ['imported' => 0, 'errors' => ['CSV is empty']];
        }

        $headers = str_getcsv(array_shift($lines), $command->delimiter);

        $imported = 0;
        $errors = [];

        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $values = str_getcsv($line, $command->delimiter);

            // Handle potentially mismatched header/value counts
            if (count($values) !== count($headers)) {
                $errors[] = "Row " . ($index + 2) . ": Column count mismatch";
                continue;
            }

            $csvRow = array_combine($headers, $values);
            $entryData = [];
            $valid = true;

            foreach ($form->fields() as $field) {
                $val = $csvRow[$field->label()] ?? null;

                if ($field->isRequired() && (is_null($val) || $val === '')) {
                    $errors[] = "Row " . ($index + 2) . ": Missing required field '{$field->label()}'";
                    $valid = false;
                    break;
                }

                if (isset($val) && $val !== '') {
                    switch ($field->type()->value()) {
                        case 'number':
                        case 'currency':
                            if (!is_numeric($val)) {
                                $errors[] = "Row " . ($index + 2) . ": Invalid number for '{$field->label()}'";
                                $valid = false;
                            }
                            break;
                    }
                    if ($valid) {
                        $entryData[$field->id()] = $val;
                    }
                }
            }

            if ($valid) {
                $entry = new EntryAggregate(
                    id: new EntryId(Uuid::generate()),
                    formId: $formId,
                    userId: $command->userId,
                    data: $entryData,
                    createdAt: new DateTime()
                );

                $this->entryRepository->save($entry);
                $imported++;
            }
        }

        return [
            'imported' => $imported,
            'errors' => $errors,
        ];
    }
}
