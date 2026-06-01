<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Services;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Mindtwo\AutoTranslatable\Support\Config;

class TranslationAgent implements Agent
{
    use Promptable;

    public function __construct(
        private string $systemPrompt,
    ) {}

    public function instructions(): string
    {
        return $this->systemPrompt;
    }

    public function provider(): string
    {
        return Config::string('auto-translatable.provider');
    }

    public function model(): string
    {
        return Config::string('auto-translatable.model');
    }

    public function maxTokens(): int
    {
        return Config::int('auto-translatable.output_tokens', 100000);
    }

    public function timeout(): int
    {
        return Config::int('auto-translatable.request_timeout', 500);
    }
}
