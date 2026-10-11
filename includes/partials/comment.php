<?php
/**
 * ============================================================================
 * partials/comment.php — 댓글 1개 (말풍선 모양)
 * ============================================================================
 *
 * 사용처: post_card.php 의 댓글 목록, actions/comment_create.php 의 AJAX 응답 HTML
 * 삭제 버튼(휴지통)은 댓글 작성자 또는 게시물 작성자에게만, 마우스를 올렸을 때 보인다.
 *
 * @var array $c           댓글 행 (users 컬럼 포함)
 * @var int   $postOwnerId 게시물 작성자 id (삭제 권한 판단용)
 */
$meId = current_user_id();
$canDelete = $meId === (int) $c['user_id'] || $meId === (int) $postOwnerId;
?>
<div class="group flex items-start gap-2" id="comment-<?= (int) $c['id'] ?>" data-comment>
    <a href="<?= profile_url((int) $c['user_id']) ?>" class="shrink-0"><?= avatar($c, 'size-8', 'text-xs') ?></a>
    <div class="min-w-0">
        <div class="flex items-center gap-1">
            <div class="rounded-2xl bg-muted px-3 py-2">
                <a href="<?= profile_url((int) $c['user_id']) ?>" class="text-[13px] font-semibold hover:underline"><?= e(full_name($c)) ?></a>
                <p class="text-sm break-words"><?= format_text($c['content']) ?></p>
            </div>
            <?php if ($canDelete): ?>
                <form method="post" action="<?= url('actions/comment_delete.php') ?>" data-comment-delete class="opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
                    <?= csrf_field() ?>
                    <input type="hidden" name="comment_id" value="<?= (int) $c['id'] ?>">
                    <button class="flex size-7 items-center justify-center rounded-full text-muted-foreground hover:bg-accent" aria-label="댓글 삭제" title="삭제"><?= icon('trash-2', 'size-3.5') ?></button>
                </form>
            <?php endif ?>
        </div>
        <p class="mt-0.5 px-3 text-xs text-muted-foreground"><?= time_ago($c['created_at']) ?></p>
    </div>
</div>
