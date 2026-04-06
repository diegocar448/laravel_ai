<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('all required tables exist', function () {
    $tables = [
        'users', 'projects', 'code_reviews', 'review_findings',
        'improvements', 'doc_embeddings', 'project_statuses',
        'improvement_types', 'improvement_steps', 'review_statuses',
        'finding_types', 'review_pillars', 'jobs', 'failed_jobs',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Table {$table} does not exist");
    }
});

test('doc_embeddings has vector column', function () {
    expect(Schema::hasColumn('doc_embeddings', 'embedding'))->toBeTrue();
});

test('pgvector extension is enabled', function () {
    $result = DB::select("SELECT * FROM pg_available_extensions WHERE name = 'vector'");
    expect($result)->not->toBeEmpty();
});
