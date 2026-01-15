<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Infrastructure\Http;

use FormaFlow\Forms\Application\Find\FindFormByIdQuery;
use FormaFlow\Forms\Application\Find\FindFormByIdQueryHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class PublicFormController
{
    public function __construct(
        private FindFormByIdQueryHandler $handler,
    ) {
    }

    public function show(Request $request, string $id)
    {
        $query = new FindFormByIdQuery($id);
        $form = $this->handler->handle($query);

        if ($form === null || !$form->isPublished()) {
            return response('Form not found or not published', Response::HTTP_NOT_FOUND);
        }

        $frontendUrl = config('app.frontend_url', 'https://app.formaflow.indeveler.ru');
        $url = "{$frontendUrl}/entries/create?form_id={$id}";

        return view('forms.shared', [
            'form' => $form,
            'url' => $url,
        ]);
    }
}
