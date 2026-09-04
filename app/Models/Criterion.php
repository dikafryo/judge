<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Criterion extends Model
{
    use HasFactory;

    protected $table = 'criteria';

    protected $fillable = ['event_id', 'parent_id', 'name', 'description', 'max_score', 'sort_order'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** 소속 대분류 (서브항목일 때) */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Criterion::class, 'parent_id');
    }

    /** 서브항목들 (대분류일 때) */
    public function children(): HasMany
    {
        return $this->hasMany(Criterion::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }
}
