<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PerplexityService
{
    private string $apiKey;
    private string $endpoint = 'https://api.perplexity.ai/chat/completions';

    public function __construct()
    {
        $this->apiKey = config('services.perplexity.key');
    }

    public function generateSemanticCategory(string $category, string $description): ?string
    {
        $prompt = $this->buildPrompt($category, $description);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($this->endpoint, [
                'model' => 'sonar',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a financial categorization assistant. Your task is to analyze bank transaction data and provide a single, clear, humanized category name in Russian. Respond ONLY with the category name, nothing else. The category should be intuitive and user-friendly, like "Продукты и супермаркеты", "Рестораны и кафе", "Транспорт", "Переводы близким", "Коммунальные услуги", "Развлечения", "Одежда и обувь", "Здоровье и аптеки", "Подписки и сервисы", "Зарплата", "Инвестиции", "Прочие доходы", "Прочие расходы".'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.1,
                'max_tokens' => 50
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $result = $data['choices'][0]['message']['content'] ?? null;
                return $result ? trim($result) : null;
            }

            Log::error('Perplexity API error', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Perplexity API exception', [
                'message' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function generateBatchSemanticCategories(array $operations): array
    {
        $results = [];
        
        $operationsText = "";
        foreach ($operations as $index => $op) {
            $operationsText .= ($index + 1) . ". Категория: \"{$op['category']}\", Описание: \"{$op['description']}\"\n";
        }

        $prompt = "Проанализируй следующие банковские операции и дай для каждой одну понятную категорию на русском языке. Отвечай ТОЛЬКО в формате \"номер: категория\" для каждой строки.\n\n" . $operationsText;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($this->endpoint, [
                'model' => 'sonar',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a financial categorization assistant. Analyze bank transactions and provide humanized category names in Russian. Categories should be intuitive like: "Продукты", "Рестораны", "Транспорт", "Переводы", "Коммунальные услуги", "Развлечения", "Одежда", "Здоровье", "Подписки", "Зарплата", "Инвестиции", "Прочее". Respond ONLY in format "number: category" for each line.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.1,
                'max_tokens' => 500
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['choices'][0]['message']['content'] ?? '';
                
                $lines = explode("\n", $content);
                foreach ($lines as $line) {
                    if (preg_match('/^(\d+)[.:]\s*(.+)$/u', trim($line), $matches)) {
                        $index = (int)$matches[1] - 1;
                        $category = trim($matches[2]);
                        if (isset($operations[$index])) {
                            $results[$operations[$index]['id']] = $category;
                        }
                    }
                }
            } else {
                Log::error('Perplexity batch API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Perplexity batch API exception', [
                'message' => $e->getMessage()
            ]);
        }

        return $results;
    }

    private function buildPrompt(string $category, string $description): string
    {
        return "Банковская операция:\nКатегория: \"{$category}\"\nОписание: \"{$description}\"\n\nОпредели понятную категорию для этой операции.";
    }
}
