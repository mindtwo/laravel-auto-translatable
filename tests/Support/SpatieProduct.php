<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Mindtwo\AutoTranslatable\Concerns\HasAutoTranslations;
use Spatie\Translatable\HasTranslations;

class SpatieProduct extends Model
{
    use HasAutoTranslations;
    use HasTranslations;
    public array $translatable = ['name', 'subtitle', 'short_description', 'meta_title'];
    protected $table = 'products';
    protected $guarded = [];

    public function autoTranslatableFields(): array
    {
        return ['name', 'subtitle', 'short_description', 'meta_title'];
    }
}
