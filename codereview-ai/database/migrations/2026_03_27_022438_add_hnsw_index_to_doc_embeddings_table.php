<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            CREATE INDEX doc_embeddings_embedding_idx
            ON doc_embeddings
            USING hnsw (embedding vector_cosine_ops)
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS doc_embeddings_embedding_idx');
    }
};
