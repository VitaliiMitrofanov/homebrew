<?php

namespace App\Http\Controllers;

use App\Models\Operation;
use App\Services\PerplexityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SemanticCategoryController extends Controller
{
    private PerplexityService $perplexityService;

    public function __construct(PerplexityService $perplexityService)
    {
        $this->perplexityService = $perplexityService;
    }

    public function populate(Request $request)
    {
        $limit = $request->get('limit', 50);
        $forceUpdate = $request->get('force', false);

        $query = Operation::query();
        
        if (!$forceUpdate) {
            $query->whereNull('semantic_category')
                  ->orWhere('semantic_category', '');
        }

        $operations = $query->limit($limit)->get();

        if ($operations->isEmpty()) {
            return response()->json([
                'message' => 'No operations to process',
                'processed' => 0
            ]);
        }

        $processed = 0;
        $failed = 0;
        $batchSize = 10;

        $chunks = $operations->chunk($batchSize);

        foreach ($chunks as $chunk) {
            $batchData = $chunk->map(function ($op) {
                return [
                    'id' => $op->id,
                    'category' => $op->category ?? '',
                    'description' => $op->description ?? ''
                ];
            })->toArray();

            $results = $this->perplexityService->generateBatchSemanticCategories($batchData);

            foreach ($chunk as $operation) {
                if (isset($results[$operation->id])) {
                    $operation->semantic_category = $results[$operation->id];
                    $operation->save();
                    $processed++;
                } else {
                    $semanticCategory = $this->perplexityService->generateSemanticCategory(
                        $operation->category ?? '',
                        $operation->description ?? ''
                    );

                    if ($semanticCategory) {
                        $operation->semantic_category = $semanticCategory;
                        $operation->save();
                        $processed++;
                    } else {
                        $failed++;
                    }
                }
            }

            usleep(500000);
        }

        return response()->json([
            'message' => 'Semantic categorization completed',
            'processed' => $processed,
            'failed' => $failed,
            'remaining' => Operation::whereNull('semantic_category')->orWhere('semantic_category', '')->count()
        ]);
    }

    public function status()
    {
        $total = Operation::count();
        $withSemantic = Operation::whereNotNull('semantic_category')
            ->where('semantic_category', '!=', '')
            ->count();
        $withoutSemantic = $total - $withSemantic;

        $semanticCategories = Operation::whereNotNull('semantic_category')
            ->where('semantic_category', '!=', '')
            ->selectRaw('semantic_category, COUNT(*) as count')
            ->groupBy('semantic_category')
            ->orderByDesc('count')
            ->get();

        return response()->json([
            'total_operations' => $total,
            'with_semantic_category' => $withSemantic,
            'without_semantic_category' => $withoutSemantic,
            'progress_percent' => $total > 0 ? round(($withSemantic / $total) * 100, 1) : 0,
            'semantic_categories' => $semanticCategories
        ]);
    }
}
