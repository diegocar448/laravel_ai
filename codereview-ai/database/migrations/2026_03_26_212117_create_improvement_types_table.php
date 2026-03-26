<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('improvement_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Refactor, Fix, Optimization
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('improvement_types');
    }
};
