<?php

declare(strict_types=1);

namespace Tests\Unit\FormaFlow\Users\Infrastructure\Persistence;

use DateTime;
use FormaFlow\Users\Domain\UserAggregate;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use FormaFlow\Users\Infrastructure\Persistence\EloquentUserRepository;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Shared\Domain\AggregateRoot;
use Shared\Domain\UserId;
use Shared\Domain\UserName;
use Tests\TestCase;
use Throwable;

final class EloquentUserRepositoryTest extends TestCase
{

    private EloquentUserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentUserRepository();
    }

    /**
     * @throws Throwable
     */
    public function testSavesNewUserAggregate(): void
    {
        $user = new UserAggregate(
            id: new UserId('00000000-0000-0000-0000-000000000123'),
            name: new UserName('John Doe'),
        );

        $user->setEmail('john@example.com');
        $user->setPassword('secret123');

        $this->repository->save($user);

        $this->assertDatabaseHas('users', [
            'id' => '00000000-0000-0000-0000-000000000123',
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    /**
     * @throws Throwable
     */
    public function testUpdatesExistingUserAggregate(): void
    {
        $user = new UserAggregate(
            id: new UserId('00000000-0000-0000-0000-000000000456'),
            name: new UserName('Original Name'),
        );

        $user->setEmail('original@example.com');
        $user->setPassword('secret123');

        $this->repository->save($user);

        $updatedUser = UserAggregate::fromPrimitives(
            id: new UserId('00000000-0000-0000-0000-000000000456'),
            name: new UserName('Updated Name'),
            email: 'updated@example.com',
            password: 'new-password',
            emailVerifiedAt: new DateTime(),
            rememberToken: 'token123',
            createdAt: new DateTime(),
        );

        $this->repository->save($updatedUser);

        $this->assertDatabaseHas('users', [
            'id' => '00000000-0000-0000-0000-000000000456',
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'remember_token' => 'token123',
        ]);
    }

    public function testFindsUserById(): void
    {
        UserModel::factory()->create([
            'id' => '00000000-0000-0000-0000-000000000150',
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
        ]);

        $result = $this->repository->findById(new UserId('00000000-0000-0000-0000-000000000150'));

        self::assertNotNull($result);
        self::assertSame('00000000-0000-0000-0000-000000000150', $result->id()->value());
        self::assertSame('Jane Smith', $result->name()->value());
        self::assertSame('jane@example.com', $result->email());
    }

    public function testReturnsNullWhenUserNotFoundById(): void
    {
        $result = $this->repository->findById(new UserId('00000000-0000-0000-0000-000000000999'));

        self::assertNull($result);
    }

    public function testFindsByEmail(): void
    {
        UserModel::factory()->create([
            'id' => '00000000-0000-0000-0000-000000000160',
            'name' => 'Email User',
            'email' => 'unique@example.com',
        ]);

        $result = $this->repository->findByEmail('unique@example.com');

        self::assertNotNull($result);
        self::assertSame('00000000-0000-0000-0000-000000000160', $result->id()->value());
        self::assertSame('Email User', $result->name()->value());
        self::assertSame('unique@example.com', $result->email());
    }

    public function testReturnsNullWhenUserNotFoundByEmail(): void
    {
        $result = $this->repository->findByEmail('nonexistent@example.com');

        self::assertNull($result);
    }

    /**
     * @throws Throwable
     */
    public function testDeletesUserAggregate(): void
    {
        $user = new UserAggregate(
            id: new UserId('00000000-0000-0000-0000-000000000170'),
            name: new UserName('Deletable User'),
        );

        $user->setEmail('delete@example.com');
        $user->setPassword('secret123');

        $this->repository->save($user);

        $this->assertDatabaseHas('users', ['id' => '00000000-0000-0000-0000-000000000170']);

        $this->repository->delete($user);

        $this->assertDatabaseMissing('users', ['id' => '00000000-0000-0000-0000-000000000170']);
    }

    /**
     * @throws Throwable
     */
    public function testThrowsExceptionWhenSavingUnsupportedAggregate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported aggregate');

        $unsupportedAggregate = new class extends AggregateRoot {
        };

        $this->repository->save($unsupportedAggregate);
    }

    /**
     * @throws Throwable
     */
    public function testThrowsExceptionWhenDeletingUnsupportedAggregate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported aggregate');

        $unsupportedAggregate = new class extends AggregateRoot {
        };

        $this->repository->delete($unsupportedAggregate);
    }

    /**
     * @throws Throwable
     */
    public function testSavesUserWithEmailVerification(): void
    {
        $verifiedAt = new DateTime('2025-01-01 12:00:00');

        $user = UserAggregate::fromPrimitives(
            id: new UserId('00000000-0000-0000-0000-000000000070'),
            name: new UserName('Verified User'),
            email: 'verified@example.com',
            password: 'hashed_password',
            emailVerifiedAt: $verifiedAt,
            rememberToken: null,
            createdAt: new DateTime(),
        );

        $this->repository->save($user);

        $saved = $this->repository->findById(new UserId('00000000-0000-0000-0000-000000000070'));

        self::assertNotNull($saved);
        self::assertNotNull($saved->emailVerifiedAt());
        self::assertEquals(
            $verifiedAt->format('Y-m-d H:i:s'),
            $saved->emailVerifiedAt()->format('Y-m-d H:i:s')
        );
    }

    /**
     * @throws Throwable
     */
    public function testSavesUserWithRememberToken(): void
    {
        $user = new UserAggregate(
            id: new UserId('00000000-0000-0000-0000-000000000080'),
            name: new UserName('Token User'),
        );

        $user->setEmail('token@example.com');
        $user->setPassword('secret123');
        $user->setRememberToken('remember_me_token_123');

        $this->repository->save($user);

        $saved = $this->repository->findById(new UserId('00000000-0000-0000-0000-000000000080'));

        self::assertNotNull($saved);
        self::assertSame('remember_me_token_123', $saved->rememberToken());
    }

    /**
     * @throws Throwable
     */
    public function testPreservesHashedPassword(): void
    {
        $password = 'secret123';
        $hashed = Hash::make($password);

        $user = UserAggregate::fromPrimitives(
            id: new UserId('00000000-0000-0000-0000-000000000090'),
            name: new UserName('Password User'),
            email: 'password@example.com',
            password: $hashed,
            emailVerifiedAt: null,
            rememberToken: null,
            createdAt: new DateTime(),
        );

        $this->repository->save($user);

        $saved = $this->repository->findById(new UserId('00000000-0000-0000-0000-000000000090'));

        self::assertNotNull($saved);
        self::assertSame($hashed, $saved->password());
    }

    /**
     * @throws Throwable
     */
    public function testDoesNotDeleteNonExistentUser(): void
    {
        $user = new UserAggregate(
            id: new UserId('00000000-0000-0000-0000-000000000100'),
            name: new UserName('Never Saved'),
        );

        $this->repository->delete($user);

        $this->assertDatabaseMissing('users', ['id' => '00000000-0000-0000-0000-000000000100']);
    }
}
