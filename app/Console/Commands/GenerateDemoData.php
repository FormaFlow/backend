<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\Carbon;
use FormaFlow\Entries\Infrastructure\Persistence\Eloquent\EntryModel;
use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormModel;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class GenerateDemoData extends Command
{
    protected $signature = 'demo:generate';

    protected $description = 'Generate demo data for the application';

    public function handle(): void
    {
        $this->info('Generating demo data...');

        $randomInt = random_int(1000, 9999);
        $email = "test+{$randomInt}@test.com";
        $password = 'cPAiAibpNzn7g88';

        $user = UserModel::query()->create([
            'id' => (string)Str::uuid(),
            'name' => 'Demo User ' . $randomInt,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $this->info("User created: {$email} / {$password}");

        $this->createBudgetForm($user);
        $this->createMedicineForm($user);

        $this->info('Demo data generation completed!');
    }

    private function createBudgetForm(UserModel $user): void
    {
        $form = FormModel::query()->create([
            'id' => (string)Str::uuid(),
            'user_id' => $user->id,
            'name' => 'Budget Tracker',
            'description' => 'Track income and expenses',
            'published' => true,
        ]);

        $amountId = (string)Str::uuid();
        $categoryId = (string)Str::uuid();
        $descId = (string)Str::uuid();

        DB::table('form_fields')->insert([
            [
                'id' => $amountId,
                'form_id' => $form->id,
                'label' => 'Amount',
                'type' => 'currency',
                'unit' => 'USD',
                'required' => true,
                'options' => null,
                'category' => null,
                'order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $categoryId,
                'form_id' => $form->id,
                'label' => 'Category',
                'type' => 'select',
                'required' => true,
                'unit' => null,
                'options' => json_encode([
                    ['label' => 'Income', 'value' => 'income'],
                    ['label' => 'Expense', 'value' => 'expense'],
                ]),
                'category' => null,
                'order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $descId,
                'form_id' => $form->id,
                'label' => 'Description',
                'type' => 'text',
                'required' => false,
                'unit' => null,
                'options' => null,
                'category' => null,
                'order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->info('Budget form created.');

        $startDate = Carbon::now()->subYears(2);
        $endDate = Carbon::now();

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $entriesCount = random_int(0, 3);
            for ($i = 0; $i < $entriesCount; $i++) {
                $isIncome = (random_int(1, 100) > 70);
                $category = $isIncome ? 'income' : 'expense';
                $amount = $isIncome ? random_int(1000, 5000) : random_int(10, 200);

                EntryModel::query()->create([
                    'id' => (string)Str::uuid(),
                    'form_id' => $form->id,
                    'user_id' => $user->id,
                    'data' => [
                        $amountId => $amount,
                        $categoryId => $category,
                        $descId => $isIncome ? 'Salary/Bonus' : 'Groceries/Food',
                    ],
                    'created_at' => $date->copy()->setTime(random_int(8, 20), random_int(0, 59)),
                ]);
            }
        }
    }

    private function createMedicineForm(UserModel $user): void
    {
        $form = FormModel::query()->create([
            'id' => (string)Str::uuid(),
            'user_id' => $user->id,
            'name' => 'Medicine Tracker',
            'description' => 'Track daily medicine intake (ml)',
            'published' => true,
        ]);

        $dosageId = (string)Str::uuid();

        DB::table('form_fields')->insert([
            [
                'id' => $dosageId,
                'form_id' => $form->id,
                'label' => 'Dosage',
                'type' => 'number',
                'unit' => 'ml',
                'required' => true,
                'options' => null,
                'category' => null,
                'order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->info('Medicine form created.');

        $startDate = Carbon::now()->subYears(2);
        $endDate = Carbon::now();

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dailyTotal = 0;
            $target = random_int(35, 50);

            $hour = 8;

            while ($dailyTotal < $target) {
                $dose = random_int(1, 5);

                if (($dailyTotal + $dose > 50) && $dailyTotal >= 35) {
                    break;
                }

                $dailyTotal += $dose;

                EntryModel::query()->create([
                    'id' => (string)Str::uuid(),
                    'form_id' => $form->id,
                    'user_id' => $user->id,
                    'data' => [
                        $dosageId => $dose,
                    ],
                    'created_at' => $date->copy()->setTime($hour, random_int(0, 59)),
                ]);

                $hour++;
                if ($hour > 22) {
                    break;
                }
            }
        }
    }
}
