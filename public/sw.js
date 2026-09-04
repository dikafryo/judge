/* 온라인 심사 시스템 — 서비스워커
 *
 * 목적
 *  1) 앱 셸(아이콘·오프라인 페이지)과 CDN 자산을 캐시해 오프라인에서도 화면이 깨지지 않게 한다.
 *     Tailwind·Alpine·Pretendard가 전부 CDN이라 이걸 캐시하지 않으면 오프라인에서 스타일이 통째로 사라진다.
 *  2) 심사위원 화면(/judge/*)을 network-first 로 캐시해 연결이 끊겨도 채점 UI가 뜨게 한다.
 *  3) 관리자 화면은 캐시하지 않는다 — 오래된 집계를 최신으로 오인할 위험 + 로그아웃 후 잔존 노출.
 *
 * 점수·서명의 오프라인 큐잉은 여기가 아니라 페이지(judge/evaluate.blade.php)에서 한다.
 * iOS Safari가 Background Sync를 지원하지 않기 때문이다.
 *
 * ※ 캐시 내용을 바꾸면 반드시 SW_VERSION 을 올릴 것 (구버전 캐시는 activate 에서 삭제된다).
 */

const SW_VERSION = 'v1';

const SHELL_CACHE = 'judge-shell-' + SW_VERSION;
const PAGE_CACHE = 'judge-pages-' + SW_VERSION;
const CDN_CACHE = 'judge-cdn-' + SW_VERSION;
const OWN_CACHES = [SHELL_CACHE, PAGE_CACHE, CDN_CACHE];

const OFFLINE_URL = '/offline.html';

/** 항상 있어야 하는 자체 자산 — 하나라도 실패하면 설치를 실패시킨다. */
const PRECACHE_SHELL = [
    OFFLINE_URL,
    '/manifest.json',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/icons/icon-maskable-192.png',
    '/icons/icon-maskable-512.png',
    '/icons/apple-touch-icon.png',
    '/icons/favicon-32.png',
];

/** layouts/app.blade.php 가 <head> 에서 불러오는 CDN 자산 — 실패해도 설치는 계속한다. */
const PRECACHE_CDN = [
    'https://cdn.tailwindcss.com',
    'https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js',
    'https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/static/pretendard.min.css',
    'https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@700&display=swap',
];

const CDN_HOSTS = [
    'cdn.tailwindcss.com',
    'cdn.jsdelivr.net',
    'fonts.googleapis.com',
    'fonts.gstatic.com',
];

/** 아이콘·매니페스트처럼 내용이 고정된 동일 출처 자산 */
function isStaticAsset(path) {
    return path.startsWith('/icons/')
        || path === '/manifest.json'
        || path === '/favicon.ico'
        || path === OFFLINE_URL;
}

/** 절대 캐시하면 안 되는 화면 — 관리자 영역, 실시간 집계, CSV 내보내기 */
function isNeverCached(path) {
    return path.startsWith('/admin/')
        || path === '/events'
        || path.endsWith('/dashboard/data')
        || path.endsWith('/export');
}

/** 오프라인에서 의미가 있는 화면 — 심사위원 화면과 로그인 없는 공개 화면 */
function isCacheablePage(path) {
    return path === '/'
        || path === '/demo'
        || path === '/demo/admin'
        || path.startsWith('/judge/');
}

/** 캐시에 넣어도 되는 응답인지 — 리다이렉트된 응답은 넣으면 재생 시 예외가 난다. */
function isStorable(response) {
    return response && response.status === 200 && ! response.redirected;
}

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const shell = await caches.open(SHELL_CACHE);
        await shell.addAll(PRECACHE_SHELL);

        // CDN 은 차단·지연될 수 있으므로 개별로 담고 실패는 무시한다.
        const cdn = await caches.open(CDN_CACHE);
        await Promise.all(PRECACHE_CDN.map(
            (url) => cdn.add(new Request(url, { mode: 'no-cors' })).catch(() => null)
        ));
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const stale = (await caches.keys())
            .filter((name) => name.startsWith('judge-') && OWN_CACHES.indexOf(name) === -1);

        await Promise.all(stale.map((name) => caches.delete(name)));
        await self.clients.claim();
    })());
});

// 심사 도중 화면이 갑자기 갈리지 않도록, 교체는 페이지가 명시적으로 요청할 때만 한다.
self.addEventListener('message', (event) => {
    if (event.data === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

/** 캐시 우선 — 없으면 네트워크에서 받아 캐시에 넣는다. */
async function cacheFirst(request, cacheName) {
    const cache = await caches.open(cacheName);
    const hit = await cache.match(request);

    if (hit) {
        return hit;
    }

    const response = await fetch(request);

    if (isStorable(response)) {
        cache.put(request, response.clone());
    }

    return response;
}

/** 네트워크 우선 — 성공하면 사본을 캐시에 남기고, 실패하면 캐시 → 오프라인 페이지 순으로 폴백. */
async function networkFirst(request, cacheName) {
    const cache = await caches.open(cacheName);

    try {
        const response = await fetch(request);

        if (isStorable(response)) {
            cache.put(request, response.clone());
        }

        return response;
    } catch (error) {
        const hit = await cache.match(request);

        return hit || (await caches.match(OFFLINE_URL));
    }
}

/** 캐시를 즉시 돌려주고 뒤에서 갱신 — CDN 자산처럼 자주 안 바뀌는 것에 쓴다. */
async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const hit = await cache.match(request);

    const fetching = fetch(request).then((response) => {
        // CDN 응답은 대부분 opaque(status 0) 라 isStorable 로 거르지 않는다.
        if (response && (response.ok || response.type === 'opaque')) {
            cache.put(request, response.clone());
        }

        return response;
    }).catch(() => null);

    return hit || (await fetching) || Response.error();
}

self.addEventListener('fetch', (event) => {
    const request = event.request;

    // 점수·서명 저장을 비롯한 쓰기 요청은 절대 가로채지 않는다.
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin === self.location.origin) {
        if (isStaticAsset(url.pathname)) {
            event.respondWith(cacheFirst(request, SHELL_CACHE));

            return;
        }

        if (request.mode !== 'navigate') {
            return; // 폴링(dashboard/data) 등 화면 안 XHR 은 손대지 않는다.
        }

        if (isNeverCached(url.pathname)) {
            event.respondWith(
                fetch(request).catch(() => caches.match(OFFLINE_URL))
            );

            return;
        }

        if (isCacheablePage(url.pathname)) {
            event.respondWith(networkFirst(request, PAGE_CACHE));

            return;
        }

        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL))
        );

        return;
    }

    if (CDN_HOSTS.indexOf(url.hostname) !== -1) {
        event.respondWith(staleWhileRevalidate(request, CDN_CACHE));
    }
});
