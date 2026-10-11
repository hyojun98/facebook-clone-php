<?php
/**
 * ============================================================================
 * actions/comment_create.php — 댓글 작성 (POST)
 * ============================================================================
 *
 * 요청 : post_card.php 하단의 댓글 입력 폼 (data-comment-form)
 *   _csrf, post_id, content(1~2,000자)
 *
 * 응답
 *   AJAX : {"html": "<div ...댓글 HTML...>", "count": 4}
 *          서버에서 partials/comment.php 로 렌더링한 HTML 을 그대로 돌려주고,
 *          app.js 가 댓글 목록 끝에 붙인다. (화면과 서버가 같은 템플릿을 쓰므로 모양이 항상 같다)
 *   일반 : 원래 화면으로 리다이렉트
 */
require __DIR__ . '/../includes/bootstrap.php';
$me = require_login();
require_post();

$postId = (int) ($_POST['post_id'] ?? 0);
$content = trim((string) ($_POST['content'] ?? ''));

if ($content === '' || mb_strlen($content) > 2000) {
    if (is_ajax()) {
        json_response(['error' => '댓글은 1~2,000자로 입력하세요.'], 422);
    }
    flash('error', '댓글은 1~2,000자로 입력하세요.');
    redirect_back();
}

// 볼 권한이 없는 게시물이면 add_comment() 안에서 404 로 중단
$comment = add_comment((int) $me['id'], $postId, $content);
$post = find_post_raw($postId);

if (is_ajax()) {
    json_response([
        // postOwnerId: 게시물 작성자에게도 댓글 삭제 버튼을 보여주기 위해 필요
        'html'  => render_partial('comment', ['c' => $comment, 'postOwnerId' => (int) $post['user_id']]),
        'count' => comment_count($postId),
    ]);
}
redirect_back();
