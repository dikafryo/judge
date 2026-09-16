<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * 안드로이드 앱(APK) 내려받기 안내 — /app
 *
 * APK 자체는 nginx 가 public/downloads/ 에서 정적으로 내보낸다. 여기서는 안내만 한다.
 * 메타데이터는 judge-app 의 build_and_publish.sh 가 public/app-release.json 에 써 둔다.
 */
class AppDownloadController extends Controller
{
    private const RELEASE_FILE = 'app-release.json';

    public function index(): View
    {
        return view('app', [
            'release' => $this->release(),
            'play' => $this->play(),
        ]);
    }

    /**
     * 플레이스토어 비공개 테스트 안내에 필요한 세 링크.
     * 하나라도 비어 있으면 안내를 통째로 숨긴다 — 반쪽짜리 절차는 안 하느니만 못하다.
     *
     * @return array{group:string, optIn:string, store:string}|null
     */
    private function play(): ?array
    {
        $links = [
            'group' => (string) config('judge.play_tester_group'),
            'optIn' => (string) config('judge.play_opt_in_url'),
            'store' => (string) config('judge.play_store_url'),
        ];

        foreach ($links as $url) {
            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                return null;
            }
        }

        return $links;
    }

    /**
     * 게시된 릴리스 정보. 스크립트가 쓴 파일이지만 화면에 그대로 뿌리므로 값을 검증한다.
     *
     * @return array{version:string,build:int,apk:string,sizeText:string,sha256:string,publishedAt:?\DateTimeImmutable}|null
     */
    private function release(): ?array
    {
        $path = public_path(self::RELEASE_FILE);

        if (! is_readable($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data)) {
            return null;
        }

        $apk = (string) ($data['apk'] ?? '');

        // 경로는 downloads/<파일명>.apk 형태만 허용하고, 실제로 있는 파일이어야 한다
        if (preg_match('#^downloads/[A-Za-z0-9._-]+\.apk$#', $apk) !== 1
            || ! is_file(public_path($apk))) {
            return null;
        }

        $sha256 = (string) ($data['sha256'] ?? '');
        $size = max(0, (int) ($data['sizeBytes'] ?? 0));

        return [
            'version' => (string) ($data['version'] ?? '—'),
            'build' => max(0, (int) ($data['build'] ?? 0)),
            'apk' => $apk,
            'sizeText' => number_format($size / 1048576, 1).' MB',
            'sha256' => preg_match('/^[a-f0-9]{64}$/', $sha256) === 1 ? $sha256 : '',
            'publishedAt' => $this->parseDate($data['publishedAt'] ?? null),
        ];
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
