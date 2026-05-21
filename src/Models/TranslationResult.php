<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Mindtwo\AutoTranslatable\Enums\TranslationStatus;

/**
 * @property int $id
 * @property string|null $translatable_type
 * @property int|null $translatable_id
 * @property string|null $field_name
 * @property string $source_locale
 * @property string $target_locale
 * @property string $source_content
 * @property string|null $translated_content
 * @property int $chunks_count
 * @property array<string, mixed>|null $metadata
 * @property TranslationStatus $status
 * @property string|null $error_message
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static Builder<static>|TranslationResult newModelQuery()
 * @method static Builder<static>|TranslationResult newQuery()
 * @method static Builder<static>|TranslationResult query()
 */
class TranslationResult extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'translation_results';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'chunks_count' => 'integer',
        'status' => TranslationStatus::class,
    ];

    /**
     * Get the owning translatable model.
     *
     * @return MorphTo<Model, $this>
     */
    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Determine if the translation has completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === TranslationStatus::COMPLETED;
    }

    /**
     * Determine if the translation has failed.
     *
     * @codeCoverageIgnore
     */
    public function isFailed(): bool
    {
        return $this->status === TranslationStatus::FAILED;
    }

    /**
     * Determine if the translation is still pending.
     *
     * @codeCoverageIgnore
     */
    public function isPending(): bool
    {
        return $this->status === TranslationStatus::PENDING;
    }

    /**
     * Determine if the translation is currently processing.
     *
     * @codeCoverageIgnore
     */
    public function isProcessing(): bool
    {
        return $this->status === TranslationStatus::PROCESSING;
    }

    /**
     * Mark the translation as processing.
     */
    public function markAsProcessing(): void
    {
        $this->update(['status' => TranslationStatus::PROCESSING]);
    }

    /**
     * Mark the translation as completed and persist the translated content.
     *
     * @param array<string, mixed> $metadata
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
     * Mark the translation as failed and persist the error message.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => TranslationStatus::FAILED,
            'error_message' => $errorMessage,
        ]);
    }
}
