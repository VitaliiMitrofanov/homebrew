<?php

namespace App\Http\Controllers;

use SergiX44\Nutgram\Nutgram;

class TelegramWebhookController extends Controller
{
    public function handle(Nutgram $bot)
    {
        $bot->run();
        return response('OK');
    }
}
