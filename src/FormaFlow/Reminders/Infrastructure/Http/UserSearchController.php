<?php

declare(strict_types=1);

namespace FormaFlow\Reminders\Infrastructure\Http;

use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class UserSearchController
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|min:2|max:100',
        ]);
        $pattern = '%' . mb_strtolower(trim($validated['query'])) . '%';

        $users = UserModel::query()
            ->where('id', '!=', $request->user()->id)
            ->where(static function ($query) use ($pattern): void {
                $query
                    ->whereRaw('LOWER(name) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$pattern]);
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email']);

        return response()->json(['users' => $users]);
    }
}
