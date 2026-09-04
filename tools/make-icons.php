<?php

declare(strict_types=1);

/**
 * PWA 아이콘 생성기 — public/icons/*.png
 *
 * neis.me Toolgrid 마크(2x2 격자)를 그대로 쓰되, 강조 셀만 judge 색(indigo)으로
 * 바꿔 "neis.me 도구 중 심사 시스템"임을 나타낸다.
 *
 *   php tools/make-icons.php
 *
 * 안티에일리어싱을 위해 4배 크기로 그린 뒤 축소한다(GD 도형에는 AA가 없음).
 */

const SUPERSAMPLE = 4;

const COLOR_BG     = [0x1F, 0x29, 0x33]; // slate-800 계열 — 로고 배경
const COLOR_CELL   = [0xF5, 0xF3, 0xEF]; // 오프화이트 셀 3개
const COLOR_ACCENT = [0x4F, 0x46, 0xE5]; // indigo-600 — judge 강조 셀

/** 로고 CSS 비율(padding .36em / gap .18em / box 1.72em)을 그대로 옮긴 값 */
const PAD_RATIO  = 0.36 / 1.72;
const GAP_RATIO  = 0.18 / 1.72;
const CORNER_RATIO = 0.18 / 1.72; // 배경 라운드 반경

function allocate(\GdImage $im, array $rgb): int
{
    return imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
}

/** 모서리가 둥근 사각형을 채운다. */
function filledRoundedRect(\GdImage $im, float $x, float $y, float $w, float $h, float $r, int $color): void
{
    $r = min($r, $w / 2, $h / 2);
    $d = (int) round($r * 2);

    if ($d > 0) {
        imagefilledarc($im, (int) round($x + $r), (int) round($y + $r), $d, $d, 180, 270, $color, IMG_ARC_PIE);
        imagefilledarc($im, (int) round($x + $w - $r), (int) round($y + $r), $d, $d, 270, 360, $color, IMG_ARC_PIE);
        imagefilledarc($im, (int) round($x + $r), (int) round($y + $h - $r), $d, $d, 90, 180, $color, IMG_ARC_PIE);
        imagefilledarc($im, (int) round($x + $w - $r), (int) round($y + $h - $r), $d, $d, 0, 90, $color, IMG_ARC_PIE);
    }

    imagefilledrectangle($im, (int) round($x + $r), (int) round($y), (int) round($x + $w - $r), (int) round($y + $h), $color);
    imagefilledrectangle($im, (int) round($x), (int) round($y + $r), (int) round($x + $w), (int) round($y + $h - $r), $color);
}

/** Toolgrid 마크를 한 변이 $box 인 정사각 영역에 그린다. */
function drawMark(\GdImage $im, float $ox, float $oy, float $box, bool $roundedBackground): void
{
    $bg = allocate($im, COLOR_BG);

    if ($roundedBackground) {
        filledRoundedRect($im, $ox, $oy, $box, $box, $box * CORNER_RATIO, $bg);
    } else {
        imagefilledrectangle($im, (int) round($ox), (int) round($oy), (int) round($ox + $box), (int) round($oy + $box), $bg);
    }

    $pad  = $box * PAD_RATIO;
    $gap  = $box * GAP_RATIO;
    $cell = ($box - 2 * $pad - $gap) / 2;
    $rad  = $cell * 0.12;

    $light  = allocate($im, COLOR_CELL);
    $accent = allocate($im, COLOR_ACCENT);

    foreach ([[0, 0], [1, 0], [0, 1], [1, 1]] as $index => [$col, $row]) {
        filledRoundedRect(
            $im,
            $ox + $pad + $col * ($cell + $gap),
            $oy + $pad + $row * ($cell + $gap),
            $cell,
            $cell,
            $rad,
            $index === 3 ? $accent : $light,
        );
    }
}

