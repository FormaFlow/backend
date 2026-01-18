<?php

declare(strict_types=1);

namespace FormaFlow\Reports\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

final class DashboardController extends Controller
{
    public function weekSummary(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $startOfWeek = now()->startOfWeek()->format('Y-m-d H:i:s');
        $endOfWeek = now()->endOfWeek()->format('Y-m-d H:i:s');

        $entries = DB::table('entries')
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->get();

        $totalEntries = $entries->count();

        $forms = DB::table('forms')
            ->where('user_id', $userId)
            ->get();

        $summaryByForm = $entries->groupBy('form_id')->map(fn($group) => $group->count());

        return response()->json([
            'forms' => $forms,
            'total_entries' => $totalEntries,
            'summary_by_form' => $summaryByForm,
        ]);
    }

    public function monthSummary(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $startOfMonth = now()->startOfMonth()->format('Y-m-d H:i:s');
        $endOfMonth = now()->endOfMonth()->format('Y-m-d H:i:s');

        $entries = DB::table('entries')
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->get();

        $totalEntries = $entries->count();

        $forms = DB::table('forms')
            ->where('user_id', $userId)
            ->get();

        $summaryByForm = $entries->groupBy('form_id')->map(fn($group) => $group->count());

        return response()->json([
            'forms' => $forms,
            'total_entries' => $totalEntries,
            'summary_by_form' => $summaryByForm,
        ]);
    }

    public function trends(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';

        $weeklySql = $isPgsql ? "to_char(created_at, 'YYYY-IW')" : "strftime('%Y-%W', created_at)";
        $monthlySql = $isPgsql ? "to_char(created_at, 'YYYY-MM')" : "strftime('%Y-%m', created_at)";

        // Weekly trends (last 4 weeks)
        $weeklyTrends = DB::table('entries')
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subWeeks(4))
            ->select(DB::raw("$weeklySql as week"), DB::raw('count(*) as count'))
            ->groupBy('week')
            ->orderBy('week')
            ->get();

        // Monthly trends (last 6 months)
        $monthlyTrends = DB::table('entries')
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subMonths(6))
            ->select(DB::raw("$monthlySql as month"), DB::raw('count(*) as count'))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'weekly_trends' => $weeklyTrends,
            'monthly_trends' => $monthlyTrends,
        ]);
    }
}
