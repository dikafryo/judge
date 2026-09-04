<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * 점수 저장이 규칙에 걸려 거절된 경우. 메시지는 사용자에게 그대로 보여줘도 되며,
 * 웹·API 모두 HTTP 422 로 옮긴다.
 */
class ScoreRejected extends RuntimeException
{
}
