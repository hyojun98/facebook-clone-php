<?php
/**
 * ============================================================================
 * post.php — 게시물 상세 화면
 * ============================================================================
 *
 * URL : /post.php?id=게시물번호
 *
 * 들어오는 경로: 게시물의 시간 링크, "이전 댓글 N개 더 보기", 좋아요/댓글 알림 클릭, 공유 링크
 * 뉴스피드와 달리 댓글을 "전부" 보여주고, 아래에 좋아요 누른 사람 목록 카드를 추가로 보여준다.
 * 볼 권한이 없거나 삭제된 게시물은 404.
 */
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();

// 세 번째 인자 0 = 댓글 개수 제한 없음
$post = find_post((int) ($_GET['id'] ?? 0), (int) $me['id'], 0);
if (!$post) {
    abort(404, '게시물이 삭제되었거나 볼 수 있는 권한이 없습니다.');
}
$likers = $post['like_count'] ? post_likers((int) $post['id'], 20) : [];

$title = full_name($post) . '님의 게시물';
require __DIR__ . '/includes/partials/header.php';
?>
<div class="mx-auto max-w-[640px] space-y-4 px-0 py-4 sm:px-4">
    <a href="<?= url('index.php') ?>" class="btn btn-ghost btn-sm text-muted-foreground"><?= icon('arrow-left') ?> 뉴스피드</a>

    <?php partial('post_card', ['post' => $post, 'expanded' => true]) ?>

    <?php if ($likers): ?>
        <div class="card gap-3 p-4">
            <h2 class="flex items-center gap-2 font-semibold">
                <span class="flex size-5 items-center justify-center rounded-full bg-primary text-primary-foreground"><?= icon('thumbs-up', 'size-3 fill-current') ?></span>
                좋아요 <?= (int) $post['like_count'] ?>개
            </h2>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($likers as $u): ?>
                    <a href="<?= profile_url((int) $u['id']) ?>" class="flex items-center gap-2 rounded-full border py-1 pl-1 pr-3 text-sm hover:bg-accent">
                        <?= avatar($u, 'size-6', 'text-[10px]') ?><?= e(full_name($u)) ?>
                    </a>
                <?php endforeach ?>
            </div>
        </div>
    <?php endif ?>
</div>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
