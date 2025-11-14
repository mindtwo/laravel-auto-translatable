<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mindtwo\AutoTranslatable\Enums\TranslationStatus;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_results', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('translatable');
            $table->string('field_name')->nullable();
            $table->string('source_locale');
            $table->string('target_locale');
            $table->longText('source_content');
            $table->longText('translated_content')->nullable();
            $table->integer('chunks_count')->default(1);
            $table->json('metadata')->nullable();
            $table->string('status')->default(TranslationStatus::PENDING->value);
            $table->text('error_message')->nullable();
            $table->timestamps();

            // Indexes for common queries
            $table->index(['translatable_type', 'translatable_id', 'field_name', 'target_locale'], 'translation_lookup');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_results');
    }
};
