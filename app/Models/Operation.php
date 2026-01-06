<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Operation extends Model
{
    use HasFactory;

    // Явно укажем таблицу (можно опустить, если имя по конвенции)
    protected $table = 'operations';

    // Разрешённые к массовому заполнению поля
    protected $fillable = [
        'datatime',
        'category',
        'description',
        'action',
        'ammount',
        'aCode',
        'username',
        'data_source',
    ];

}
