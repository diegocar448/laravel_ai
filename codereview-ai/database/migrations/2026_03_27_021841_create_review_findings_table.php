<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('code_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('finding_type_id')->constrained();
            $table->foreignId('review_pillar_id')->constrained();
            $table->text('description');
            $table->string('severity')->default('medium'); // low, medium, high, critical
            $table->dateTime('agent_flagged_at')->nullable();
            $table->dateTime('user_flagged_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_findings');
    }
};
