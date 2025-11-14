<?php

namespace Mindtwo\AutoTranslatable\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Mindtwo\AutoTranslatable\Enums\TranslationStatus;

/**
 * @property int $id
 * @property ?string $translatable_type
 * @property ?int $translatable_id
 * @property ?string $field_name
 * @property string $source_locale
 * @property string $target_locale
 * @property string $source_content
 * @property string $translated_content
 * @property int $chunks_count
 * @property ?array $metadata
 * @property TranslationStatus $status
 * @property ?string $error_message
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static Builder<static>|TranslationResult newModelQuery()
 * @method static Builder<static>|TranslationResult newQuery()
 * @method static Builder<static>|TranslationResult query()
 *
 * @mixin Eloquent
 */
class TranslationResult extends Model
{
    /** {@inheritDoc} */
    protected $table = 'translation_results';

    /** {@inheritDoc} */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /** {@inheritDoc} */
    protected $casts = [
        'metadata' => 'array',
        'chunks_count' => 'integer',
        'status' => TranslationStatus::class,
    ];

    /**
     * Get the owning translatable model
     */
    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Check if translation is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === TranslationStatus::COMPLETED;
    }

    /**
     * Check if translation failed.
     */
    public function isFailed(): bool
    {
        return $this->status === TranslationStatus::FAILED;
    }

    /**
     * Check if translation is still pending.
     */
    public function isPending(): bool
    {
        return $this->status === TranslationStatus::PENDING;
    }

    /**
     * Check if translation is currently processing.
     */
    public function isProcessing(): bool
    {
        return $this->status === TranslationStatus::PROCESSING;
    }

    /**
     * Mark translation as processing.
     */
    public function markAsProcessing(): void
    {
        $this->update(['status' => TranslationStatus::PROCESSING]);
    }

    /**
     * Mark translation as completed.
     */
    public function markAsCompleted(string $content, array $metadata = []): void
    {
        $this->update([
            'status' => TranslationStatus::COMPLETED,
            'translated_content' => $content,
            'metadata' => array_merge($this->metadata ?? [], $metadata),
        ]);
    }

    /**
     * Mark translation as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => TranslationStatus::FAILED,
            'error_message' => $errorMessage,
        ]);
    }
}
