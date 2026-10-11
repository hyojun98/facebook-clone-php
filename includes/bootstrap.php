<?php
/**
 * ============================================================================
 * bootstrap.php — 모든 페이지/액션의 공통 진입점
 * ============================================================================
 *
 * 모든 화면(index.php, profile.php ...)과 처리기(actions/*.php)는 맨 처음에
 *   require __DIR__ . '/includes/bootstrap.php';
 * 를 호출한다. 이 파일이 하는 일:
 *
 *   1. 프로젝트 루트 경로 상수(APP_ROOT) 정의
 *   2. 설정 파일 로드 (config.php + 선택적으로 config.local.php 덮어쓰기)
 *   3. 시간대 / 문자열 인코딩 설정
 *   4. 세션 쿠키 보안 옵션 지정 후 세션 시작
 *   5. 기능별 함수 파일 로드 (helpers → users → friends → posts → notifications → uploads)
 *
 * 프레임워크 없이 "함수 + require" 구조로 되어 있어, 클래스/오토로더가 없다.
 * 각 도메인(사용자, 친구, 게시물 ...)의 DB 로직은 includes/ 아래 파일에 함수로 모여 있다.
 */
declare(strict_types=1);

// 프로젝트 루트 (D:\workspace\php). 파일 경로를 만들 때 항상 이 상수를 기준으로 한다.
define('APP_ROOT', dirname(__DIR__));

// 설정은 전역 변수에 보관하고, 읽을 때는 helpers.php 의 config('db.host') 같은 함수를 쓴다.
$GLOBALS['config'] = require APP_ROOT . '/config.php';
// 로컬 개발용 덮어쓰기 설정 (scripts/setup.ps1 이 생성)
// array_replace_recursive: 중첩 배열에서 config.local.php 에 있는 키만 교체한다.
if (is_file(APP_ROOT . '/config.local.php')) {
    $GLOBALS['config'] = array_replace_recursive($GLOBALS['config'], require APP_ROOT . '/config.local.php');
}

date_default_timezone_set($GLOBALS['config']['timezone']);
// mb_* 함수(한글 길이 계산, 자르기)의 기본 인코딩
mb_internal_encoding('UTF-8');

// ---------------------------------------------------------------------------
// 세션 설정
//  - httponly : JavaScript 에서 세션 쿠키를 읽지 못하게 해 XSS 로 인한 세션 탈취를 줄인다.
//  - samesite : Lax 로 두면 다른 사이트에서 보낸 POST 요청에는 쿠키가 붙지 않아 CSRF 를 한 번 더 막는다.
//  - secure   : HTTPS 로 접속했을 때만 true (로컬 http 개발 환경에서도 동작하도록)
// ---------------------------------------------------------------------------
session_name('FBCLONESESSID');
session_set_cookie_params([
    'lifetime' => 0,          // 브라우저를 닫으면 만료
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

// ---------------------------------------------------------------------------
// 기능별 함수 파일 로드 (순서 중요: helpers 의 q(), e() 등을 다른 파일이 사용)
// ---------------------------------------------------------------------------
require __DIR__ . '/helpers.php';        // DB 연결, URL, CSRF, 인증, 플래시 메시지, 표시용 헬퍼
require __DIR__ . '/users.php';          // 회원 조회/생성/수정/검색
require __DIR__ . '/friends.php';        // 친구 관계(요청/수락/거절/끊기), 추천
require __DIR__ . '/posts.php';          // 게시물 조회(공개 범위), 작성/삭제, 좋아요, 댓글
require __DIR__ . '/notifications.php';  // 알림 생성/조회/읽음 처리
require __DIR__ . '/uploads.php';        // 사진/동영상 업로드 검증 및 저장
