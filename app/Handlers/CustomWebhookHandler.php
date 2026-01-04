<?php

namespace App\Handlers;

use Illuminate\Support\Stringable;
use DefStudio\Telegraph\Handlers\WebhookHandler;

use DefStudio\Telegraph\DTO\Document;
use DefStudio\Telegraph\DTO\Attachment;
use DefStudio\Telegraph\DTO\Photo;

use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Facades\Log;

use Spatie\PdfToText\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use InitRed\Tabula\Tabula;
use App\Models\Operation;

class CustomWebhookHandler extends WebhookHandler {
    protected function handleChatMessage(Stringable $text): void {

        $document = $this->message->document();
        
        if ($document instanceof Document) {
            $fileId = $document->id();
            $fileName = $document->filename();
            $fileMime = $document->mimeType();

            $response = $this->bot->getFileInfo($fileId)->send();
            $this->chat->html("Received document: ID=" . $fileId . ", Name=" . $fileName . ", MimeType=" . $fileMime)->send();

            if($fileMime != "application/pdf") {
                $this->chat->html("This is a " . $fileMime . ". Please send a PDF document.")->send();
                return;
            }
            $this->chat->html("Processing PDF document...")->send();

            $path = $this->bot->store($fileId, Storage::path("telegraph"));
            $content = Pdf::getText($path);
            $tabula = new Tabula('/usr/bin/');

            $tabula->setPdf($path)
                ->setOptions([
                    'format' => 'JSON',
                    'pages' => 'all',
                    'lattice' => false,
                    'stream' => true,
                    'outfile' => storage_path("app/public/test.JSON"),
                ])
                ->convert();

            $content = File::get(storage_path('app/public/test.JSON'));
            $json = json_decode($content, true);
            $collection = collect($json);
            $data = $collection->select('data')->all();
            
            $sum = [];
            $rowIndex = 0;
            foreach($data as $sheet) {
                $results = [];
                foreach ($sheet['data'] as $rotindex => $row) {

                    foreach ($row as $celindex => $cell) {
                        $results[$rowIndex][$celindex] = $cell['text'];
                    
                    }
                    $rowIndex++;
                }
                $sum = array_merge($sum, $results);
            }
            
            $results = [];
            $rowIndex = 0;
            foreach ($sum as $row) {
                //Ищем новую запись. Если у строки есть дата и время, то это оно
                //В четвертом столбце будет категория, а в пятом сумма
                if($row[0] != "" AND $row[1] != "") {
                    
                    $results[$rowIndex] = [
                        'date' => $row[0],
                        'time' => $row[1],
                        'aCode' => $row[2],
                        'category' => $row[3],
                        'description' => '',
                        'action' => str_contains($row[4], '+') ? 'income' : 'expense',
                        'amount' => floatval(str_replace(' ', '', $row[4]))
                    ];
                    $rowIndex++;
                }
                // Если не указано время во втором столбце, то это продолжение записи
                if($row[1] == "" ) {
                    $results[$rowIndex-1]['description'] .= ' ' . $row[3];
                }

            
            }
            $insertedRowsCount = 0;
            foreach ($results as $result) {
                $operation = Operation::firstOrcreate(
                     [
                        'datatime' => date('Y-m-d H:i:s', strtotime($result['date'] . ' ' . $result['time'])),
                        'aCode' => $result['aCode'],
                        'category' => $result['category'],
                        'action' => $result['action'],
                        'ammount' => $result['amount'],
                        'description' => trim($result['description']),
                     ]
                );
                if(!$operation->wasRecentlyCreated) {
                    LOG::info('Operation already exists: Было' . json_encode($operation) . ' Стало ' . json_encode($result));
                    continue; // Запись уже существует, пропускаем создание
                }
                if ($operation->wasRecentlyCreated) {
                    $insertedRowsCount++;
                }
            
            }
            $this->chat->html("Parsed " . count($results) . " rows. Inserted " . $insertedRowsCount . " new operations.")->send();


            return;
        }

        $photo = $this->message->photos()->first();
        if ($photo instanceof Photo) {
            $this->chat->html("Вы отправили фото с id: " . $photo->id())->send();
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
