<?php

namespace App\Http\Controllers;

use App\Models\Operation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index()
    {
        return view('analytics');
    }

    public function categories()
    {
        $categories = Operation::select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');
        
        return response()->json($categories);
    }

    private function applyExcludeCategories($query, Request $request)
    {
        if ($request->has('exclude_categories')) {
            $exclude = $request->get('exclude_categories');
            if (is_string($exclude)) {
                $exclude = explode(',', $exclude);
            }
            if (!empty($exclude)) {
                $query->whereNotIn('category', $exclude);
            }
        }
        return $query;
    }

    public function summary(Request $request)
    {
        $incomeQuery = Operation::where('action', 'income');
        $expenseQuery = Operation::where('action', 'expense');
        $totalQuery = Operation::query();
        
        $this->applyExcludeCategories($incomeQuery, $request);
        $this->applyExcludeCategories($expenseQuery, $request);
        $this->applyExcludeCategories($totalQuery, $request);
        
        $totalIncome = $incomeQuery->sum('ammount');
        $totalExpense = $expenseQuery->sum('ammount');
        $totalOperations = $totalQuery->count();
        
        return response()->json([
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'balance' => $totalIncome - $totalExpense,
            'total_operations' => $totalOperations,
        ]);
    }

    public function byCategory(Request $request)
    {
        $incomeQuery = Operation::where('action', 'income')
            ->select('category', DB::raw('SUM(ammount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->orderByDesc('total');
        
        $expenseQuery = Operation::where('action', 'expense')
            ->select('category', DB::raw('SUM(ammount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->orderByDesc('total');

        $this->applyExcludeCategories($incomeQuery, $request);
        $this->applyExcludeCategories($expenseQuery, $request);

        return response()->json([
            'income' => $incomeQuery->get(),
            'expense' => $expenseQuery->get(),
        ]);
    }

    public function byDate(Request $request)
    {
        $groupBy = $request->get('group', 'month');
        
        if ($groupBy === 'day') {
            $dateFormat = 'YYYY-MM-DD';
        } elseif ($groupBy === 'week') {
            $dateFormat = 'IYYY-IW';
        } else {
            $dateFormat = 'YYYY-MM';
        }

        $incomeQuery = Operation::where('action', 'income')
            ->select(DB::raw("TO_CHAR(datatime, '$dateFormat') as period"), DB::raw('SUM(ammount) as total'))
            ->groupBy('period')
            ->orderBy('period');

        $expenseQuery = Operation::where('action', 'expense')
            ->select(DB::raw("TO_CHAR(datatime, '$dateFormat') as period"), DB::raw('SUM(ammount) as total'))
            ->groupBy('period')
            ->orderBy('period');

        $this->applyExcludeCategories($incomeQuery, $request);
        $this->applyExcludeCategories($expenseQuery, $request);

        return response()->json([
            'income' => $incomeQuery->get(),
            'expense' => $expenseQuery->get(),
        ]);
    }

    public function byUser(Request $request)
    {
        $incomeQuery = Operation::where('action', 'income')
            ->select('username', DB::raw('SUM(ammount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('username')
            ->orderByDesc('total');

        $expenseQuery = Operation::where('action', 'expense')
            ->select('username', DB::raw('SUM(ammount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('username')
            ->orderByDesc('total');

        $this->applyExcludeCategories($incomeQuery, $request);
        $this->applyExcludeCategories($expenseQuery, $request);

        return response()->json([
            'income' => $incomeQuery->get(),
            'expense' => $expenseQuery->get(),
        ]);
    }

    public function byBank(Request $request)
    {
        $incomeQuery = Operation::where('action', 'income')
            ->select('data_source as bank', DB::raw('SUM(ammount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('data_source')
            ->orderByDesc('total');

        $expenseQuery = Operation::where('action', 'expense')
            ->select('data_source as bank', DB::raw('SUM(ammount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('data_source')
            ->orderByDesc('total');

        $this->applyExcludeCategories($incomeQuery, $request);
        $this->applyExcludeCategories($expenseQuery, $request);

        return response()->json([
            'income' => $incomeQuery->get(),
            'expense' => $expenseQuery->get(),
        ]);
    }

    public function bySemanticCategory(Request $request)
    {
        $incomeQuery = Operation::where('action', 'income')
            ->whereNotNull('semantic_category')
            ->where('semantic_category', '!=', '')
            ->select('semantic_category', DB::raw('SUM(ammount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('semantic_category')
            ->orderByDesc('total');

        $expenseQuery = Operation::where('action', 'expense')
            ->whereNotNull('semantic_category')
            ->where('semantic_category', '!=', '')
            ->select('semantic_category', DB::raw('SUM(ammount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('semantic_category')
            ->orderByDesc('total');

        $this->applyExcludeCategories($incomeQuery, $request);
        $this->applyExcludeCategories($expenseQuery, $request);

        $withoutSemantic = Operation::whereNull('semantic_category')
            ->orWhere('semantic_category', '')
            ->count();

        return response()->json([
            'income' => $incomeQuery->get(),
            'expense' => $expenseQuery->get(),
            'without_semantic' => $withoutSemantic
        ]);
    }

    public function operations(Request $request)
    {
        $query = Operation::query();

        $this->applyExcludeCategories($query, $request);

        if ($request->has('category')) {
            $query->where('category', $request->get('category'));
        }
        if ($request->has('semantic_category')) {
            $query->where('semantic_category', $request->get('semantic_category'));
        }
        if ($request->has('action')) {
            $query->where('action', $request->get('action'));
        }
        if ($request->has('username')) {
            $query->where('username', $request->get('username'));
        }
        if ($request->has('bank')) {
            $query->where('data_source', $request->get('bank'));
        }
        if ($request->has('period')) {
            $period = $request->get('period');
            $groupBy = $request->get('group', 'month');
            
            if ($groupBy === 'day') {
                $query->whereRaw("TO_CHAR(datatime, 'YYYY-MM-DD') = ?", [$period]);
            } elseif ($groupBy === 'week') {
                $query->whereRaw("TO_CHAR(datatime, 'IYYY-IW') = ?", [$period]);
            } else {
                $query->whereRaw("TO_CHAR(datatime, 'YYYY-MM') = ?", [$period]);
            }
        }

        $operations = $query->orderByDesc('datatime')->get();

        return response()->json(['data' => $operations]);
    }
}
