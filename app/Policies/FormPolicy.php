<?php

declare(strict_types=1);

namespace App\Policies;

use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormModel;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;

final class FormPolicy
{
    public function update(UserModel $user, FormModel $form): bool
    {
        return $user->id === $form->user_id;
    }

    public function delete(UserModel $user, FormModel $form): bool
    {
        return $user->id === $form->user_id;
    }

    public function publish(UserModel $user, FormModel $form): bool
    {
        return $user->id === $form->user_id;
    }

    public function addField(UserModel $user, FormModel $form): bool
    {
        return $user->id === $form->user_id;
    }
}