/**
 * 아이콘 한 장을 만든다.
 *
 * @param float $inset 마크가 캔버스에서 차지하는 비율. 마스커블 아이콘은 안전영역(80%)
 *                     안에 마크가 들어가야 하므로 0.6 정도로 줄이고 배경을 꽉 채운다.
 */
function renderIcon(string $path, int $size, float $inset, bool $transparentOutside): void
{
    $s  = $size * SUPERSAMPLE;
    $im = imagecreatetruecolor($s, $s);
    imagealphablending($im, false);
    imagesavealpha($im, true);

    if ($transparentOutside) {
        imagefilledrectangle($im, 0, 0, $s, $s, imagecolorallocatealpha($im, 0, 0, 0, 127));
    } else {
        imagefilledrectangle($im, 0, 0, $s, $s, allocate($im, COLOR_BG));
    }

    imagealphablending($im, true);

    $box = $s * $inset;
    drawMark($im, ($s - $box) / 2, ($s - $box) / 2, $box, $transparentOutside);

    $out = imagecreatetruecolor($size, $size);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagecopyresampled($out, $im, 0, 0, 0, 0, $size, $size, $s, $s);

    imagepng($out, $path, 9);
    imagedestroy($im);
    imagedestroy($out);

    echo "생성: {$path}\n";
}

$dir = dirname(__DIR__) . '/public/icons';

if (! is_dir($dir) && ! mkdir($dir, 0o755, true) && ! is_dir($dir)) {
    fwrite(STDERR, "디렉터리를 만들 수 없습니다: {$dir}\n");
    exit(1);
}

// 일반 아이콘 — 캔버스를 꽉 채우고 모서리만 둥글게
renderIcon("{$dir}/icon-192.png", 192, 1.0, true);
renderIcon("{$dir}/icon-512.png", 512, 1.0, true);

// 마스커블 — 배경을 꽉 채우고 마크는 안전영역 안으로
renderIcon("{$dir}/icon-maskable-192.png", 192, 0.6, false);
renderIcon("{$dir}/icon-maskable-512.png", 512, 0.6, false);

// iOS 홈화면 — 투명 없이 정사각(iOS가 알아서 라운딩)
renderIcon("{$dir}/apple-touch-icon.png", 180, 0.72, false);

// 파비콘
renderIcon("{$dir}/favicon-32.png", 32, 1.0, true);

/*
 * 안드로이드 앱 런처 아이콘 — /var/services/web/apps/judge-app
 *
 * PWA 아이콘과 같은 마크를 쓴다(홈화면에 앱과 PWA가 함께 있어도 같은 그림이어야 한다).
 * neisme-knight 처럼 flutter_launcher_icons 없이 mipmap 디렉터리에 직접 넣는다.
 * 앱 프로젝트가 없으면 조용히 건너뛴다.
 */
const ANDROID_LAUNCHER_SIZES = [
    'mdpi'    => 48,
    'hdpi'    => 72,
    'xhdpi'   => 96,
    'xxhdpi'  => 144,
    'xxxhdpi' => 192,
];

$androidRes = '/var/services/web/apps/judge-app/android/app/src/main/res';

if (is_dir($androidRes)) {
    foreach (ANDROID_LAUNCHER_SIZES as $density => $size) {
        $target = "{$androidRes}/mipmap-{$density}";

        if (! is_dir($target) && ! mkdir($target, 0o755, true) && ! is_dir($target)) {
            fwrite(STDERR, "디렉터리를 만들 수 없습니다: {$target}\n");
            exit(1);
        }

        // 런처가 자체 마스크를 씌우므로 투명 없이 정사각으로, 마크는 안전영역 안에
        renderIcon("{$target}/ic_launcher.png", $size, 0.72, false);

        // 앱 실행 직후 스플래시 — 런처 아이콘과 같은 마크가 이어져 보이도록 모서리만 둥근 형태로
        renderIcon("{$target}/launch_image.png", $size * 2, 1.0, true);
    }
} else {
    echo "안드로이드 앱 프로젝트가 없어 런처 아이콘 생성은 건너뜁니다: {$androidRes}\n";
}
