<?php

declare(strict_types=1);

namespace FormaFlow\Users\Domain;

use DateTime;
use Shared\Domain\AggregateRoot;
use Shared\Domain\UserId;
use Shared\Domain\UserName;

final class UserAggregate extends AggregateRoot
{
    private ?string $email = null;
    private ?string $password = null;
    private ?DateTime $emailVerifiedAt = null;
    private ?string $rememberToken = null;
    private DateTime $createdAt;
    private string $timezone = 'Europe/Moscow';

    public function __construct(
        private readonly UserId $id,
        private readonly UserName $name,
        DateTime $createdAt = new DateTime()
    ) {
        $this->createdAt = $createdAt;
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function name(): UserName
    {
        return $this->name;
    }

    public function email(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function password(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = password_hash($password, PASSWORD_DEFAULT);
    }

    public function emailVerifiedAt(): ?DateTime
    {
        return $this->emailVerifiedAt;
    }

    public function setEmailVerifiedAt(?DateTime $date = null): void
    {
        $this->emailVerifiedAt = $date ?? new DateTime();
    }

    public function rememberToken(): ?string
    {
        return $this->rememberToken;
    }

    public function setRememberToken(?string $token): void
    {
        $this->rememberToken = $token;
    }

    public function createdAt(): DateTime
    {
        return $this->createdAt;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    public function setTimezone(string $timezone): void
    {
        $this->timezone = $timezone;
    }

    public static function fromPrimitives(
        UserId $id,
        UserName $name,
        ?string $email,
        ?string $password,
        ?DateTime $emailVerifiedAt,
        ?string $rememberToken,
        DateTime $createdAt,
        string $timezone = 'Europe/Moscow'
    ): self {
        $self = new self($id, $name, $createdAt);
        $self->email = $email;
        $self->password = $password;
        $self->emailVerifiedAt = $emailVerifiedAt;
        $self->rememberToken = $rememberToken;
        $self->timezone = $timezone;
        return $self;
    }
}
