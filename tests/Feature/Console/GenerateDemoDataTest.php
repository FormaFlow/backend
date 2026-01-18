<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use FormaFlow\Entries\Infrastructure\Persistence\Eloquent\EntryModel;
use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormModel;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

final class GenerateDemoDataTest extends TestCase
{

    public function test_command_generates_demo_data_successfully(): void
    {
        // Execute the command
        $this
            ->artisan('demo:generate')
            ->expectsOutput('Generating demo data...')
            ->expectsOutput('Budget form created.')
            ->expectsOutput('Medicine form created.')
            ->expectsOutput('Demo data generation completed!')
            ->assertSuccessful();

        // Check User Creation
        $this->assertDatabaseCount('users', 1);
        $user = UserModel::query()->first();
        $this->assertMatchesRegularExpression('/^test\+\d+@test\.com$/', $user->email);

        // Check Forms Creation
        $this->assertDatabaseHas('forms', [
            'name' => 'Budget Tracker',
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('forms', [
            'name' => 'Medicine Tracker',
            'user_id' => $user->id,
        ]);

        $budgetForm = FormModel::query()->where('name', 'Budget Tracker')->first();
        $medicineForm = FormModel::query()->where('name', 'Medicine Tracker')->first();

        // Check Fields
        $this->assertDatabaseHas('form_fields', [
            'form_id' => $budgetForm->id,
            'label' => 'Amount',
        ]);
        $this->assertDatabaseHas('form_fields', [
            'form_id' => $budgetForm->id,
            'label' => 'Category',
        ]);

        $this->assertDatabaseHas('form_fields', [
            'form_id' => $medicineForm->id,
            'label' => 'Dosage',
        ]);

        // Check Entries
        // We expect entries for last 30 days.
        // Budget entries: 0-3 per day. Total should be >= 0.
        // Medicine entries: >= 1 per day (target 35-50ml, dose 1-5ml -> at least 7 doses per day).
        // So plenty of entries.

        $budgetEntriesCount = EntryModel::query()->where('form_id', $budgetForm->id)->count();
        $medicineEntriesCount = EntryModel::query()->where('form_id', $medicineForm->id)->count();

        // We can't be 100% sure about exact count due to randomness, but it should be significant.
        // 30 days. Budget: avg 1.5 * 30 = 45.
        // Medicine: ~10 * 30 = 300.

        $this->assertGreaterThan(
            0,
            $budgetEntriesCount,
            'Budget entries should be generated (unless randomness hit 0 for all 30 days which is impossibly rare)'
        );
        $this->assertGreaterThan(100, $medicineEntriesCount, 'Medicine entries should be generated');

        // Check Medicine Constraints (roughly)
        // Check one entry data structure
        $medicineEntry = EntryModel::query()->where('form_id', $medicineForm->id)->first();
        $dosageField = DB::table('form_fields')->where('form_id', $medicineForm->id)->where('label', 'Dosage')->first();

        $this->assertIsArray($medicineEntry->data);
        $this->assertArrayHasKey($dosageField->id, $medicineEntry->data);
        $this->assertGreaterThanOrEqual(1, $medicineEntry->data[$dosageField->id]);
        $this->assertLessThanOrEqual(5, $medicineEntry->data[$dosageField->id]);
    }
}
