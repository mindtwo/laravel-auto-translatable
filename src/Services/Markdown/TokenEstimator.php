<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Services\Markdown;

/**
 * Estimates token count based on character length.
 *
 * Used as fallback when TikToken is not available for the model.
 * Approximate ratio: 1 token ≈ 3.5 characters for English text.
 *
 * Note: Uses byte length (strlen) rather than character length (mb_strlen)
 * as this provides a more accurate approximation for token counts across
 * different languages and character sets.
 */
final class TokenEstimator implements Tokenizer
{
    private const CHARS_PER_TOKEN = 3.5;

    public function count(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        // Use byte length for better approximation across languages
        // Japanese/Chinese characters are typically 3-4 bytes and ~1 token each
        // English characters are typically 1 byte and ~0.3 tokens each
        return (int) ceil(mb_strlen($text, '8bit') / self::CHARS_PER_TOKEN);
    }
}
