<?php
/**
 * ============================================================================
 * actions/like.php — 좋아요 / 좋아요 취소 토글 (POST)
 * ============================================================================
 *
 * 요청 : post_card.php 의 [좋아요] 버튼 폼 (data-like-form)
 *   _csrf, post_id
 *
 * 응답
 *   AJAX (assets/app.js) : {"liked": true|false, "count": 3}
 *                          → 화면을 새로고침하지 않고 버튼 색과 숫자만 바꾼다.
 *   일반 폼 전송         : 원래 화면으로 리다이렉트 (JavaScript 가 꺼져 있어도 동작)
 */
require __DIR__ . '/../includes/bootstrap.php';
$me = require_login();
require_post();

// 볼 수 없는 게시물이면 toggle_like() 안에서 404 로 중단된다.
$result = toggle_like((int) $me['id'], (int) ($_POST['post_id'] ?? 0));

if (is_ajax()) {
    json_response($result);
}
redirect_back();
