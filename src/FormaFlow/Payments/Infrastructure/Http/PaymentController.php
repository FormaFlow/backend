<?php

declare(strict_types=1);

namespace FormaFlow\Payments\Infrastructure\Http;

use Carbon\CarbonImmutable;
use FormaFlow\Payments\Application\PaymentScheduleService;
use FormaFlow\Payments\Infrastructure\Persistence\Eloquent\PaymentCategoryModel;
use FormaFlow\Payments\Infrastructure\Persistence\Eloquent\PaymentOccurrenceModel;
use FormaFlow\Payments\Infrastructure\Persistence\Eloquent\PaymentPlanModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final readonly class PaymentController
{
    public function __construct(private PaymentScheduleService $schedules)
    {
    }

    public function categories(Request $request): JsonResponse
    {
        $categories = PaymentCategoryModel::query()
            ->where('user_id', $request->user()->id)
            ->withCount('plans')
            ->orderBy('name')
            ->get();

        return response()->json(['categories' => $categories]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('payment_categories', 'name')->where('user_id', $request->user()->id),
            ],
            'color' => 'nullable|string|max:16',
        ]);
        $category = PaymentCategoryModel::query()->create($data + ['user_id' => $request->user()->id]);

        return response()->json($category, Response::HTTP_CREATED);
    }

    public function updateCategory(Request $request, string $id): JsonResponse
    {
        $category = $this->ownedCategory($request, $id);
        $data = $request->validate([
            'name' => [
                'sometimes', 'string', 'max:120',
                Rule::unique('payment_categories', 'name')
                    ->where('user_id', $request->user()->id)
                    ->ignore($category->id),
            ],
            'color' => 'nullable|string|max:16',
        ]);
        $category->update($data);

        return response()->json($category->fresh());
    }

    public function destroyCategory(Request $request, string $id): JsonResponse
    {
        $category = $this->ownedCategory($request, $id);
        if ($category->plans()->exists()) {
            return response()->json(['message' => 'Category is in use'], Response::HTTP_CONFLICT);
        }
        $category->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function plans(Request $request): JsonResponse
    {
        $query = PaymentPlanModel::query()
            ->where('user_id', $request->user()->id)
            ->with('category')
            ->withCount([
                'occurrences',
                'occurrences as paid_count' => fn(Builder $query) => $query->where('status', 'paid'),
                'occurrences as planned_count' => fn(Builder $query) => $query->where('status', 'planned'),
            ]);
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return response()->json(['plans' => $query->orderBy('name')->get()]);
    }

    public function showPlan(Request $request, string $id): JsonResponse
    {
        $plan = $this->ownedPlan($request, $id);
        $plan->load(['category', 'occurrences' => fn($query) => $query->orderBy('due_on')]);

        return response()->json($plan);
    }

    public function storePlan(Request $request): JsonResponse
    {
        $data = $this->validatePlan($request);
        $plan = DB::transaction(function () use ($request, $data): PaymentPlanModel {
            $occurrences = $data['occurrences'] ?? [];
            unset($data['occurrences']);
            $data['user_id'] = $request->user()->id;
            $data['status'] = 'active';
            $plan = PaymentPlanModel::query()->create($data);

            if ($plan->schedule_type === 'one_off') {
                $this->createOccurrence($plan, [
                    'due_on' => $plan->starts_on->toDateString(),
                    'nominal_amount' => $plan->default_nominal_amount,
                    'expected_amount' => $this->schedules->expectedAmount($plan),
                ]);
            } elseif ($plan->schedule_type === 'manual') {
                foreach ($occurrences as $occurrence) {
                    $this->createOccurrence($plan, $occurrence);
                }
            } else {
                $this->schedules->materialize($plan, CarbonImmutable::today()->addMonths(12));
            }

            return $plan;
        });

        return response()->json($plan->load('category'), Response::HTTP_CREATED);
    }

    public function updatePlan(Request $request, string $id): JsonResponse
    {
        $plan = $this->ownedPlan($request, $id);
        $data = $this->validatePlan($request, true);
        $effectiveFrom = CarbonImmutable::parse($data['effective_from'] ?? CarbonImmutable::today())->toDateString();
        unset($data['effective_from'], $data['occurrences']);

        DB::transaction(function () use ($plan, $data, $effectiveFrom): void {
            $plan->update($data);
            if (in_array($plan->schedule_type, ['monthly', 'interval'], true)) {
                $plan->occurrences()
                    ->where('status', 'planned')
                    ->where('kind', 'scheduled')
                    ->whereDate('due_on', '>=', $effectiveFrom)
                    ->delete();
                $plan->refresh();
                $this->schedules->materialize($plan, CarbonImmutable::today()->addMonths(12));
            }
        });

        $plan->refresh();
        return response()->json($plan->load('category'));
    }

    public function destroyPlan(Request $request, string $id): JsonResponse
    {
        $plan = $this->ownedPlan($request, $id);
        if ($plan->occurrences()->where('status', 'paid')->exists()) {
            return response()->json(
                ['message' => 'A plan with payment history cannot be deleted'],
                Response::HTTP_CONFLICT,
            );
        }
        $plan->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function occurrences(Request $request): JsonResponse
    {
        $query = $this->occurrenceQuery($request);
        $from = $request->input('from', CarbonImmutable::today()->subMonth()->toDateString());
        $to = $request->input('to', CarbonImmutable::today()->addMonths(12)->toDateString());
        $query->whereBetween('due_on', [$from, $to]);
        $status = $request->input('status');
        if ($status === 'overdue') {
            $query->where('payment_occurrences.status', 'planned')->whereDate('due_on', '<', CarbonImmutable::today());
        } elseif ($status) {
            $query->where('payment_occurrences.status', $status);
        }

        return response()->json(['occurrences' => $query->orderBy('due_on')->get()]);
    }

    public function storeOccurrence(Request $request, string $planId): JsonResponse
    {
        $plan = $this->ownedPlan($request, $planId);
        $data = $request->validate($this->occurrenceRules());
        $occurrence = $this->createOccurrence($plan, $data);

        return response()->json($occurrence->load('plan.category'), Response::HTTP_CREATED);
    }

    public function updateOccurrence(Request $request, string $id): JsonResponse
    {
        $occurrence = $this->ownedOccurrence($request, $id);
        if ($occurrence->status !== 'planned') {
            return response()->json(['message' => 'Only planned payments can be edited'], Response::HTTP_CONFLICT);
        }
        $occurrence->update($request->validate($this->occurrenceRules(true)));

        $occurrence->refresh();
        return response()->json($occurrence->load('plan.category'));
    }

    public function destroyOccurrence(Request $request, string $id): JsonResponse
    {
        $occurrence = $this->ownedOccurrence($request, $id);
        if ($occurrence->status !== 'planned') {
            return response()->json(['message' => 'Only planned payments can be deleted'], Response::HTTP_CONFLICT);
        }
        $occurrence->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function pay(Request $request, string $id): JsonResponse
    {
        $occurrence = $this->ownedOccurrence($request, $id);
        if ($occurrence->status === 'cancelled') {
            return response()->json(['message' => 'Cancelled payment cannot be paid'], Response::HTTP_CONFLICT);
        }
        $data = $request->validate([
            'actual_amount' => 'required|numeric|min:0|max:999999999999.99',
            'paid_at' => 'required|date',
        ]);
        $occurrence->update($data + ['status' => 'paid']);

        $occurrence->refresh();
        return response()->json($occurrence->load('plan.category'));
    }

    public function reopen(Request $request, string $id): JsonResponse
    {
        $occurrence = $this->ownedOccurrence($request, $id);
        if ($occurrence->kind === 'settlement') {
            return response()->json(['message' => 'Settlement payments cannot be reopened'], Response::HTTP_CONFLICT);
        }
        $occurrence->update(['status' => 'planned', 'actual_amount' => null, 'paid_at' => null]);

        $occurrence->refresh();
        return response()->json($occurrence->load('plan.category'));
    }

    public function closePlan(Request $request, string $id): JsonResponse
    {
        $plan = $this->ownedPlan($request, $id);
        $data = $request->validate([
            'paid_at' => 'required|date',
            'actual_amount' => 'required|numeric|min:0|max:999999999999.99',
            'nominal_amount' => 'nullable|numeric|min:0|max:999999999999.99',
            'expected_amount' => 'nullable|numeric|min:0|max:999999999999.99',
            'notes' => 'nullable|string|max:2000',
        ]);

        DB::transaction(function () use ($plan, $data): void {
            $paidAt = CarbonImmutable::parse($data['paid_at']);
            $plan->occurrences()->where('status', 'planned')->update(['status' => 'cancelled']);
            PaymentOccurrenceModel::query()->updateOrCreate(
                ['plan_id' => $plan->id, 'due_on' => $paidAt->toDateString(), 'kind' => 'settlement'],
                [
                    'status' => 'paid',
                    'paid_at' => $paidAt,
                    'actual_amount' => $data['actual_amount'],
                    'nominal_amount' => $data['nominal_amount'] ?? null,
                    'expected_amount' => $data['expected_amount'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'source_type' => 'manual',
                    'source_key' => sprintf('%s:settlement', $plan->id),
                ],
            );
            $plan->update(['status' => 'closed', 'closed_at' => $paidAt]);
        });

        $plan->refresh();
        return response()->json($plan->load(['category', 'occurrences']));
    }

    public function overview(Request $request): JsonResponse
    {
        $today = CarbonImmutable::today();
        $monthEnd = $today->endOfMonth();
        $base = $this->occurrenceQuery($request);
        $occurrences = (clone $base)
            ->whereBetween('due_on', [
                $request->input('from', $today->subMonth()->toDateString()),
                $request->input('to', $today->addMonths(3)->toDateString()),
            ])
            ->orderBy('due_on')
            ->get();

        $plannedMonth = (clone $base)->where('payment_occurrences.status', 'planned')
            ->whereBetween('due_on', [$today->startOfMonth(), $monthEnd])->sum('expected_amount');
        $paidMonth = (clone $base)->where('payment_occurrences.status', 'paid')
            ->whereBetween('paid_at', [$today->startOfMonth(), $monthEnd->endOfDay()])->sum('actual_amount');
        $overdue = (clone $base)->where('payment_occurrences.status', 'planned')
            ->whereDate('due_on', '<', $today)->count();
        $dueSoon = (clone $base)->where('payment_occurrences.status', 'planned')
            ->whereBetween('due_on', [$today, $today->addDays(7)])->count();

        return response()->json([
            'summary' => [
                'overdue_count' => $overdue,
                'due_soon_count' => $dueSoon,
                'expected_this_month' => number_format((float)$plannedMonth, 2, '.', ''),
                'paid_this_month' => number_format((float)$paidMonth, 2, '.', ''),
            ],
            'occurrences' => $occurrences,
        ]);
    }

    private function validatePlan(Request $request, bool $partial = false): array
    {
        $sometimes = $partial ? 'sometimes|' : '';
        $data = $request->validate([
            'category_id' => $sometimes . 'nullable|uuid',
            'name' => $sometimes . 'required|string|max:160',
            'payee' => 'nullable|string|max:160',
            'type' => $sometimes . 'required|in:one_off,recurring,installment',
            'currency' => $sometimes . 'required|string|size:3',
            'schedule_type' => $sometimes . 'required|in:one_off,monthly,interval,manual',
            'starts_on' => $sometimes . 'nullable|date',
            'ends_on' => 'nullable|date|after_or_equal:starts_on',
            'day_of_month' => 'nullable|integer|min:1|max:31',
            'interval_days' => 'nullable|integer|min:1|max:366',
            'total_installments' => 'nullable|integer|min:1|max:10000',
            'default_nominal_amount' => 'nullable|numeric|min:0|max:999999999999.99',
            'default_expected_amount' => 'nullable|numeric|min:0|max:999999999999.99',
            'fee_percent' => 'nullable|numeric|min:0|max:10000',
            'fee_fixed' => 'nullable|numeric|min:0|max:999999999999.99',
            'notes' => 'nullable|string|max:5000',
            'effective_from' => 'nullable|date',
            'occurrences' => 'nullable|array|max:10000',
            'occurrences.*.due_on' => 'required_with:occurrences|date',
            'occurrences.*.sequence_no' => 'nullable|integer|min:1',
            'occurrences.*.total_count' => 'nullable|integer|min:1',
            'occurrences.*.nominal_amount' => 'nullable|numeric|min:0',
            'occurrences.*.expected_amount' => 'nullable|numeric|min:0',
        ]);

        if (array_key_exists('category_id', $data) && $data['category_id'] !== null) {
            $owned = PaymentCategoryModel::query()
                ->where('id', $data['category_id'])
                ->where('user_id', $request->user()->id)
                ->exists();
            abort_unless($owned, Response::HTTP_UNPROCESSABLE_ENTITY, 'Unknown payment category');
        }

        $schedule = $data['schedule_type'] ?? null;
        if (!$partial || $schedule !== null) {
            if (in_array($schedule, ['one_off', 'monthly', 'interval'], true) && empty($data['starts_on'])) {
                abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'starts_on is required for this schedule');
            }
            if ($schedule === 'monthly' && empty($data['day_of_month'])) {
                abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'day_of_month is required');
            }
            if ($schedule === 'interval' && empty($data['interval_days'])) {
                abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'interval_days is required');
            }
            if ($schedule === 'manual' && !$partial && empty($data['occurrences'])) {
                abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'occurrences are required');
            }
        }

        return $data;
    }

    private function occurrenceRules(bool $partial = false): array
    {
        $sometimes = $partial ? 'sometimes|' : '';

        return [
            'due_on' => $sometimes . 'required|date',
            'sequence_no' => 'nullable|integer|min:1',
            'total_count' => 'nullable|integer|min:1',
            'nominal_amount' => 'nullable|numeric|min:0|max:999999999999.99',
            'expected_amount' => 'nullable|numeric|min:0|max:999999999999.99',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    private function createOccurrence(PaymentPlanModel $plan, array $data): PaymentOccurrenceModel
    {
        return PaymentOccurrenceModel::query()->create([
            'plan_id' => $plan->id,
            'due_on' => $data['due_on'],
            'sequence_no' => $data['sequence_no'] ?? null,
            'total_count' => $data['total_count'] ?? $plan->total_installments,
            'kind' => 'scheduled',
            'nominal_amount' => $data['nominal_amount'] ?? $plan->default_nominal_amount,
            'expected_amount' => $data['expected_amount'] ?? $this->schedules->expectedAmount($plan),
            'status' => 'planned',
            'notes' => $data['notes'] ?? null,
            'source_type' => $data['source_type'] ?? 'manual',
            'source_key' => $data['source_key'] ?? null,
        ]);
    }

    private function occurrenceQuery(Request $request): Builder
    {
        $query = PaymentOccurrenceModel::query()
            ->whereHas('plan', fn(Builder $query) => $query->where('user_id', $request->user()->id))
            ->with('plan.category');
        if ($request->filled('category_id')) {
            $query->whereHas(
                'plan',
                fn(Builder $query) => $query->where('category_id', $request->input('category_id')),
            );
        }
        if ($request->filled('plan_id')) {
            $query->where('plan_id', $request->input('plan_id'));
        }

        return $query;
    }

    private function ownedCategory(Request $request, string $id): PaymentCategoryModel
    {
        return PaymentCategoryModel::query()->where('user_id', $request->user()->id)->findOrFail($id);
    }

    private function ownedPlan(Request $request, string $id): PaymentPlanModel
    {
        return PaymentPlanModel::query()->where('user_id', $request->user()->id)->findOrFail($id);
    }

    private function ownedOccurrence(Request $request, string $id): PaymentOccurrenceModel
    {
        return PaymentOccurrenceModel::query()
            ->whereHas('plan', fn(Builder $query) => $query->where('user_id', $request->user()->id))
            ->findOrFail($id);
    }
}
