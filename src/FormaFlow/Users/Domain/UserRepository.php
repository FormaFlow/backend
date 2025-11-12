<?php

declare(strict_types=1);

namespace FormaFlow\Users\Domain;

use Shared\Domain\AggregateRoot;
use Shared\Domain\Repository;
use Shared\Domain\UserId;

interface UserRepository extends Repository
{
    public function findById(UserId $id): ?UserAggregate;

    public function findByEmail(string $email): ?UserAggregate;

    public function save(UserAggregate|AggregateRoot $aggregate): void;

    public function delete(UserAggregate|AggregateRoot $aggregate): void;
}
