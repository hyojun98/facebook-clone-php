<?php
/**
 * ============================================================================
 * actions/post_delete.php — 게시물 삭제 (POST)
 * ============================================================================
 *
 * 요청 : post_card.php 의 "…" 메뉴 → 삭제 (확인 대화상자 후 전송)
 *   _csrf, post_id
 *
 * 작성자 본인만 삭제할 수 있다. (delete_post() 가 소유자를 확인)
 * 좋아요/댓글/알림/첨부 행은 DB CASCADE 로, 첨부 파일은 delete_post() 가 함께 지운다.
 */
require __DIR__ . '/../includes/bootstrap.php';
$me = require_login();
require_post();

$postId = (int) ($_POST['post_id'] ?? 0);
if (!delete_post($postId, (int) $me['id'])) {
    abort(403, '이 게시물을 삭제할 권한이 없습니다.');
}

flash('success', '게시물이 삭제되었습니다.');

// 단일 게시물 페이지에서 삭제했다면 뉴스피드로
// (그대로 돌아가면 방금 지운 게시물 화면 = 404 가 되므로)
$ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
if (str_contains($ref, 'post.php') || str_contains($ref, 'post_edit.php')) {
    redirect('index.php');
}
redirect_back();
