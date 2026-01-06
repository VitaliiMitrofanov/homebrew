<?php

namespace App\Handlers;

use DefStudio\Telegraph\Handlers\WebhookHandler;

use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;

use DefStudio\Telegraph\DTO\Document;

use App\Models\Operation;
use App\Services\PDFProcessingService;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use InitRed\Tabula\Tabula;

use Illuminate\Support\Facades\Log;


class CustomWebhookHandler extends WebhookHandler
{
    private $actionList = [
        'statement' => 'statement',
        'sberbank' => ["Please upload your Sberbank statement (PDF file). I'm waiting..."],
        'yap' => ["Please upload your YaP statement (PDF file). I'm waiting..."],
        'tbank' => ["Please upload your TBank statement (PDF file). I'm waiting..."],
    ];

    public function statement(): void
    {
        $this
            ->chat
            ->markdown('Welcome! Choose an action:')
            ->keyboard(Keyboard::make()->buttons([
                Button::make('Upload Sberbank statement')->action('uploadStatement')->param('statement', 'sberbank'),
                Button::make('Upload YaP statement')->action('uploadStatement')->param('statement', 'yap'),
                Button::make('Upload TBank statement')->action('uploadStatement')->param('statement', 'tbank'),
            ]))
            ->send();
    }

    public function uploadStatement(): void
    {
        $statement = $this->data->get('statement');
        $this->chat->markdown($this->actionList[$statement][0])->send();

        $cacheKey = 'tg:callback:statement:' . $this->chat->id;
        Cache::put($cacheKey, $statement, now()->addMinutes(10));

        LOG::info('Step 1 - prompt sent');
    }

    protected function handleChatMessage(Stringable $text): void
    {
        LOG::info('Step 2 - message received');
        $cacheKey = 'tg:callback:statement:' . $this->chat->id;
        $statement = Cache::get($cacheKey);
        LOG::info('Selected statement: ' . $statement);
        $document = $this->message->document();
        $userName = $this->message->from()->username();

        if ($document instanceof Document) {
            $fileId = $document->id();
            $fileName = $document->filename();
            $fileMime = $document->mimeType();

            $this->chat->html("Received document from " . $userName . ":\nMimeType=" . $fileMime)->send();

            if ($fileMime != 'application/pdf' && !$statement) {
                $this->chat->html('This is a ' . $fileMime . '. Please send a PDF document. Or i forget what ive asked for.')->send();
                return;
            }
            $path = $this->bot->store($fileId, Storage::path('telegraph'));

            $processingService = new PDFProcessingService($userName, $statement);
            switch ($statement) {
                case 'sberbank':
                    $this->chat->html('Processing Sberbank statement PDF...')->send();

                    $processedData = $processingService->parseSberbankPDF($path);
                    
                    break;
                case 'yap':
                    $this->chat->html('Processing YaP statement PDF...')->send();

                    $processedData = $processingService->parseYaPPDF($path);
                    
                    break;
                case 'tbank':
                    $this->chat->html('Processing TBank statement PDF...')->send(); 

                    $processedData = $processingService->parseTBankPDF($path);

                    break;
                default:
                    $this->chat->html('Unknown statement type.')->send();
                    return;
            }

            Cache::forget($cacheKey);
            $this->chat->html('PDF processing completed.')->send();
            $this->chat->html("Parsed " . $processedData['parsed'] . " rows. Inserted " . $processedData['loaded'] . " new operations.")->send();
            return;
        }

        // полученное сообщение отправляется обратно в чат
        $this->chat->html("Вы написали: $text")->send();
    }

    public function hi(string $userName)
    {
        $this->chat->markdown("*Hi* $userName, happy to be here!")->send();
    }
}
