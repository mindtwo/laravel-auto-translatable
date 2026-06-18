<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Services;

use Illuminate\Database\Eloquent\Model;
use Laravel\Ai\Responses\StructuredAgentResponse;
use LaravelLang\NativeLocaleNames\LocaleNames;
use Mindtwo\AutoTranslatable\Enums\TranslationApiCallKind;
use Mindtwo\AutoTranslatable\Events\TranslationApiCallCompleted;

class TranslationProvider
{
    /**
     * Translate a single chunk through the configured AI provider.
     *
     * @param array<string, mixed> $options
     * @param Model|null $translatable model being translated, for usage attribution (null for model-less calls)
     */
    public function translateChunk(
        string $content,
        string $sourceLocale,
        string $targetLocale,
        array $options,
        ?Model $translatable = null,
    ): string {
        $prompt = $this->buildPrompt($content, $sourceLocale, $targetLocale, $options);
        $strategy = $options['chunking_strategy'] ?? 'none';
        $systemPrompt = $strategy === 'markdown'
            ? $this->buildSystemPromptMarkdown()
            : $this->buildSystemPromptPlain();

        $response = (new TranslationAgent($systemPrompt))->prompt($prompt);

        TranslationApiCallCompleted::dispatch(
            TranslationApiCallKind::Chunk,
            $sourceLocale,
            $targetLocale,
            $response->usage,
            $response->meta->provider,
            $response->meta->model,
            [],
            $translatable,
        );

        return mb_trim($response->text);
    }

    /**
     * Translate multiple fields in a single structured request.
     *
     * Returns a map of field => translated content. Fields that the model
     * omits from its structured response are left out of the result, so the
     * caller can decide how to recover them.
     *
     * @param array<string, string> $fields field => source content
     * @param array<string, mixed> $options
     * @param Model|null $translatable model being translated, for usage attribution (null for model-less calls)
     *
     * @return array<string, string>
     */
    public function translateFields(
        array $fields,
        string $sourceLocale,
        string $targetLocale,
        array $options,
        ?Model $translatable = null,
    ): array {
        $prompt = $this->buildFieldsPrompt($fields, $sourceLocale, $targetLocale, $options);

        $response = (new StructuredTranslationAgent($this->buildSystemPromptStructured(), array_keys($fields)))
            ->prompt($prompt);

        TranslationApiCallCompleted::dispatch(
            TranslationApiCallKind::Fields,
            $sourceLocale,
            $targetLocale,
            $response->usage,
            $response->meta->provider,
            $response->meta->model,
            array_keys($fields),
            $translatable,
        );

        $structured = $response instanceof StructuredAgentResponse ? $response->structured : [];

        $translated = [];

        foreach (array_keys($fields) as $field) {
            $value = $structured[$field] ?? null;

            if (is_string($value) && $value !== '') {
                $translated[$field] = mb_trim($value);
            }
        }

        return $translated;
    }

    /**
     * The translation rules shared by every system prompt variant.
     */
    protected function translationRules(): string
    {
        return <<<'PROMPT'
            1. Translate text 1:1 - no semantic adjustments, no cuts, no additions, no explanations
            2. Preserve ALL original syntax exactly: [links](url), **bold**, *italic*, `code`, etc.
            3. Never translate: URLs, code blocks, inline code, HTML tags, image paths
            4. Maintain paragraph structure and line breaks exactly as in the source
            5. Keep technical terms accurate
            6. Preserve all whitespace and formatting
            PROMPT;
    }

    /**
     * Build the system prompt for plain-text translation.
     */
    protected function buildSystemPromptPlain(): string
    {
        $rules = $this->translationRules();

        return <<<PROMPT
            You are a precise technical translator specializing in text content.

            Your translation rules:
            {$rules}
            7. Do not add any commentary or notes - only output the translated content
            PROMPT;
    }

    /**
     * Build the system prompt for markdown translation.
     */
    protected function buildSystemPromptMarkdown(): string
    {
        $rules = $this->translationRules();

        return <<<PROMPT
            You are a precise technical translator specializing in markdown content.

            Your translation rules:
            {$rules}
            7. Do not add any commentary or notes - only output the translated markdown
            8. Do not output any additional horizontal rules

            The output must be valid markdown that can be parsed identically to the source, just in a different language.
            PROMPT;
    }

    /**
     * Build the system prompt for batched, structured translation of multiple fields.
     */
    protected function buildSystemPromptStructured(): string
    {
        $rules = $this->translationRules();

        return <<<PROMPT
            You are a precise technical translator translating the fields of a single record.

            Your translation rules:
            {$rules}
            7. Translate only the field values, never the field names
            8. Return every field under its exact key in the structured response
            9. Keep terminology consistent across all fields of the record
            PROMPT;
    }

    /**
     * Build the user prompt for the translation request.
     *
     * @param array<string, mixed> $options
     */
    protected function buildPrompt(
        string $content,
        string $sourceLocale,
        string $targetLocale,
        array $options,
    ): string {
        $sourceLanguage = $this->getLanguageName($sourceLocale);
        $targetLanguage = $this->getLanguageName($targetLocale);

        $prompt = '';

        if (isset($options['prompt_additions']) && is_scalar(
            $options['prompt_additions'],
        ) && $options['prompt_additions']) {
            $prompt .= "\n".(string) $options['prompt_additions'];
        }

        $prompt .= ($options['chunking_strategy'] ?? 'none') === 'markdown'
            ? "Translate the following markdown content from {$sourceLanguage} to {$targetLanguage}:\n"
            : "Translate the following content from {$sourceLanguage} to {$targetLanguage}:\n";

        return $prompt.$content;
    }

    /**
     * Build the user prompt that lists every field to translate in one request.
     *
     * @param array<string, string> $fields
     * @param array<string, mixed> $options
     */
    protected function buildFieldsPrompt(
        array $fields,
        string $sourceLocale,
        string $targetLocale,
        array $options,
    ): string {
        $sourceLanguage = $this->getLanguageName($sourceLocale);
        $targetLanguage = $this->getLanguageName($targetLocale);

        $prompt = '';

        if (isset($options['prompt_additions']) && is_scalar(
            $options['prompt_additions'],
        ) && $options['prompt_additions']) {
            $prompt .= (string) $options['prompt_additions']."\n";
        }

        $prompt .= "Translate each of the following fields from {$sourceLanguage} to {$targetLanguage}.\n";

        foreach ($fields as $field => $content) {
            $prompt .= "\n=== FIELD: {$field} ===\n{$content}\n";
        }

        return $prompt;
    }

    /**
     * Get the English language name for the given locale code.
     */
    protected function getLanguageName(string $locale): string
    {
        $names = LocaleNames::get('en');

        return isset($names[$locale]) && is_string($names[$locale])
            ? $names[$locale]
            : ucfirst($locale);
    }
}
