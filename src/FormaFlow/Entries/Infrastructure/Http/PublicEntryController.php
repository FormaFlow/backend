<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Infrastructure\Http;

use FormaFlow\Entries\Application\Find\FindEntryByIdQuery;
use FormaFlow\Entries\Application\Find\FindEntryByIdQueryHandler;
use FormaFlow\Forms\Application\Find\FindFormByIdQuery;
use FormaFlow\Forms\Application\Find\FindFormByIdQueryHandler;
use Illuminate\Support\Facades\App;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class PublicEntryController
{
    public function __construct(
        private FindEntryByIdQueryHandler $entryHandler,
        private FindFormByIdQueryHandler $formHandler,
    ) {
    }

    public function show(Request $request, string $id)
    {
        $lang = $request->query('lang', 'en');
        App::setLocale($lang);

        $entry = $this->entryHandler->handle(new FindEntryByIdQuery($id));

        if ($entry === null) {
            return response('Entry not found', Response::HTTP_NOT_FOUND);
        }

        $form = $this->formHandler->handle(new FindFormByIdQuery($entry->formId()->value()));

        if ($form === null) {
            return response('Form not found', Response::HTTP_NOT_FOUND);
        }

        $frontendUrl = config('app.frontend_url', 'https://app.formaflow.indeveler.ru');
        $url = "{$frontendUrl}/entries/{$id}/result?lang={$lang}";

        $title = $form->name()->value();
        $description = $form->description() ?? __('share.check_entry');

        if ($form->isQuiz() && $entry->score() !== null) {
            // Calculate total points
            $totalPoints = 0;
            foreach ($form->fields() as $field) {
                $totalPoints += $field->points();
            }
            $title = __('share.beat_score_title', [
                'score' => $entry->score(),
                'total' => $totalPoints,
                'name' => $form->name()->value()
            ]);
            $description = __('share.beat_score_desc');
        }

        return view('entries.shared', [
            'entry' => $entry,
            'form' => $form,
            'url' => $url,
            'title' => $title,
            'description' => $description,
        ]);
    }
}
