<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Mindtwo\AutoTranslatable\Concerns\HasAutoTranslations;
use Spatie\Translatable\HasTranslations;

class SpatieArticle extends Model
{
    use HasAutoTranslations;
    use HasTranslations;
    public array $translatable = ['title', 'content'];
    protected $table = 'articles';
    protected $guarded = [];

    public function autoTranslatableFields(): array
    {
        return ['content'];
    }
}
