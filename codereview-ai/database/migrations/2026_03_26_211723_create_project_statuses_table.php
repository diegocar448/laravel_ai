<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Pending, Completed, Failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_statuses');
    }
};
