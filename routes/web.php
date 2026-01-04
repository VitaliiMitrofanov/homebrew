<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

use App\Models\Operation;

Route::get('/test', function () {
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
    foreach ($results as $result) {
        $operation = Operation::firstOrcreate(
            [
                'datatime' => date('Y-m-d H:i:s', strtotime($result['date'] . ' ' . $result['time'])),
                'category' => $result['category'],
                'action' => $result['action'],
            ],[
                'description' => trim($result['description']),
                'ammount' => $result['amount'],
            ]
        );
        if (!$operation->wasRecentlyCreated) {
            continue; // Запись уже существует, пропускаем создание
        }
    }

    //LOG::info("Parsed data: " . print_r($results, true));

    return response()->json($results);
});

Route::get('/operations', function () {
    $operations = Operation::all();
    return response()->json($operations);
});