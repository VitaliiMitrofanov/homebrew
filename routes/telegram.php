<?php

use App\Telegram\Conversations\StatementConversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\WebApp\WebAppInfo;

$miniAppUrl = env('APP_URL', 'localhost') . '/telegram-mini-app';

$bot->onCommand('start', function (Nutgram $bot) use ($miniAppUrl) {
    $keyboard = InlineKeyboardMarkup::make()
        ->addRow(
            InlineKeyboardButton::make(
                text: 'Открыть аналитику',
                web_app: new WebAppInfo($miniAppUrl)
            )
        );
    
    $bot->sendMessage(
        text: "Добро пожаловать в Bank Statement Processor!\n\nДоступные команды:\n/statement - загрузить выписку\n/analytics - открыть аналитику",
        reply_markup: $keyboard
    );
})->description('Start the bot');

$bot->onCommand('statement', StatementConversation::class)
    ->description('Upload a bank statement');

$bot->onCommand('analytics', function (Nutgram $bot) use ($miniAppUrl) {
    $keyboard = InlineKeyboardMarkup::make()
        ->addRow(
            InlineKeyboardButton::make(
                text: 'Открыть аналитику',
                web_app: new WebAppInfo($miniAppUrl)
            )
        );
    
    $bot->sendMessage(
        text: 'Нажмите кнопку ниже, чтобы открыть финансовую аналитику:',
        reply_markup: $keyboard
    );
})->description('View financial analytics');
