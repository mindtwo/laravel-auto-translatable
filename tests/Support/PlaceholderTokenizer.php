<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Tests\Support;

use Mindtwo\AutoTranslatable\Services\Markdown\Tokenizer;

/**
 * Test tokenizer that counts tokens based on {N tokens} placeholders.
 *
 * This makes tests more readable by allowing explicit token counts:
 * Example: "# Section\n{400 tokens}" will count as ~3 (heading) + 400 = 403 tokens
 *
 * Counts:
 * - Sum of all {N tokens} placeholders
 * - Plus character-based estimation of actual content (excluding placeholders)
 */
final class PlaceholderTokenizer implements Tokenizer
{
    private const CHARS_PER_TOKEN = 4;

    public function count(string $text): int
    {
        // Extract {N tokens} placeholders and sum them
        preg_match_all('/\{(\d+)\s+tokens?\}/', $text, $matches);
        $placeholderTotal = array_sum(array_map('intval', $matches[1]));

        // Also count actual content (rough estimate: 1 token per 4 chars)
        $stripped = preg_replace('/\{(\d+)\s+tokens?\}/', '', $text);
        $contentTokens = max(1, (int) ceil(mb_strlen(mb_trim($stripped)) / self::CHARS_PER_TOKEN));

        // Sum both placeholder tokens and content tokens
        return $placeholderTotal + $contentTokens;
    }
}
