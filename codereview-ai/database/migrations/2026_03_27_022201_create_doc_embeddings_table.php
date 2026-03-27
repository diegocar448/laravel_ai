<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doc_embeddings', function (Blueprint $table) {
            $table->id();
            $table->string('source');       // "PSR-12", "OWASP Top 10", "Laravel 13 Docs"
            $table->string('title');
            $table->text('content');
            $table->vector('embedding', 768);  // pgvector! 768 dimensoes
            $table->string('category');     // architecture, performance, security
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doc_embeddings');
    }
};
