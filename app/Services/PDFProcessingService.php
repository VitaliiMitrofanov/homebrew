<?php
namespace App\Services;

use App\Models\Operation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use InitRed\Tabula\Tabula;
use DateTime;

class PDFProcessingService
{
    private Tabula $tabula;
    private array $parcedData;
    private string $userName;
    private string $statement;

    public function __construct($userName, $statement)
    {
        $this->userName = $userName;
        $this->statement = $statement;
        $this->tabula = new Tabula('/usr/bin/');
    }

    function isTime(string $value): bool
    {
        $value = trim($value, 'в ');
        $dt = DateTime::createFromFormat('H:i', $value);
        return $dt && $dt->format('H:i') === $value;
    }

    function isDate(string $value): bool
    {
        $dt = DateTime::createFromFormat('d.m.Y', $value);
        return $dt && $dt->format('d.m.Y') === $value;
    }

    function parseMoney(string $value): int|false
    {
        $clean = trim($value);
        $clean = str_replace(',', '.', $clean);
        $pos = strpos($clean, '.');  // позиция первой точки
        if ($pos === false) {
            return false;
        } else {
            $clean = substr($clean, 0, $pos);
        }
        $clean = preg_replace('/\D+/', '', $clean);
        // запоминаем, был ли минус
        return (int) $clean;
    }

    function hasOnlyOneString(array $arr): bool
    {
        // Оставляем только элементы, которые являются строками и не пусты
        $nonEmptyStrings = array_filter($arr, function ($v) {
            return is_string($v) && $v !== '';
        });

        // Условие: ровно один такой элемент
        // и в исходном массиве нет значений, которые не строка и не ''
        return count($nonEmptyStrings) === 1 &&
            count($nonEmptyStrings) + count(
                array_filter($arr, fn($v) => $v === '')
            ) === count($arr);
    }

    private function detectCategory(array $operation): string
    {
        $rules = config('operation_categories');
        $description = mb_strtoupper($operation['description'] ?? '');
        $action = $operation['action'] ?? null;  // income/expense

        if (empty($description) || !isset($rules[$action])) {
            return 'noCat';
        }

        foreach ($rules[$action] as $categoryName => $rule) {
            foreach ($rule['contains'] as $needle) {
                if (mb_stripos($description, mb_strtoupper($needle)) !== false) {
                    return $categoryName;
                }
            }
        }

        return 'noCat';
    }

    public function loadDataInDatabase()
    {
        $insertedRowsCount = 0;

        foreach ($this->parsedData as $result) {
            $operation = Operation::firstOrcreate(
                [
                    'datatime' => date('Y-m-d H:i:s', strtotime($result['date'] . ' ' . $result['time'])),
                    'aCode' => $result['aCode'],
                    'category' => $result['category'],
                    'action' => $result['action'],
                    'ammount' => $result['amount'],
                    'description' => trim($result['description']),
                    'username' => $this->userName,
                    'data_source' => $this->statement,
                ]
            );
            if (!$operation->wasRecentlyCreated) {
                continue;  // Запись уже существует, пропускаем создание
            }
            if ($operation->wasRecentlyCreated) {
                $insertedRowsCount++;
            }
        }
        return $insertedRowsCount;
    }

