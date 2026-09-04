<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Judge extends Model implements AuthenticatableContract
{
    // 심사위원은 접속 코드를 토큰으로 교환해 앱에서 쓴다
    // Sanctum 토큰의 주체가 되려면 Authenticatable 이어야 한다.
    // 없으면 인증된 요청이 throttle 미들웨어를 지날 때 getAuthIdentifier() 로 500 이 난다.
    use Authenticatable;
    use HasApiTokens;
    use HasFactory;

    protected $fillable = ['event_id', 'name', 'code', 'signature', 'signed_at'];

    protected $hidden = ['signature'];

    protected function casts(): array
    {
        return ['signed_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }

    // 심사위원용 라우트는 /judge/{judge:code} 로 코드 바인딩을 명시하고,
    // 관리자용 라우트({judge})는 기본 id 바인딩을 쓴다.
    // (전역 getRouteKeyName='code' 를 두면 코드 회수(null) 시 URL 생성이 깨진다)

    /** 충돌 없는 접속 코드 생성 — 입력이 쉬운 6자리 숫자 */
    public static function generateCode(): string
    {
        do {
            $code = (string) random_int(100000, 999999);
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /** 이 심사위원이 특정 대상 평가를 완료했는지 (모든 항목 점수 존재) */
    public function completedCandidate(Candidate $candidate, int $criteriaCount): bool
    {
        return $this->scores()->where('candidate_id', $candidate->id)->count() >= $criteriaCount;
    }
}
