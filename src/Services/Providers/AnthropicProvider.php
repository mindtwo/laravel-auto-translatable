<?php

namespace Mindtwo\AutoTranslatable\Services\Providers;

use EchoLabs\Prism\Facades\Prism;
use Mindtwo\AutoTranslatable\Contracts\TranslationProvider;

class AnthropicProvider implements TranslationProvider
{
    protected string $model;

    protected int $maxTokens;

    public function __construct(array $config = [])
    {
        $this->model = $config['model'] ?? 'claude-3-5-sonnet-20241022';
        $this->maxTokens = $config['max_tokens'] ?? 16000;
    }

    public function translate(
        string $content,
        string $sourceLocale,
        string $targetLocale,
        array $options = []
    ): string {
        $prompt = $this->buildPrompt($content, $sourceLocale, $targetLocale, $options);
        $systemPrompt = $this->buildSystemPrompt();

        $response = Prism::text()
            ->using('anthropic', $this->model)
            ->withSystemPrompt($systemPrompt)
            ->withPrompt($prompt)
            ->withMaxTokens($this->maxTokens)
            ->generate();

        return trim($response->text);
    }

    public function estimateTokens(string $content): int
    {
        // Claude's tokenization: roughly 1 token ≈ 3.5 characters
        return (int) ceil(strlen($content) / 3.5);
    }

    public function getMaxTokens(): int
    {
        return $this->maxTokens;
    }

    protected function buildSystemPrompt(): string
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

The output must be valid markdown that can be parsed identically to the source, just in a different language.
PROMPT;
    }

    protected function buildPrompt(
        string $content,
        string $sourceLocale,
        string $targetLocale,
        array $options
    ): string {
        $sourceLanguage = $this->getLanguageName($sourceLocale);
        $targetLanguage = $this->getLanguageName($targetLocale);

        $prompt = "Translate the following markdown content from {$sourceLanguage} to {$targetLanguage}:\n\n";
        $prompt .= "---\n{$content}\n---\n";

        if (isset($options['prompt_additions']) && $options['prompt_additions']) {
            $prompt .= "\n".$options['prompt_additions'];
        }

        return $prompt;
    }

    protected function getLanguageName(string $locale): string
    {
        return match ($locale) {
            'de' => 'German',
            'en' => 'English',
            'fr' => 'French',
            'es' => 'Spanish',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'nl' => 'Dutch',
            'pl' => 'Polish',
            'ru' => 'Russian',
            'ja' => 'Japanese',
            'zh' => 'Chinese',
            default => ucfirst($locale),
        };
    }
}
