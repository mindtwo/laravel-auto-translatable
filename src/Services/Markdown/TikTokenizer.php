<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Services\Markdown;

use InvalidArgumentException;
use Yethee\Tiktoken\Encoder;
use Yethee\Tiktoken\EncoderProvider;

/**
 * Accurate token counter using TikToken for OpenAI models.
 */
class TikTokenizer implements Tokenizer
{
    protected Encoder $encoder;

    /**
     * @throws InvalidArgumentException if model is not supported
     */
    public function __construct(string $model)
    {
        $this->encoder = (new EncoderProvider)->getForModel($model);
    }

    public function count(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        return count($this->encoder->encode($text));
    }
}
