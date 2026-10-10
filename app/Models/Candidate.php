<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Candidate extends Model
{
    use HasFactory;

    protected $fillable = ['event_id', 'name', 'affiliation', 'description', 'sort_order'];

    /** 저장·삭제되면 소속 행사의 캐시(심사위원 화면·설정·집계)를 비운다 */
    protected static function booted(): void
    {
        static::saved(fn (self $model) => Event::forgetCachesFor((int) $model->event_id));
        static::deleted(fn (self $model) => Event::forgetCachesFor((int) $model->event_id));
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }
}
