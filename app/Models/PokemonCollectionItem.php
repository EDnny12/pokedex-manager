<?php

namespace App\Models;

use Database\Factories\PokemonCollectionItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PokemonCollectionItem extends Model
{
    /** @use HasFactory<PokemonCollectionItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pokemon_id',
        'nickname',
        'notes',
        'is_favorite',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_favorite' => false,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_favorite' => 'boolean',
        ];
    }
}
