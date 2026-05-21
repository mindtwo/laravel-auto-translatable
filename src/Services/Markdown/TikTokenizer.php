<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Services\Markdown;

use InvalidArgumentException;
use Yethee\Tiktoken\Encoder;
use Yethee\Tiktoken\EncoderProvider;

/**
 * Token counter backed by TikToken for OpenAI models.
 */
class TikTokenizer implements Tokenizer
{
    /** The TikToken encoder for the configured model. */
    protected Encoder $encoder;

    /**
     * Create a new TikTokenizer for the given model.
     *
     * @throws InvalidArgumentException when the model is not supported by TikToken
     */
    public function __construct(string $model)
    {
        if ($model === '') {
            throw new InvalidArgumentException('Model name must not be empty');
        }

        $this->encoder = (new EncoderProvider)->getForModel($model);
    }

    /**
     * Count the number of tokens in the given text.
     */
    public function count(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        return count($this->encoder->encode($text));
    }
}
