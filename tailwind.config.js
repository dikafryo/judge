/** @type {import('tailwindcss').Config} */
// Play CDN(https://cdn.tailwindcss.com) 시절의 tailwind.config 를 그대로 옮긴 것.
// 빌드는 아래 content 에 있는 파일에서 쓰인 클래스만 담으므로, 클래스 이름을
// PHP 문자열로 조립하는 파일이 생기면 그 경로도 여기에 넣어야 한다.
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './app/View/**/*.php',
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    ],
    theme: {
        extend: {
            fontFamily: { sans: ['Pretendard', 'Noto Sans KR', 'sans-serif'] },
        },
    },
    plugins: [],
};
