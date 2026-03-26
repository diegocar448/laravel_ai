<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('improvement_steps', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // ToDo, InProgress, Done
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('improvement_steps');
    }
};
