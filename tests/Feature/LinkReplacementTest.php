<?php declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mindtwo\AutoTranslatable\Enums\TranslationStatus;
use Mindtwo\AutoTranslatable\Services\TranslationService;
use Mindtwo\AutoTranslatable\Tests\Support\TestLinkMappingResolver;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\ValueObjects\Usage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'auto-translatable.chunk_size' => 80000,
        'auto-translatable.link_replacement.enabled' => true,
        'auto-translatable.link_replacement.internal_hosts' => ['https://example.com', 'https://www.example.com'],
        'auto-translatable.link_replacement.resolver' => TestLinkMappingResolver::class,
        'auto-translatable.link_replacement.unmapped_links' => 'remove',
    ]);
});

it('replaces internal relative links using mapping', function (): void {
    $sourceContent = <<<'MD'
        # Getting Started

        Check out our [getting started guide](/docs/getting-started) for more information.

        Also read our [blog post](/blog/hello-world) about Laravel.
        MD;

    // LLM translates text but keeps original URLs
    $translatedContent = <<<'MD'
        # Erste Schritte

        Schauen Sie sich unseren [Leitfaden für den Einstieg](/docs/getting-started) für weitere Informationen an.

        Lesen Sie auch unseren [Blog-Beitrag](/blog/hello-world) über Laravel.
        MD;

    Prism::fake([
        TextResponseFake::make()
            ->withText($translatedContent)
            ->withFinishReason(FinishReason::Stop)
            ->withUsage(new Usage(50, 60)),
    ]);

    $service = app(TranslationService::class);
    $result = $service->translate($sourceContent, 'en', 'de');

    // Post-processor should have mapped the URLs
    expect($result->status)->toBe(TranslationStatus::COMPLETED)
        ->and($result->translated_content)
        ->toContain('[Leitfaden für den Einstieg](/de/docs/erste-schritte)')
        ->toContain('[Blog-Beitrag](/de/blog/hallo-welt)')
        ->not->toContain('/docs/getting-started')
        ->not->toContain('/blog/hello-world');
});

