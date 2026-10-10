// Alpine.js — 예전엔 cdn.jsdelivr.net 에서 defer 로 받았다.
// 번들에 넣으면 외부 요청 하나가 줄고, 서비스워커가 CDN 응답을 따로 들고 있을 필요도 없다.
// Vite 가 type="module" 로 싣기 때문에 화면의 인라인 <script>(x-data 함수 정의)가 먼저 실행된 뒤 시작한다 —
// defer 스크립트였을 때와 같은 순서다.
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();
