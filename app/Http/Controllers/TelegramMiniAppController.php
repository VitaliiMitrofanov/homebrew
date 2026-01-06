<?php

namespace App\Http\Controllers;

use App\Models\Operation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TelegramMiniAppController extends Controller
{
    public function index()
    {
        return view('telegram-mini-app');
    }

    private function getUserName(Request $request): ?string
    {
        $telegramUser = $request->attributes->get('telegram_user');
        
        if ($telegramUser) {
            return $telegramUser['username'] ?? $telegramUser['first_name'] ?? null;
        }
        
        return $request->query('username');
    }

    private function applyUserFilter($query, ?string $userName)
    {
        if ($userName) {
            $query->where('username', $userName);
        }
        return $query;
    }

    public function summary(Request $request)
    {
        $userName = $this->getUserName($request);
        
        $incomeQuery = Operation::where('action', 'income');
        $expenseQuery = Operation::where('action', 'expense');
        $totalQuery = Operation::query();
        
        $this->applyUserFilter($incomeQuery, $userName);
        $this->applyUserFilter($expenseQuery, $userName);
        $this->applyUserFilter($totalQuery, $userName);
        
        $totalIncome = $incomeQuery->sum('ammount');
        $totalExpense = $expenseQuery->sum('ammount');
        $totalOperations = $totalQuery->count();
        LOG::info('summary function');
        return response()->json([
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'balance' => $totalIncome - $totalExpense,
            'total_operations' => $totalOperations,
            'username' => $userName,
        ]);
    }

    public function byCategory(Request $request)
    {
        $userName = $this->getUserName($request);
        
        $incomeQuery = Operation::where('action', 'income')
            ->select('category', DB::raw('SUM(ammount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->orderByDesc('total');
            
        $expenseQuery = Operation::where('action', 'expense')
            ->select('category', DB::raw('SUM(ammount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->orderByDesc('total');

        $this->applyUserFilter($incomeQuery, $userName);
        $this->applyUserFilter($expenseQuery, $userName);

        return response()->json([
            'income' => $incomeQuery->get(),
            'expense' => $expenseQuery->get(),
        ]);
    }

    public function bySemanticCategory(Request $request)
    {
        $userName = $this->getUserName($request);
        
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

        $this->applyUserFilter($incomeQuery, $userName);
        $this->applyUserFilter($expenseQuery, $userName);

        return response()->json([
            'income' => $incomeQuery->get(),
            'expense' => $expenseQuery->get(),
        ]);
    }

    public function byDate(Request $request)
    {
        $userName = $this->getUserName($request);
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

        $this->applyUserFilter($incomeQuery, $userName);
        $this->applyUserFilter($expenseQuery, $userName);

        return response()->json([
            'income' => $incomeQuery->get(),
            'expense' => $expenseQuery->get(),
        ]);
    }

    public function operations(Request $request)
    {
        $userName = $this->getUserName($request);
        $limit = min($request->get('limit', 50), 100);
        
        $query = Operation::orderByDesc('datatime')->limit($limit);
        
        $this->applyUserFilter($query, $userName);

        return response()->json(['data' => $query->get()]);
    }
}