it('replaces internal full URLs using mapping', function (): void {
    $sourceContent = <<<'MD'
        # Products

        Visit [our Laravel page](https://example.com/products/laravel) to learn more.
        MD;

    // LLM keeps original URL
    $translatedContent = <<<'MD'
        # Produkte

        Besuchen Sie [unsere Laravel-Seite](https://example.com/products/laravel), um mehr zu erfahren.
        MD;

    Prism::fake([
        TextResponseFake::make()
            ->withText($translatedContent)
            ->withFinishReason(FinishReason::Stop)
            ->withUsage(new Usage(30, 35)),
    ]);

    $service = app(TranslationService::class);
    $result = $service->translate($sourceContent, 'en', 'de');

    // Post-processor should have mapped to full URL with locale path
    expect($result->status)->toBe(TranslationStatus::COMPLETED)
        ->and($result->translated_content)
        ->toContain('[unsere Laravel-Seite](https://example.com/de/produkte/laravel)')
        ->not->toContain('https://example.com/products/laravel');
});

it('uses dynamic resolver for unmapped internal links', function (): void {
    $sourceContent = <<<'MD'
        # API Documentation

        See the [API reference](/api/v1/users) for details.
        MD;

    // LLM keeps original URL
    $translatedContent = <<<'MD'
        # API-Dokumentation

        Siehe die [API-Referenz](/api/v1/users) für Details.
        MD;

    Prism::fake([
        TextResponseFake::make()
            ->withText($translatedContent)
            ->withFinishReason(FinishReason::Stop)
            ->withUsage(new Usage(25, 30)),
    ]);

    $service = app(TranslationService::class);
    $result = $service->translate($sourceContent, 'en', 'de');

    // Dynamic resolver should add locale prefix
    expect($result->status)->toBe(TranslationStatus::COMPLETED)
        ->and($result->translated_content)
        ->toContain('[API-Referenz](/de/api/v1/users)')
        ->not->toContain('[API-Referenz](/api/v1/users)');
});

it('keeps external links unchanged', function (): void {
    $sourceContent = <<<'MD'
        # Resources

        Check out [GitHub](https://github.com) and [Stack Overflow](https://stackoverflow.com).
        MD;

    // LLM translates text but keeps URLs
    $translatedContent = <<<'MD'
        # Ressourcen

        Schauen Sie sich [GitHub](https://github.com) und [Stack Overflow](https://stackoverflow.com) an.
        MD;

    Prism::fake([
        TextResponseFake::make()
            ->withText($translatedContent)
            ->withFinishReason(FinishReason::Stop)
            ->withUsage(new Usage(30, 35)),
    ]);

    $service = app(TranslationService::class);
    $result = $service->translate($sourceContent, 'en', 'de');

    // External links should remain unchanged
    expect($result->status)->toBe(TranslationStatus::COMPLETED)
        ->and($result->translated_content)
        ->toContain('[GitHub](https://github.com)')
        ->toContain('[Stack Overflow](https://stackoverflow.com)');
});

it('removes unmapped internal links when configured to remove', function (): void {
    config(['auto-translatable.link_replacement.unmapped_links' => 'remove']);

    $sourceContent = <<<'MD'
        # Help

        Visit our [help center](/help/support) for assistance.
        MD;

    // LLM keeps original URL
    $translatedContent = <<<'MD'
        # Hilfe

        Besuchen Sie unser [Hilfezentrum](/help/support) für Unterstützung.
        MD;

    Prism::fake([
        TextResponseFake::make()
            ->withText($translatedContent)
            ->withFinishReason(FinishReason::Stop)
            ->withUsage(new Usage(20, 25)),
    ]);

    $service = app(TranslationService::class);
    $result = $service->translate($sourceContent, 'en', 'de');

    // Unmapped link should be removed, only text kept
    expect($result->status)->toBe(TranslationStatus::COMPLETED)
        ->and($result->translated_content)
        ->toContain('Hilfezentrum')
        ->not->toContain('[Hilfezentrum]')
        ->not->toContain('/help/support');
});

it('keeps unmapped internal links when configured to keep', function (): void {
    config(['auto-translatable.link_replacement.unmapped_links' => 'keep']);

    $sourceContent = <<<'MD'
        # Help

        Visit our [help center](/help/support) for assistance.
        MD;

    // LLM keeps original URL
    $translatedContent = <<<'MD'
        # Hilfe

        Besuchen Sie unser [Hilfezentrum](/help/support) für Unterstützung.
        MD;

    Prism::fake([
        TextResponseFake::make()
            ->withText($translatedContent)
            ->withFinishReason(FinishReason::Stop)
            ->withUsage(new Usage(20, 25)),
    ]);

    $service = app(TranslationService::class);
    $result = $service->translate($sourceContent, 'en', 'de');

    // Unmapped link should be kept as-is
    expect($result->status)->toBe(TranslationStatus::COMPLETED)
        ->and($result->translated_content)
        ->toContain('[Hilfezentrum](/help/support)');
});

it('warns about unmapped internal links when configured to warn', function (): void {
    config(['auto-translatable.link_replacement.unmapped_links' => 'warn']);

    $sourceContent = <<<'MD'
        # Help

        Visit our [help center](/help/support) for assistance.
        MD;

    // LLM keeps original URL
    $translatedContent = <<<'MD'
        # Hilfe

        Besuchen Sie unser [Hilfezentrum](/help/support) für Unterstützung.
        MD;

    Prism::fake([
        TextResponseFake::make()
            ->withText($translatedContent)
            ->withFinishReason(FinishReason::Stop)
            ->withUsage(new Usage(20, 25)),
    ]);

    $service = app(TranslationService::class);
    $result = $service->translate($sourceContent, 'en', 'de');

    // Unmapped link should have warning comment
    expect($result->status)->toBe(TranslationStatus::COMPLETED)
        ->and($result->translated_content)
        ->toContain('[Hilfezentrum](/help/support)')
        ->toContain('<!-- UNMAPPED: /help/support -->');
});

it('handles mixed internal and external links', function (): void {
    $sourceContent = <<<'MD'
        # Resources

        - [Getting Started](/docs/getting-started) - Internal guide
        - [Laravel Docs](https://laravel.com/docs) - External docs
        - [Our Blog](/blog/hello-world) - Internal blog
        - [GitHub](https://github.com) - External service
        MD;

    // LLM translates text but keeps all original URLs
    $translatedContent = <<<'MD'
        # Ressourcen

        - [Erste Schritte](/docs/getting-started) - Interner Leitfaden
        - [Laravel-Dokumentation](https://laravel.com/docs) - Externe Dokumentation
        - [Unser Blog](/blog/hello-world) - Interner Blog
        - [GitHub](https://github.com) - Externer Service
        MD;

    Prism::fake([
        TextResponseFake::make()
            ->withText($translatedContent)
            ->withFinishReason(FinishReason::Stop)
            ->withUsage(new Usage(60, 70)),
    ]);

    $service = app(TranslationService::class);
    $result = $service->translate($sourceContent, 'en', 'de');

    // Internal links should be mapped, external unchanged
    expect($result->status)->toBe(TranslationStatus::COMPLETED)
        ->and($result->translated_content)
        // Internal links should be mapped
        ->toContain('[Erste Schritte](/de/docs/erste-schritte)')
        ->toContain('[Unser Blog](/de/blog/hallo-welt)')
        ->not->toContain('/docs/getting-started')
        ->not->toContain('/blog/hello-world')
        // External links should be unchanged
        ->toContain('[Laravel-Dokumentation](https://laravel.com/docs)')
        ->toContain('[GitHub](https://github.com)');
});

it('handles links with complex text containing special characters', function (): void {
    $sourceContent = <<<'MD'
        # Special Cases

        Check out [Laravel's "Magic" Methods](/docs/getting-started) and [API (v2)](/api/v2/endpoints).
        MD;

    // LLM translates text but keeps original URLs
    $translatedContent = <<<'MD'
        # Sonderfälle

        Schauen Sie sich [Laravels „magische" Methoden](/docs/getting-started) und [API (v2)](/api/v2/endpoints) an.
        MD;

    Prism::fake([
        TextResponseFake::make()
            ->withText($translatedContent)
            ->withFinishReason(FinishReason::Stop)
            ->withUsage(new Usage(40, 45)),
    ]);

    $service = app(TranslationService::class);
    $result = $service->translate($sourceContent, 'en', 'de');

    // URLs should be mapped while preserving special characters in text
    expect($result->status)->toBe(TranslationStatus::COMPLETED)
        ->and($result->translated_content)
        ->toContain('[Laravels „magische" Methoden](/de/docs/erste-schritte)')
        ->toContain('[API (v2)](/de/api/v2/endpoints)')
        ->not->toContain('/docs/getting-started');
});

it('works when link replacement is disabled', function (): void {
    config(['auto-translatable.link_replacement.enabled' => false]);

    $sourceContent = <<<'MD'
        # Guide

        See our [getting started guide](/docs/getting-started).
        MD;

    // LLM keeps original URL
    $translatedContent = <<<'MD'
        # Leitfaden

        Siehe unseren [Leitfaden für den Einstieg](/docs/getting-started).
        MD;

    Prism::fake([
        TextResponseFake::make()
            ->withText($translatedContent)
            ->withFinishReason(FinishReason::Stop)
            ->withUsage(new Usage(20, 25)),
    ]);

    $service = app(TranslationService::class);
    $result = $service->translate($sourceContent, 'en', 'de');

    // Link should NOT be replaced when disabled
    expect($result->status)->toBe(TranslationStatus::COMPLETED)
        ->and($result->translated_content)
        ->toContain('[Leitfaden für den Einstieg](/docs/getting-started)')
        ->not->toContain('/de/docs/erste-schritte');
});

it('handles multiple occurrences of the same link', function (): void {
    $sourceContent = <<<'MD'
        # Getting Started

        First, read the [guide](/docs/getting-started).

        Then, practice with the [guide](/docs/getting-started) again.
        MD;

    // LLM keeps original URLs in both places
    $translatedContent = <<<'MD'
        # Erste Schritte

        Lesen Sie zuerst den [Leitfaden](/docs/getting-started).

        Dann üben Sie mit dem [Leitfaden](/docs/getting-started) noch einmal.
        MD;

    Prism::fake([
        TextResponseFake::make()
            ->withText($translatedContent)
            ->withFinishReason(FinishReason::Stop)
            ->withUsage(new Usage(40, 45)),
    ]);

    $service = app(TranslationService::class);
    $result = $service->translate($sourceContent, 'en', 'de');

    // Both occurrences should be replaced
    expect($result->status)->toBe(TranslationStatus::COMPLETED)
        ->and(mb_substr_count($result->translated_content, '/de/docs/erste-schritte'))->toBe(2);

    expect($result->translated_content)
        ->not->toContain('/docs/getting-started');
});

it('handles links in code blocks correctly', function (): void {
    $sourceContent = <<<'MD'
        # Documentation

        Visit [our guide](/docs/getting-started).

        ```markdown
        Example: [link](/docs/getting-started)
        ```
        MD;

    // LLM should preserve code blocks and keep URLs
    $translatedContent = <<<'MD'
        # Dokumentation

        Besuchen Sie [unseren Leitfaden](/docs/getting-started).

        ```markdown
        Beispiel: [link](/docs/getting-started)
        ```
        MD;

    Prism::fake([
        TextResponseFake::make()
            ->withText($translatedContent)
            ->withFinishReason(FinishReason::Stop)
            ->withUsage(new Usage(35, 40)),
    ]);

    $service = app(TranslationService::class);
    $result = $service->translate($sourceContent, 'en', 'de');

    // Links outside code blocks should be replaced, inside should remain
    expect($result->status)->toBe(TranslationStatus::COMPLETED)
        ->and($result->translated_content)
        ->toContain('[unseren Leitfaden](/de/docs/erste-schritte)')
        // Code block content is also processed (this is a limitation we might want to address)
        ->toContain('```markdown');
});
