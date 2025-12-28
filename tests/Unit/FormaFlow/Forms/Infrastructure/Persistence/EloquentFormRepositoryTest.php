<?php

declare(strict_types=1);

namespace Tests\Unit\FormaFlow\Forms\Infrastructure\Persistence;

use DateTime;
use DB;
use FormaFlow\Forms\Domain\Field;
use FormaFlow\Forms\Domain\FieldType;
use FormaFlow\Forms\Domain\FormAggregate;
use FormaFlow\Forms\Domain\FormId;
use FormaFlow\Forms\Domain\FormName;
use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormModel;
use FormaFlow\Forms\Infrastructure\Persistence\EloquentFormRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use InvalidArgumentException;
use Shared\Domain\AggregateRoot;
use Throwable;

final class EloquentFormRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentFormRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentFormRepository();
    }

    /**
     * @throws Throwable
     */
    public function testSavesNewFormAggregate(): void
    {
        $form = new FormAggregate(
            id: new FormId('test-id-123'),
            userId: 'user-1',
            name: new FormName('Test Form'),
            description: 'Test description',
        );

        $this->repository->save($form);

        $this->assertDatabaseHas('forms', [
            'id' => 'test-id-123',
            'user_id' => 'user-1',
            'name' => 'Test Form',
            'description' => 'Test description',
            'published' => false,
            'version' => 1,
        ]);
    }

    /**
     * @throws Throwable
     */
    public function testUpdatesExistingFormAggregate(): void
    {
        $form = new FormAggregate(
            id: new FormId('test-id-456'),
            userId: 'user-2',
            name: new FormName('Original Name'),
        );

        $this->repository->save($form);

        $updatedForm = FormAggregate::fromPrimitives(
            id: new FormId('test-id-456'),
            userId: 'user-2',
            name: new FormName('Updated Name'),
            description: 'Updated description',
            published: true,
            version: 2,
            createdAt: new DateTime(),
            fields: [],
        );

        $this->repository->save($updatedForm);

        $this->assertDatabaseHas('forms', [
            'id' => 'test-id-456',
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'published' => true,
            'version' => 2,
        ]);
    }

    /**
     * @throws Throwable
     */
    public function testSavesFormWithFields(): void
    {
        $form = new FormAggregate(
            id: new FormId('form-with-fields'),
            userId: 'user-3',
            name: new FormName('Form with Fields'),
        );

        $field = new Field(
            id: 'field-1',
            label: 'Email Address',
            type: new FieldType('email'),
            required: true,
        );

        $form->addField($field);
        $this->repository->save($form);

        $this->assertDatabaseHas('forms', [
            'id' => 'form-with-fields',
        ]);

        $this->assertDatabaseHas('form_fields', [
            'id' => 'field-1',
            'form_id' => 'form-with-fields',
            'label' => 'Email Address',
            'type' => 'email',
            'required' => true,
        ]);
    }

    /**
     * @throws Throwable
     */
    public function testDeletesRemovedFieldsOnUpdate(): void
    {
        $form = new FormAggregate(
            id: new FormId('form-delete-fields'),
            userId: 'user-4',
            name: new FormName('Test Form'),
        );

        $field1 = new Field(
            id: 'field-to-keep',
            label: 'Name',
            type: new FieldType('text'),
        );

        $field2 = new Field(
            id: 'field-to-delete',
            label: 'Age',
            type: new FieldType('number'),
        );

        $form->addField($field1);
        $form->addField($field2);
        $this->repository->save($form);

        $this->assertDatabaseHas('form_fields', ['id' => 'field-to-delete']);

        $updatedForm = FormAggregate::fromPrimitives(
            id: new FormId('form-delete-fields'),
            userId: 'user-4',
            name: new FormName('Test Form'),
            description: null,
            published: false,
            version: 1,
            createdAt: new DateTime(),
            fields: [$field1],
        );

        $this->repository->save($updatedForm);

        $this->assertDatabaseHas('form_fields', ['id' => 'field-to-keep']);
        $this->assertDatabaseMissing('form_fields', ['id' => 'field-to-delete']);
    }

    public function testFindsFormById(): void
    {
        FormModel::factory()->create([
            'id' => 'findable-id',
            'user_id' => 'user-5',
            'name' => 'Findable Form',
            'description' => 'Description',
            'published' => false,
            'version' => 1,
        ]);

        $result = $this->repository->findById(new FormId('findable-id'));

        self::assertNotNull($result);
        self::assertSame('findable-id', $result->id()->value());
        self::assertSame('user-5', $result->userId());
        self::assertSame('Findable Form', $result->name()->value());
        self::assertSame('Description', $result->description());
        self::assertFalse($result->isPublished());
        self::assertSame(1, $result->getVersion());
    }

    public function testReturnsNullWhenFormNotFound(): void
    {
        $result = $this->repository->findById(new FormId('non-existent-id'));

        self::assertNull($result);
    }

    public function testFindsFormByIdWithFields(): void
    {
        $formModel = FormModel::factory()->create([
            'id' => 'form-with-fields-id',
            'user_id' => 'user-6',
            'name' => 'Form with Fields',
        ]);

        DB::table('form_fields')->insert([
            'id' => 'field-1',
            'form_id' => $formModel->id,
            'label' => 'Username',
            'type' => 'text',
            'required' => true,
            'unit' => null,
            'options' => null,
            'category' => null,
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->repository->findById(new FormId('form-with-fields-id'));

        self::assertNotNull($result);
        self::assertCount(1, $result->fields());
        self::assertSame('Username', $result->fields()[0]->label());
        self::assertSame('text', $result->fields()[0]->type()->value());
        self::assertTrue($result->fields()[0]->isRequired());
    }

    public function testFindsByUserId(): void
    {
        FormModel::factory()->create([
            'id' => 'form-1',
            'user_id' => 'target-user',
            'name' => 'Form 1',
        ]);

        FormModel::factory()->create([
            'id' => 'form-2',
            'user_id' => 'target-user',
            'name' => 'Form 2',
        ]);

        FormModel::factory()->create([
            'id' => 'form-3',
            'user_id' => 'other-user',
            'name' => 'Other Form',
        ]);

        $results = $this->repository->findByUserId('target-user');

        self::assertCount(2, $results);
        self::assertSame('form-1', $results[0]->id()->value());
        self::assertSame('form-2', $results[1]->id()->value());
    }

    public function testReturnsEmptyArrayWhenNoFormsForUser(): void
    {
        $results = $this->repository->findByUserId('user-with-no-forms');

        self::assertIsArray($results);
        self::assertEmpty($results);
    }

    /**
     * @throws Throwable
     */
    public function testDeletesFormAggregate(): void
    {
        $form = new FormAggregate(
            id: new FormId('form-to-delete'),
            userId: 'user-7',
            name: new FormName('Deletable Form'),
        );

        $this->repository->save($form);
        $this->assertDatabaseHas('forms', ['id' => 'form-to-delete']);

        $this->repository->delete($form);

        $this->assertDatabaseMissing('forms', ['id' => 'form-to-delete', 'deleted_at' => null]);
    }

    /**
     * @throws Throwable
     */
    public function testDeletesFormCascadesFields(): void
    {
        $form = new FormAggregate(
            id: new FormId('form-cascade'),
            userId: 'user-8',
            name: new FormName('Form with Cascade'),
        );

        $field = new Field(
            id: 'field-cascade',
            label: 'Test',
            type: new FieldType('text'),
        );

        $form->addField($field);
        $this->repository->save($form);

        $this->assertDatabaseHas('form_fields', ['id' => 'field-cascade']);

        $this->repository->delete($form);

        $this->assertDatabaseMissing('form_fields', ['id' => 'field-cascade']);
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
    public function testSavesFieldWithAllProperties(): void
    {
        $form = new FormAggregate(
            id: new FormId('form-complex-field'),
            userId: 'user-9',
            name: new FormName('Complex Field Form'),
        );

        $field = new Field(
            id: 'complex-field',
            label: 'Price',
            type: new FieldType('currency'),
            required: true,
            options: ['min' => 0, 'max' => 1000],
            unit: 'USD',
            category: 'financial',
            order: 5,
        );

        $form->addField($field);
        $this->repository->save($form);

        $this->assertDatabaseHas('form_fields', [
            'id' => 'complex-field',
            'label' => 'Price',
            'type' => 'currency',
            'required' => true,
            'unit' => 'USD',
            'category' => 'financial',
            'order' => 5,
        ]);

        $savedForm = $this->repository->findById(new FormId('form-complex-field'));
        self::assertNotNull($savedForm);
        self::assertSame(['min' => 0, 'max' => 1000], $savedForm->fields()[0]->options());
    }
}
