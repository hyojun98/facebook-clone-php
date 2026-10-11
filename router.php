<?php
/**
 * ============================================================================
 * router.php — PHP 내장 서버용 라우터 (Apache 의 .htaccess 역할)
 * ============================================================================
 *
 *   php -S localhost:8000 router.php     (start.bat / F5 가 이 방식으로 실행)
 *
 * 내장 서버는 .htaccess 를 읽지 않으므로, 웹에서 열리면 안 되는 경로를 여기서 403 으로 막는다.
 *   - includes/, database/, scripts/        : 내부 코드, SQL, 실행 스크립트
 *   - .runtime/, .htaccess 등 점(.) 경로    : 포터블 PHP/MySQL, DB 데이터 파일, 설정
 *   - config.php, config.local.php, router.php
 *   - 루트의 .bat / .md 파일
 *   - uploads/ 아래의 PHP 파일              : 업로드로 들어온 스크립트 실행 방지
 *
 * 그 외 요청은 return false → 내장 서버가 실제 파일(.php 실행, 이미지/JS 전송)을 그대로 처리한다.
 */
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

// 내부 파일 / 업로드 폴더의 스크립트 접근 차단
if (preg_match('#^/(includes|database|scripts)(/|$)#', $path)
    || preg_match('#(^|/)\.#', $path) // .runtime, .htaccess 등 숨김 경로
    || preg_match('#^/(config|config\.local|router)\.php$#', $path)
    || preg_match('#^/[^/]+\.(bat|md)$#i', $path)
    || preg_match('#^/uploads/.*\.(php\d?|phtml|phar)$#i', $path)) {
    http_response_code(403);
    exit('Forbidden');
}

if ($path === '/') {
    require __DIR__ . '/index.php';
    return true;
}

return false; // 나머지는 내장 서버가 그대로 처리
