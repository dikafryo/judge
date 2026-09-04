<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * 행사 설정 변경이 규칙에 걸려 거절된 경우 (배점 합계 초과, 이미 채점된 항목의 구조 변경 등).
 *
 * 웹은 [$field => 메시지] 로 폼에 되돌리고, API 는 같은 모양의 422 를 낸다.
 * 규칙 자체는 서비스 한 곳에만 두고, 표현만 두 곳에서 다르게 한다.
 */
class SetupRejected extends RuntimeException
{
    public function __construct(string $message, public readonly string $field)
    {
        parent::__construct($message);
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return [$this->field => $this->getMessage()];
    }
}
