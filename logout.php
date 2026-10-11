<?php
/**
 * ============================================================================
 * logout.php — 로그아웃 (POST 전용)
 * ============================================================================
 *
 * 상단 계정 메뉴의 [로그아웃] 버튼 폼에서 호출한다.
 * GET 링크가 아니라 POST + CSRF 로 받는 이유: 다른 사이트의 <img src=".../logout.php"> 같은
 * 요청으로 사용자가 강제로 로그아웃되는 것을 막기 위해서다.
 *
 * 처리: 세션 변수 비우기 → 세션 쿠키 만료 → 세션 파괴 → 새 세션에 안내 메시지를 담아 로그인 화면으로
 */
require __DIR__ . '/includes/bootstrap.php';
require_post();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

// 플래시 메시지를 저장할 새(빈) 세션 시작
session_start();
flash('info', '로그아웃되었습니다.');
redirect('login.php');
