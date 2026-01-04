<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations', function (Blueprint $table) {
            $table->id(); // id INT AUTO_INCREMENT PRIMARY KEY
            $table->dateTime('datatime'); // DATETIME
            $table->string('category', 100); // VARCHAR(100)
            $table->string('description', 255)->nullable(); // VARCHAR(255)
            $table->string('action', 100)->nullable(); // VARCHAR(100)
            $table->decimal('ammount', 10, 2)->nullable(); // DECIMAL(10,2)
            $table->timestamps(); // created_at, updated_at (по желанию)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations');
    }
};
