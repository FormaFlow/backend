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

        // 1. Create User
        $randomInt = random_int(1000, 9999);
        $email = "test+{$randomInt}@test.com";
        $password = 'cPAiAibpNzn7g88';

        $user = UserModel::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Demo User ' . $randomInt,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $this->info("User created: {$email} / {$password}");

        // 2. Budget Form
        $this->createBudgetForm($user);

        // 3. Medicine Form
        $this->createMedicineForm($user);

        $this->info('Demo data generation completed!');
    }

    private function createBudgetForm(UserModel $user): void
    {
        $form = FormModel::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'name' => 'Budget Tracker',
            'description' => 'Track income and expenses',
            'published' => true,
        ]);

        // Add fields directly via DB to ensure IDs are set if needed, or just insert
        DB::table('form_fields')->insert([
            [
                'id' => (string) Str::uuid(),
                'form_id' => $form->id,
                'name' => 'amount',
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
                'id' => (string) Str::uuid(),
                'form_id' => $form->id,
                'name' => 'category',
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
                'id' => (string) Str::uuid(),
                'form_id' => $form->id,
                'name' => 'description',
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

        // Generate Entries
        $startDate = Carbon::now()->subYears(2);
        $endDate = Carbon::now();

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            // 0-3 entries per day
            $entriesCount = random_int(0, 3);
            for ($i = 0; $i < $entriesCount; $i++) {
                $isIncome = (random_int(1, 100) > 70); // 30% income, 70% expense
                $category = $isIncome ? 'income' : 'expense';
                $amount = $isIncome ? random_int(1000, 5000) : random_int(10, 200);
                
                EntryModel::query()->create([
                    'id' => (string) Str::uuid(),
                    'form_id' => $form->id,
                    'user_id' => $user->id,
                    'data' => [
                        'amount' => $amount,
                        'category' => $category,
                        'description' => $isIncome ? 'Salary/Bonus' : 'Groceries/Food',
                    ],
                    'created_at' => $date->copy()->setTime(random_int(8, 20), random_int(0, 59)),
                ]);
            }
        }
    }

    private function createMedicineForm(UserModel $user): void
    {
        $form = FormModel::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'name' => 'Medicine Tracker',
            'description' => 'Track daily medicine intake (ml)',
            'published' => true,
        ]);

        DB::table('form_fields')->insert([
            [
                'id' => (string) Str::uuid(),
                'form_id' => $form->id,
                'name' => 'dosage',
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

        // Generate Entries
        // Constraint: 35-50 ml per day. 1-5 ml per dose.
        $startDate = Carbon::now()->subYears(2);
        $endDate = Carbon::now();

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $dailyTotal = 0;
            $target = random_int(35, 50);
            
            // Distribute doses across the day
            $hour = 8; // Start at 8 AM

            while ($dailyTotal < $target) {
                $dose = random_int(1, 5);
                
                // Don't exceed target too much (optional, but requested "35 to 50")
                // If we are already >= 35, stop.
                if (($dailyTotal + $dose > 50) && $dailyTotal >= 35) {
                    break;
                }

                $dailyTotal += $dose;
                
                EntryModel::query()->create([
                    'id' => (string) Str::uuid(),
                    'form_id' => $form->id,
                    'user_id' => $user->id,
                    'data' => [
                        'dosage' => $dose,
                    ],
                    'created_at' => $date->copy()->setTime($hour, random_int(0, 59)),
                ]);

                $hour++;
                if ($hour > 22) {
                    break;
                } // Safety break
            }
        }
    }
}
