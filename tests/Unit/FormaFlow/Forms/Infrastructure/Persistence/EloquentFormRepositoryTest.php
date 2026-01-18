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
use Shared\Domain\UserId;
use Tests\TestCase;
use InvalidArgumentException;
use Shared\Domain\AggregateRoot;
use Throwable;

final class EloquentFormRepositoryTest extends TestCase
{

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
            id: new FormId('00000000-0000-0000-0000-000000000123'),
            userId: '00000000-0000-0000-0000-000000000123',
            name: new FormName('Test Form'),
        );

        $this->repository->save($form);

        $this->assertDatabaseHas('forms', [
            'id' => '00000000-0000-0000-0000-000000000123',
            'name' => 'Test Form',
        ]);
    }

    /**
     * @throws Throwable
     */
    public function testUpdatesExistingFormAggregate(): void
    {
        // Setup initial state
        $form = new FormAggregate(
            id: new FormId('00000000-0000-0000-0000-000000000456'),
            userId: '00000000-0000-0000-0000-000000000456',
            name: new FormName('Original Name'),
        );
        $this->repository->save($form);

        // Update
        $form->update(new FormName('Updated Name'), 'New Description');
        $this->repository->save($form);

        $this->assertDatabaseHas('forms', [
            'id' => '00000000-0000-0000-0000-000000000456',
            'name' => 'Updated Name',
            'description' => 'New Description',
            'version' => 2
        ]);
    }

    /**
     * @throws Throwable
     */
    public function testSavesFormWithFields(): void
    {
        $form = new FormAggregate(
            id: new FormId('00000000-0000-0000-0000-000000000001'),
            userId: '00000000-0000-0000-0000-000000000005',
            name: new FormName('Form with Fields'),
        );

        $form->addField(new Field(
            id: '00000000-0000-0000-0000-000000000130',
            label: 'Field 1',
            type: new FieldType('text'),
        ));

        $this->repository->save($form);

        $this->assertDatabaseHas('forms', ['id' => '00000000-0000-0000-0000-000000000001']);
        $this->assertDatabaseHas('form_fields', [
            'id' => '00000000-0000-0000-0000-000000000130',
            'form_id' => '00000000-0000-0000-0000-000000000001',
            'label' => 'Field 1'
        ]);
    }

    /**
     * @throws Throwable
     */
    public function testDeletesRemovedFieldsOnUpdate(): void
    {
        $form = new FormAggregate(
            id: new FormId('00000000-0000-0000-0000-000000000002'),
            userId: '00000000-0000-0000-0000-000000000004',
            name: new FormName('Test Form'),
        );

        $field1 = new Field(
            id: '00000000-0000-0000-0000-000000000001',
            label: 'Name',
            type: new FieldType('text'),
        );

        $field2 = new Field(
            id: '00000000-0000-0000-0000-000000000002',
            label: 'Age',
            type: new FieldType('number'),
        );

        $form->addField($field1);
        $form->addField($field2);
        $this->repository->save($form);

        $this->assertDatabaseHas('form_fields', ['id' => '00000000-0000-0000-0000-000000000002']);

        $updatedForm = FormAggregate::fromPrimitives(
            id: new FormId('00000000-0000-0000-0000-000000000002'),
            userId: '00000000-0000-0000-0000-000000000004',
            name: new FormName('Test Form'),
            description: null,
            published: false,
            version: 1,
            createdAt: new DateTime(),
            fields: [$field1],
        );

        $this->repository->save($updatedForm);

        $this->assertDatabaseHas('form_fields', ['id' => '00000000-0000-0000-0000-000000000001']);
        $this->assertDatabaseMissing('form_fields', ['id' => '00000000-0000-0000-0000-000000000002']);
    }

    public function testFindsFormById(): void
    {
        FormModel::factory()->create([
            'id' => '00000000-0000-0000-0000-000000000003',
            'user_id' => '00000000-0000-0000-0000-000000000005',
            'name' => 'Findable Form',
            'description' => 'Description',
            'published' => false,
            'version' => 1,
        ]);

        $result = $this->repository->findById(new FormId('00000000-0000-0000-0000-000000000003'));

        self::assertNotNull($result);
        self::assertSame('00000000-0000-0000-0000-000000000003', $result->id()->value());
        self::assertSame('00000000-0000-0000-0000-000000000005', $result->userId());
        self::assertSame('Findable Form', $result->name()->value());
        self::assertSame('Description', $result->description());
        self::assertFalse($result->isPublished());
        self::assertSame(1, $result->getVersion());
    }

    public function testReturnsNullWhenFormNotFound(): void
    {
        $result = $this->repository->findById(new FormId('00000000-0000-0000-0000-000000000999'));

        self::assertNull($result);
    }

    public function testFindsFormByIdWithFields(): void
    {
        $formModel = FormModel::factory()->create([
            'id' => '00000000-0000-0000-0000-000000000004',
            'user_id' => '00000000-0000-0000-0000-000000000006',
            'name' => 'Form with Fields',
        ]);

        DB::table('form_fields')->insert([
            'id' => '00000000-0000-0000-0000-000000000130',
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

        $result = $this->repository->findById(new FormId('00000000-0000-0000-0000-000000000004'));

        self::assertNotNull($result);
        self::assertCount(1, $result->fields());
        self::assertSame('Username', $result->fields()[0]->label());
        self::assertSame('text', $result->fields()[0]->type()->value());
        self::assertTrue($result->fields()[0]->isRequired());
    }

    public function testFindsByUserId(): void
    {
        FormModel::factory()->create([
            'id' => '00000000-0000-0000-0000-000000000010',
            'user_id' => '00000000-0000-0000-0000-000000000020',
            'name' => 'Form 1',
        ]);

        FormModel::factory()->create([
            'id' => '00000000-0000-0000-0000-000000000011',
            'user_id' => '00000000-0000-0000-0000-000000000020',
            'name' => 'Form 2',
        ]);

        FormModel::factory()->create([
            'id' => '00000000-0000-0000-0000-000000000012',
            'user_id' => '00000000-0000-0000-0000-000000000021',
            'name' => 'Other Form',
        ]);

        $results = $this->repository->findByUserId('00000000-0000-0000-0000-000000000020');

        self::assertCount(2, $results);
        self::assertSame('00000000-0000-0000-0000-000000000010', $results[0]->id()->value());
        self::assertSame('00000000-0000-0000-0000-000000000011', $results[1]->id()->value());
    }

    public function testReturnsEmptyArrayWhenNoFormsForUser(): void
    {
        $results = $this->repository->findByUserId('00000000-0000-0000-0000-000000000030');

        self::assertIsArray($results);
        self::assertEmpty($results);
    }

    /**
     * @throws Throwable
     */
    public function testDeletesFormAggregate(): void
    {
        $form = new FormAggregate(
            id: new FormId('00000000-0000-0000-0000-000000000040'),
            userId: '00000000-0000-0000-0000-000000000007',
            name: new FormName('Deletable Form'),
        );

        $this->repository->save($form);
        $this->assertDatabaseHas('forms', ['id' => '00000000-0000-0000-0000-000000000040']);

        $this->repository->delete($form);

        $this->assertDatabaseMissing('forms', ['id' => '00000000-0000-0000-0000-000000000040', 'deleted_at' => null]);
    }

    /**
     * @throws Throwable
     */
    public function testDeletesFormCascadesFields(): void
    {
        $form = new FormAggregate(
            id: new FormId('00000000-0000-0000-0000-000000000050'),
            userId: '00000000-0000-0000-0000-000000000008',
            name: new FormName('Form with Cascade'),
        );

        $field = new Field(
            id: '00000000-0000-0000-0000-000000000051',
            label: 'Test',
            type: new FieldType('text'),
        );

        $form->addField($field);
        $this->repository->save($form);

        $this->assertDatabaseHas('form_fields', ['id' => '00000000-0000-0000-0000-000000000051']);

        $this->repository->delete($form);

        $this->assertDatabaseMissing('form_fields', ['id' => '00000000-0000-0000-0000-000000000051']);
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
            id: new FormId('00000000-0000-0000-0000-000000000060'),
            userId: '00000000-0000-0000-0000-000000000009',
            name: new FormName('Complex Field Form'),
        );

        $field = new Field(
            id: '00000000-0000-0000-0000-000000000061',
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
            'id' => '00000000-0000-0000-0000-000000000061',
            'label' => 'Price',
            'type' => 'currency',
            'required' => true,
            'unit' => 'USD',
            'category' => 'financial',
            'order' => 5,
        ]);

        $savedForm = $this->repository->findById(new FormId('00000000-0000-0000-0000-000000000060'));
        self::assertNotNull($savedForm);
        self::assertSame(['min' => 0, 'max' => 1000], $savedForm->fields()[0]->options());
    }
}
