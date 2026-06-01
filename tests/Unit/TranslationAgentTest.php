<?php declare(strict_types=1);

use Mindtwo\AutoTranslatable\Services\TranslationAgent;

it('reads the request timeout from config', function (): void {
    config(['auto-translatable.request_timeout' => 1200]);

    expect((new TranslationAgent('You are a translator.'))->timeout())->toBe(1200);
});

it('falls back to the default request timeout', function (): void {
    config(['auto-translatable.request_timeout' => null]);

    expect((new TranslationAgent('You are a translator.'))->timeout())->toBe(500);
});
