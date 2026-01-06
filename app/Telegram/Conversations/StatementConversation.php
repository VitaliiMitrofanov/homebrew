<?php

namespace App\Telegram\Conversations;

use App\Services\PDFProcessingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardRemove;

use SergiX44\Nutgram\Telegram\Types\Media\File;

class StatementConversation extends Conversation
{
    protected ?string $statementType = null;

    public function start(Nutgram $bot): void
    {
        $keyboard = InlineKeyboardMarkup::make()
            ->addRow(
                InlineKeyboardButton::make('Upload Sberbank statement', callback_data: 'statement:sberbank')
            )
            ->addRow(
                InlineKeyboardButton::make('Upload YaP statement', callback_data: 'statement:yap')
            )
            ->addRow(
                InlineKeyboardButton::make('Upload TBank statement', callback_data: 'statement:tbank')
            );

        $bot->sendMessage(
            text: 'Welcome! Choose a bank statement type to upload:',
            reply_markup: $keyboard
        );

        $this->next('waitForSelection');
    }

    public function waitForSelection(Nutgram $bot): void
    {
        $callbackData = $bot->callbackQuery()?->data;

        if ($callbackData && str_starts_with($callbackData, 'statement:')) {
            $this->statementType = str_replace('statement:', '', $callbackData);
            
            $bankNames = [
                'sberbank' => 'Sberbank',
                'yap' => 'YaP',
                'tbank' => 'TBank',
            ];

            $bankName = $bankNames[$this->statementType] ?? $this->statementType;
            
            $bot->answerCallbackQuery();
            $bot->sendMessage("Please upload your {$bankName} statement (PDF file). I'm waiting...");
            
            Log::info('Statement type selected', ['type' => $this->statementType]);
            
            $this->next('waitForDocument');
            return;
        }

        $bot->sendMessage('Please select a bank from the buttons above.');
    }

    public function waitForDocument(Nutgram $bot): void
    {
        $message = $bot->message();
        $document = $message?->document;
        $userName = $message?->from?->username ?? 'unknown';

        if (!$document) {
            $bot->sendMessage('Please send a PDF document.');
            return;
        }

        $mimeType = $document->mime_type ?? '';
        $fileName = $document->file_name ?? 'document.pdf';

        $bot->sendMessage("Received document from {$userName}:\nMimeType={$mimeType}");

    $bot->sendMessage(
        text: 'Removing keyboard...',
        reply_markup: ReplyKeyboardRemove::make(true),
    )?->delete();


        if ($mimeType !== 'application/pdf') {
            $bot->sendMessage("This is a {$mimeType}. Please send a PDF document.");
            return;
        }

        $file = $bot->getFile($document->file_id);

        LOG::info('File name: '. $file->file_path);
        LOG::info('File id: '. $file->file_id);
        

        $filePath = Storage::path("telegram/{$file->file_id}");
        LOG::info('File path: '. $filePath);
        $status = $bot->downloadFile($file, $filePath);
        
        LOG::info('Status: '. $status);
        /*
        if ($status) {
            $bot->sendMessage('Failed to download the file. Please try again.');
            return;
        }
*/
        Log::info('Processing PDF', ['type' => $this->statementType, 'path' => $filePath]);

        $processingService = new PDFProcessingService($userName, $this->statementType);
        
        $bankNames = [
            'sberbank' => 'Sberbank',
            'yap' => 'YaP',
            'tbank' => 'TBank',
        ];
        $bankName = $bankNames[$this->statementType] ?? $this->statementType;
        
        $bot->sendMessage("Processing {$bankName} statement PDF...");

        try {
            $processedData = match ($this->statementType) {
                'sberbank' => $processingService->parseSberbankPDF($filePath),
                'yap' => $processingService->parseYaPPDF($filePath),
                'tbank' => $processingService->parseTBankPDF($filePath),
                default => throw new \Exception('Unknown statement type'),
            };

            $bot->sendMessage('PDF processing completed.');
            $bot->sendMessage("Parsed {$processedData['parsed']} rows. Inserted {$processedData['loaded']} new operations.");
        } catch (\Exception $e) {
            Log::error('PDF processing failed', ['error' => $e->getMessage()]);
            $bot->sendMessage("Error processing PDF: {$e->getMessage()}");
        }

        $this->end();
    }
}
