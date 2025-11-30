<?php

declare(strict_types=1);

namespace FormaFlow\Users\Infrastructure\Persistence;

use FormaFlow\Users\Domain\UserAggregate;
use FormaFlow\Users\Domain\UserRepository;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Shared\Domain\AggregateRoot;
use Shared\Domain\UserId;
use Shared\Domain\UserName;
use Throwable;

final class EloquentUserRepository implements UserRepository
{
    /**
     * @throws Throwable
     */
    public function save(UserAggregate|AggregateRoot $aggregate): void
    {
        if (!$aggregate instanceof UserAggregate) {
            throw new InvalidArgumentException('Unsupported aggregate');
        }

        DB::transaction(static function () use ($aggregate): void {
            UserModel::query()->updateOrCreate(
                ['id' => $aggregate->id()->value()],
                [
                    'name' => $aggregate->name()->value(),
                    'email' => $aggregate->email(),
                    'password' => $aggregate->password(),
                    'email_verified_at' => $aggregate->emailVerifiedAt(),
                    'remember_token' => $aggregate->rememberToken(),
                ]
            );
        });
    }

    /**
     * @throws Throwable
     */
    public function delete(UserAggregate|AggregateRoot $aggregate): void
    {
        if (!$aggregate instanceof UserAggregate) {
            throw new InvalidArgumentException('Unsupported aggregate');
        }

        DB::transaction(static function () use ($aggregate): void {
            $model = UserModel::query()->find($aggregate->id()->value());
            if ($model) {
                $model->delete();
            }
        });
    }

    public function findById(UserId $id): ?UserAggregate
    {
        $model = UserModel::query()->find($id->value());
        if (!$model) {
            return null;
        }

        return UserAggregate::fromPrimitives(
            id: new UserId((string)$model->id),
            name: new UserName((string)$model->name),
            email: $model->email,
            password: $model->password,
            emailVerifiedAt: $model->email_verified_at,
            rememberToken: $model->remember_token,
            createdAt: $model->created_at,
        );
    }

    public function findByEmail(string $email): ?UserAggregate
    {
        $model = UserModel::query()->where('email', $email)->first();
        if (!$model) {
            return null;
        }

        return UserAggregate::fromPrimitives(
            id: new UserId((string)$model->id),
            name: new UserName((string)$model->name),
            email: $model->email,
            password: $model->password,
            emailVerifiedAt: $model->email_verified_at,
            rememberToken: $model->remember_token,
            createdAt: $model->created_at,
        );
    }
}
