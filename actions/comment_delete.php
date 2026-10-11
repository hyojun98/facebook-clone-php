<?php
/**
 * ============================================================================
 * actions/comment_delete.php — 댓글 삭제 (POST)
 * ============================================================================
 *
 * 요청 : partials/comment.php 의 휴지통 버튼 (마우스를 올리면 보임, data-comment-delete)
 *   _csrf, comment_id
 *
 * 권한 : 댓글 작성자 또는 게시물 작성자 (delete_comment() 에서 확인)
 * 응답 : AJAX → {"ok": true, "count": 남은 댓글 수} / 일반 → 리다이렉트
 */
require __DIR__ . '/../includes/bootstrap.php';
$me = require_login();
require_post();

$postId = delete_comment((int) $me['id'], (int) ($_POST['comment_id'] ?? 0));
if ($postId === null) {
    abort(403, '이 댓글을 삭제할 권한이 없습니다.');
}

if (is_ajax()) {
    json_response(['ok' => true, 'count' => comment_count($postId)]);
}
flash('success', '댓글이 삭제되었습니다.');
redirect_back();