    public function parseSberbankPDF($filePath)
    {
        $this
            ->tabula
            ->setPdf($filePath)
            ->setOptions([
                'format' => 'JSON',
                'pages' => 'all',
                'lattice' => false,
                'stream' => true,
                'outfile' => storage_path('app/public/parsed_data_sber.JSON'),
            ])
            ->convert();
        $content = File::get(storage_path('app/public/parsed_data_sber.JSON'));
        $json = json_decode($content, true);
        $collection = collect($json);
        $data = $collection->select('data')->all();

        $sum = [];
        $rowIndex = 0;
        foreach ($data as $sheet) {
            $results = [];
            foreach ($sheet['data'] as $rotindex => $row) {
                foreach ($row as $celindex => $cell) {
                    $results[$rowIndex][$celindex] = $cell['text'];
                }
                $rowIndex++;
            }
            $sum = array_merge($sum, $results);
        }

        $rowIndex = 0;
        foreach ($sum as $row) {
            // Ищем новую запись. Если у строки есть дата и время, то это оно
            // В четвертом столбце будет категория, а в пятом сумма
            if ($row[0] != '' AND $row[1] != '') {
                $this->parsedData[$rowIndex] = [
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
            if ($row[1] == '') {
                $this->parsedData[$rowIndex - 1]['description'] .= ' ' . $row[3];
            }
        }

        File::delete(storage_path('app/public/parsed_data_sber.JSON'));

        $parsed = count($this->parsedData);
        $loadData = $this->loadDataInDatabase();

        return ['parsed' => $parsed, 'loaded' => $loadData];  // Возвращаем разобранные данные
    }

    public function parseYaPPDF($filePath)
    {
        // Логика парсинга PDF Яндекс.Практикум
        $this
            ->tabula
            ->setPdf($filePath)
            ->setOptions([
                'format' => 'JSON',
                'guess' => false,
                'pages' => 'all',
                'columns' => [1.111, 198.333, 277.171, 341.877, 393.196, 500.296],
                'outfile' => storage_path('app/public/parsed_data_yap.JSON'),
            ])
            ->convert();

        $content = File::get(storage_path('app/public/parsed_data_yap.JSON'));
        $json = json_decode($content, true);
        $collection = collect($json);
        $data = $collection->select('data')->all();

        $sum = [];
        $rowIndex = 0;
        foreach ($data as $sheet) {
            $results = [];
            foreach ($sheet['data'] as $rotindex => $row) {
                foreach ($row as $celindex => $cell) {
                    $results[$rowIndex][$celindex] = $cell['text'];
                }
                $rowIndex++;
            }
            $sum = array_merge($sum, $results);
        }
        $currentOperation = NULL;
        foreach ($sum as $row) {
            $money = $this->parseMoney($row[5]);
            if ($this->isDate($row[2]) && is_integer($money)) {
                // Это новая строка. Сохраняем накопленные данные и создаем новый массив
                if ($currentOperation !== NULL) {
                    $currentOperation['category'] = $this->detectCategory($currentOperation);
                    $this->parsedData[] = $currentOperation;
                }
                $currentOperation = [
                    'date' => $row[2],
                    'time' => '',
                    'aCode' => 'yap',
                    'category' => '',
                    'description' => '',
                    'action' => str_contains($row[5], '+') ? 'income' : 'expense',
                    'amount' => $money
                ];
            }
            if ($currentOperation !== NULL && $this->isTime($row[2])) {
                $currentOperation['time'] = trim($row[2], 'в ');
            }
            if ($currentOperation !== NULL && $row[1] != 'Описание операции') {
                $currentOperation['description'] .= ' ' . $row[1];
            }
        }

        File::delete(storage_path('app/public/parsed_data_yap.JSON'));

        $parsed = count($this->parsedData);
        $loadData = $this->loadDataInDatabase();

        return ['parsed' => $parsed, 'loaded' => $loadData];  // Возвращаем разобранные данные
    }

    public function parseTBankPDF($filePath)
    {
        // Логика парсинга PDF TBank
        $this
            ->tabula
            ->setPdf($filePath)
            ->setOptions([
                'format' => 'JSON',
                'pages' => 'all',
                'Stream' => true,
                'outfile' => storage_path('app/public/parsed_data_tbank_all_sheets.JSON'),
            ])
            ->convert();
        $this
            ->tabula
            ->setPdf($filePath)
            ->setOptions([
                'format' => 'JSON',
                'pages' => '1',
                'area' => '291.178,52.558,738.916,547.152',
                'outfile' => storage_path('app/public/parsed_data_tbank_page1_area.JSON'),
            ])
            ->convert();
        $content_page1_area = File::get(storage_path('app/public/parsed_data_tbank_page1_area.JSON'));
        $content_all_sheets = File::get(storage_path('app/public/parsed_data_tbank_all_sheets.JSON'));
        $content = array_merge(json_decode($content_page1_area, true), json_decode($content_all_sheets, true));

        $collection = collect($content);
        $data = $collection->select('data')->all();

        $sum = [];
        $rowIndex = 0;
        foreach ($data as $sheet) {
            $results = [];
            foreach ($sheet['data'] as $rotindex => $row) {
                foreach ($row as $celindex => $cell) {
                    $results[$rowIndex][$celindex] = $cell['text'];
                }
                $rowIndex++;
            }
            $sum = array_merge($sum, $results);
        }
        $currentOperation = NULL;

        foreach ($sum as $row) {
            // Ищем новую запись. Если у строки есть дата и денежная сумма, то это оно
            // В четвертом столбце будет категория, а в пятом сумма
            $money = $this->parseMoney($row[2]);
            if ($this->isDate($row[0]) && is_integer($money)) {
                if ($currentOperation !== NULL) {
                    $currentOperation['category'] = $this->detectCategory($currentOperation);
                    $this->parsedData[] = $currentOperation;
                }
                $currentOperation = [
                    'date' => $row[0],
                    'time' => '',
                    'aCode' => 'tbank',
                    'category' => $row[4],
                    'description' => $row[4],
                    'action' => str_contains($row[2], '+') ? 'income' : 'expense',
                    'amount' => $money
                ];
            }
            if ($currentOperation !== NULL && $this->isTime($row[0])) {
                $currentOperation['time'] = $row[0];
            }
            if ($currentOperation !== NULL && $row[4] != '') {
                $currentOperation['description'] .= ' ' . $row[4];
            }
        }

        File::delete(storage_path('app/public/parsed_data_tbank_page1_area.JSON'));
        File::delete(storage_path('app/public/parsed_data_tbank_all_sheets.JSON'));

        $parsed = count($this->parsedData);
        $loadData = $this->loadDataInDatabase();

        return ['parsed' => $parsed, 'loaded' => $loadData];  // Возвращаем разобранные данные
    }
}
