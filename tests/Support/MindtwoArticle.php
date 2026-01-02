<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Mindtwo\AutoTranslatable\Concerns\HasAutoTranslations;
use mindtwo\LaravelTranslatable\Traits\HasTranslations;

class MindtwoArticle extends Model
{
    use HasAutoTranslations;
    use HasTranslations;
    protected $table = 'mindtwo_articles';
    protected $guarded = [];
    protected array $translatable = ['title', 'content'];

    public function autoTranslatableFields(): array
    {
        return ['content'];
    }
}
