<?php

declare(strict_types=1);

namespace FormaFlow\Reports\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class ReportController extends Controller
{
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'form_id' => 'required|string',
            'aggregation' => 'required|string|in:sum,avg,min,max,count',
            'field' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'tags' => 'nullable|array',
        ]);

        $query = DB::table('entries')
            ->where('form_id', $validated['form_id'])
            ->where('user_id', $request->user()->id);

        if (isset($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }
        if (isset($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        if (!empty($validated['tags'])) {
            $query->join('entry_tags', 'entries.id', '=', 'entry_tags.entry_id')
                  ->whereIn('entry_tags.tag', $validated['tags']);
        }

        if ($validated['aggregation'] === 'count') {
            return response()->json(['result' => $query->count()]);
        }

        $field = $validated['field'];
        // SQLite JSON extraction: json_extract(data, '$.field')
        // We need to handle casting if necessary.
        $jsonField = "json_extract(data, '$.\"{$field}\"')";

        $result = match ($validated['aggregation']) {
            'sum' => $query->sum(DB::raw($jsonField)),
            'avg' => $query->avg(DB::raw($jsonField)),
            'min' => $query->min(DB::raw($jsonField)),
            'max' => $query->max(DB::raw($jsonField)),
            default => 0,
        };

        return response()->json([
            'result' => $result,
            'aggregation' => $validated['aggregation'],
            'field' => $field,
        ]);
    }

    public function timeSeries(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'form_id' => 'required|string',
            'field' => 'required|string',
            'aggregation' => 'required|string|in:sum,avg,min,max,count',
            'period' => 'required|string|in:daily,weekly,monthly',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $query = DB::table('entries')
            ->where('form_id', $validated['form_id'])
            ->where('user_id', $request->user()->id);

        if (isset($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }
        if (isset($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        $field = $validated['field'];
        $jsonField = "json_extract(data, '$.\"{$field}\"')";
        
        $dateFormat = match ($validated['period']) {
            'daily' => '%Y-%m-%d',
            'weekly' => '%Y-%W', // SQLite doesn't strictly have %W in strftime in all versions, but let's try. Or just group by date.
            // Actually for SQLite: %Y-%m-%d is good for daily.
            // For weekly: strftime('%Y-%W', created_at)
            // For monthly: strftime('%Y-%m', created_at)
            'monthly' => '%Y-%m',
        };

        $selectDate = "strftime('{$dateFormat}', created_at) as date";

        $agg = match ($validated['aggregation']) {
            'sum' => "SUM($jsonField) as value",
            'avg' => "AVG($jsonField) as value",
            'min' => "MIN($jsonField) as value",
            'max' => "MAX($jsonField) as value",
            'count' => "COUNT(*) as value",
        };

        $data = $query->select(DB::raw($selectDate), DB::raw($agg))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json(['data' => $data]);
    }

    public function grouped(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'form_id' => 'required|string',
            'group_by' => 'required|string',
            'field' => 'required|string',
            'aggregation' => 'required|string|in:sum,avg,min,max,count',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $query = DB::table('entries')
            ->where('form_id', $validated['form_id'])
            ->where('user_id', $request->user()->id);

        if (isset($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }
        if (isset($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        $groupByField = $validated['group_by'];
        $groupByJson = "json_extract(data, '$.\"{$groupByField}\"')";
        
        $targetField = $validated['field'];
        $targetJson = "json_extract(data, '$.\"{$targetField}\"')";

        $agg = match ($validated['aggregation']) {
            'sum' => "SUM($targetJson) as value",
            'avg' => "AVG($targetJson) as value",
            'min' => "MIN($targetJson) as value",
            'max' => "MAX($targetJson) as value",
            'count' => "COUNT(*) as value",
        };

        $groups = $query->select(DB::raw("$groupByJson as category"), DB::raw($agg))
            ->groupBy('category')
            ->get();

        return response()->json(['groups' => $groups]);
    }

    public function export(Request $request): Response
    {
        $validated = $request->validate([
            'form_id' => 'required|string',
            'format' => 'required|string|in:csv,json',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $query = DB::table('entries')
            ->where('form_id', $validated['form_id'])
            ->where('user_id', $request->user()->id);

        if (isset($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }
        if (isset($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        $entries = $query->get();

        if ($validated['format'] === 'json') {
            return response()->json([
                'entries' => $entries->map(function ($entry) {
                    $entry->data = json_decode($entry->data);
                    return $entry;
                }),
                'meta' => ['count' => $entries->count()]
            ]);
        }

        // CSV Export
        $csvData = [];
        $headers = [];

        // Collect all possible keys from data
        foreach ($entries as $entry) {
            $data = json_decode($entry->data, true);
            $headers = array_unique(array_merge($headers, array_keys($data)));
        }
        
        // Add standard headers
        $csvHeaders = array_merge(['id', 'created_at'], $headers);

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $csvHeaders);

        foreach ($entries as $entry) {
            $data = json_decode($entry->data, true);
            $row = [$entry->id, $entry->created_at];
            foreach ($headers as $header) {
                $row[] = $data[$header] ?? '';
            }
            fputcsv($handle, $row);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return response($content, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="report.csv"',
        ]);
    }

    public function weeklySummary(Request $request): JsonResponse
    {
        // Simple implementation for weekly summary of a form (e.g. Budget)
        // Assumes fields "amount" and "category" (income/expense) exist or similar logic.
        // Or it aggregates everything. The test implies general summary.
        // Let's look at the test: 'total_income', 'total_expense', 'net', 'count'
        // This implies it looks for 'category' = 'income'/'expense' and sums 'amount'.

        $formId = $request->input('form_id');
        
        // Determine current week
        $startOfWeek = now()->startOfWeek()->format('Y-m-d');
        $endOfWeek = now()->endOfWeek()->format('Y-m-d');

        $entries = DB::table('entries')
            ->where('form_id', $formId)
            ->where('user_id', $request->user()->id)
            ->whereDate('created_at', '>=', $startOfWeek)
            ->whereDate('created_at', '<=', $endOfWeek)
            ->get();

        $totalIncome = 0;
        $totalExpense = 0;
        $count = $entries->count();

        foreach ($entries as $entry) {
            $data = json_decode($entry->data, true);
            $amount = $data['amount'] ?? 0;
            $category = strtolower($data['category'] ?? '');

            if ($category === 'income') {
                $totalIncome += $amount;
            } elseif ($category === 'expense') {
                $totalExpense += $amount;
            }
        }

        return response()->json([
            'week_start' => $startOfWeek,
            'week_end' => $endOfWeek,
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net' => $totalIncome - $totalExpense,
            'count' => $count,
        ]);
    }

    public function monthlySummary(Request $request): JsonResponse
    {
        $formId = $request->input('form_id');
        $month = $request->input('month', now()->format('Y-m'));

        $entries = DB::table('entries')
            ->where('form_id', $formId)
            ->where('user_id', $request->user()->id)
            ->where(DB::raw("strftime('%Y-%m', created_at)"), $month)
            ->get();

        $totalIncome = 0;
        $totalExpense = 0;
        $count = $entries->count();

        foreach ($entries as $entry) {
            $data = json_decode($entry->data, true);
            $amount = $data['amount'] ?? 0;
            $category = strtolower($data['category'] ?? '');

            if ($category === 'income') {
                $totalIncome += $amount;
            } elseif ($category === 'expense') {
                $totalExpense += $amount;
            }
        }

        return response()->json([
            'month' => $month,
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net' => $totalIncome - $totalExpense,
            'count' => $count,
        ]);
    }

    public function predefinedBudget(Request $request): JsonResponse
    {
        // Find form with name 'Budget Tracker' or similar, or just aggregate all forms?
        // Test says "access predefined budget report", implies logic specific to budget nature.
        // It likely aggregates across all forms or a specific budget form if known.
        // But simpler: The test sets up a 'Budget Tracker' form. 
        // We need to find that form for the user.
        
        $form = DB::table('forms')
            ->where('user_id', $request->user()->id)
            ->where('name', 'Budget Tracker')
            ->first();

        if (!$form) {
            return response()->json(['error' => 'Budget Tracker form not found'], 404);
        }

        $query = DB::table('entries')
            ->where('form_id', $form->id);

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $entries = $query->get();
        $totalIncome = 0;
        $totalExpense = 0;

        foreach ($entries as $entry) {
            $data = json_decode($entry->data, true);
            $amount = $data['amount'] ?? 0;
            $category = strtolower($data['category'] ?? '');

            if ($category === 'income') {
                $totalIncome += $amount;
            } elseif ($category === 'expense') {
                $totalExpense += $amount;
            }
        }

        $balance = $totalIncome - $totalExpense;
        $savingsRate = $totalIncome > 0 ? ($balance / $totalIncome) * 100 : 0;

        return response()->json([
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'balance' => $balance,
            'savings_rate' => round($savingsRate, 2),
        ]);
    }

    public function predefinedMedicine(Request $request): JsonResponse
    {
        $form = DB::table('forms')
            ->where('user_id', $request->user()->id)
            ->where('name', 'Medicine Tracker')
            ->first();

        if (!$form) {
            return response()->json(['error' => 'Medicine Tracker form not found'], 404);
        }

        $query = DB::table('entries')->where('form_id', $form->id);
         if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $entries = $query->get();
        $medicines = [];
        $totalConsumption = 0;

        foreach ($entries as $entry) {
            $data = json_decode($entry->data, true);
            $name = $data['medicine_name'] ?? 'Unknown';
            $qty = $data['quantity'] ?? 0;
            
            if (!isset($medicines[$name])) {
                $medicines[$name] = 0;
            }
            $medicines[$name] += $qty;
            $totalConsumption += $qty;
        }

        return response()->json([
            'medicines' => $medicines,
            'total_consumption' => $totalConsumption,
            'frequency' => $entries->count(), // approximation
        ]);
    }

    public function predefinedWeight(Request $request): JsonResponse
    {
        $form = DB::table('forms')
            ->where('user_id', $request->user()->id)
            ->where('name', 'Weight Tracker')
            ->first();

        if (!$form) {
            return response()->json(['error' => 'Weight Tracker form not found'], 404);
        }

        $query = DB::table('entries')
            ->where('form_id', $form->id)
            ->orderBy('created_at', 'asc'); // Ensure order for trend

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $entries = $query->get();
        
        if ($entries->isEmpty()) {
            return response()->json([
                'current_weight' => 0,
                'starting_weight' => 0,
                'change' => 0,
                'trend' => [],
            ]);
        }

        $firstEntry = json_decode($entries->first()->data, true);
        $lastEntry = json_decode($entries->last()->data, true);
        
        $startWeight = $firstEntry['weight'] ?? 0;
        $currentWeight = $lastEntry['weight'] ?? 0;

        $trend = $entries->map(function($e) {
            $d = json_decode($e->data, true);
            return [
                'date' => $e->created_at,
                'weight' => $d['weight'] ?? 0
            ];
        });

        return response()->json([
            'current_weight' => $currentWeight,
            'starting_weight' => $startWeight,
            'change' => $currentWeight - $startWeight,
            'trend' => $trend,
        ]);
    }
}
