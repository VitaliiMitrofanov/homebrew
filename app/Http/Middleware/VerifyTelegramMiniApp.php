<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Closure;

class VerifyTelegramMiniApp
{
    public function handle(Request $request, Closure $next): Response
    {

        $initData = $request->header('X-Telegram-Init-Data', '');

        if (empty($initData)) {
            $initData = $request->query('init_data', '');
        }

        if (empty($initData)) {
            return response()->json(['error' => 'Unauthorized - No init data'], 401);
        }
        $botToken = config('nutgram.token');

        if (empty($botToken)) {
            return response()->json(['error' => 'Server configuration error'], 500);
        }
        if (!$this->validateTelegramInitData($initData, $botToken)) {
            return response()->json(['error' => 'Unauthorized - Invalid init data'], 401);
        }
        $parsedData = $this->parseInitData($initData);
        $request->attributes->set('telegram_user', $parsedData['user'] ?? null);
        $request->attributes->set('telegram_init_data', $parsedData);
        return $next($request);
    }

    private function validateTelegramInitData(string $initData, string $botToken): bool
    {
        parse_str($initData, $data);

        if (!isset($data['hash'])) {
            return false;
        }

        $hash = $data['hash'];
        unset($data['hash']);

        ksort($data);
        $dataCheckString = collect($data)
            ->map(fn($value, $key) => "{$key}={$value}")
            ->implode("\n");

        $checkString = collect($data)
            ->map(fn($v, $k) => $k . '=' . $v)
            ->implode("\n");

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $expectedHash = hash_hmac('sha256', $checkString, $secretKey);

        if (!hash_equals($expectedHash, $hash)) {
            return false;
        }
        return true;
    }

    private function parseInitData(string $initData): array
    {
        parse_str($initData, $data);
        LOG::info($data);
        if (isset($data['user'])) {
            $data['user'] = json_decode(urldecode($data['user']), true);
        }

        return $data;
    }
}
