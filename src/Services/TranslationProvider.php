<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Services;

use LaravelLang\NativeLocaleNames\LocaleNames;
use Mindtwo\AutoTranslatable\Support\Config;
use Prism\Prism\Facades\Prism;

class TranslationProvider
{
    /**
     * Translate a single chunk through the configured PRISM provider.
     *
     * @param array<string, mixed> $options
     */
    public function translateChunk(
        string $content,
        string $sourceLocale,
        string $targetLocale,
        array $options,
    ): string {
        $provider = Config::string('auto-translatable.provider');
        $model = Config::string('auto-translatable.model');
        $outputTokens = Config::int('auto-translatable.output_tokens', 100000);

        $prompt = $this->buildPrompt($content, $sourceLocale, $targetLocale, $options);
        $strategy = $options['chunking_strategy'] ?? 'none';
        $systemPrompt = $strategy === 'markdown'
            ? $this->buildSystemPromptMarkdown()
            : $this->buildSystemPromptPlain();

        $response = Prism::text()
            ->withClientOptions(['timeout' => 500])
            ->using($provider, $model)
            ->withSystemPrompt($systemPrompt)
            ->withPrompt($prompt)
            ->withMaxTokens($outputTokens)
            ->asText();

        return mb_trim($response->text);
    }

    /**
     * Build the system prompt for plain-text translation.
     */
    protected function buildSystemPromptPlain(): string
    {
        return <<<'PROMPT'
            You are a precise technical translator specializing in text content.

            Your translation rules:
            1. Translate text 1:1 - no semantic adjustments, no cuts, no additions, no explanations
            2. Preserve ALL original syntax exactly: [links](url), **bold**, *italic*, `code`, etc.
            3. Never translate: URLs, code blocks, inline code, HTML tags, image paths
            4. Maintain paragraph structure and line breaks exactly as in the source
            5. Keep technical terms accurate
            6. Preserve all whitespace and formatting
            7. Do not add any commentary or notes - only output the translated content
            PROMPT;
    }

    /**
     * Build the system prompt for markdown translation.
     */
    protected function buildSystemPromptMarkdown(): string
    {
        return <<<'PROMPT'
            You are a precise technical translator specializing in markdown content.

            Your translation rules:
            1. Translate text 1:1 - no semantic adjustments, no cuts, no additions, no explanations
            2. Preserve ALL markdown syntax exactly: [links](url), **bold**, *italic*, `code`, etc.
            3. Never translate: URLs, code blocks, inline code, HTML tags, image paths
            4. Maintain paragraph structure and line breaks exactly as in the source
            5. Keep technical terms accurate
            6. Preserve all whitespace and formatting
            7. Do not add any commentary or notes - only output the translated markdown
            8. Do not output any additional horizontal rules

            The output must be valid markdown that can be parsed identically to the source, just in a different language.
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
