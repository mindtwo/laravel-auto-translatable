<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Services\Markdown;

/**
 * Fallback token counter that estimates tokens from byte length.
 *
 * Used when TikToken cannot encode for the configured model. The approximate
 * ratio of one token per ~3.5 bytes works reasonably well across both Latin
 * and CJK scripts because multi-byte characters tend to consume one token each.
 */
final class TokenEstimator implements Tokenizer
{
    /** The approximate number of bytes per token. */
    private const CHARS_PER_TOKEN = 3.5;

    /**
     * Estimate the number of tokens in the given text.
     */
    public function count(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        return (int) ceil(mb_strlen($text, '8bit') / self::CHARS_PER_TOKEN);
    }
}
