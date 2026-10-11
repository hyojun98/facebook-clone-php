<?php
/**
 * ============================================================================
 * partials/post_card.php — 게시물 카드 1개
 * ============================================================================
 *
 * 사용처: 뉴스피드(index.php), 프로필 게시물 탭(profile.php), 게시물 상세(post.php),
 *        "더 보기" AJAX 응답(render_partial)
 *
 * 카드 구조
 *   ① 헤더   : 작성자 사진/이름, 작성 시간(→ 상세 링크), 공개 범위 아이콘, "…" 메뉴(링크 복사/수정/삭제)
 *   ② 본문   : 짧은 글(85자 이하, 3줄 미만, 첨부 없음)은 큰 글씨로 표시 — 페이스북과 같은 방식
 *   ③ 미디어 : 1개면 원본 비율, 2개 이상이면 2열 그리드(최대 4칸, 5개 이상은 마지막 칸에 +N)
 *   ④ 요약   : 좋아요 수(파란 원), 댓글 수
 *   ⑤ 버튼   : 좋아요(AJAX 토글) / 댓글 달기(입력칸 포커스) / 공유하기(링크 복사)
 *   ⑥ 댓글   : 최근 3개(+ "이전 댓글 N개 더 보기") 또는 전체($expanded), 댓글 입력칸
 *
 * data-* 속성은 assets/app.js 가 이벤트를 연결하는 표시이다. (data-like-form, data-comment-form ...)
 *
 * @var array $post      fetch_posts() 가 돌려준 게시물 1개 (media, comments, liked ... 포함)
 * @var bool  $expanded  true면 모든 댓글 표시 (단일 게시물 페이지)
 */
$expanded = $expanded ?? false;
$meId     = current_user_id();
$pid      = (int) $post['id'];
$isOwner  = (int) $post['user_id'] === $meId;
$media    = $post['media'];
$mCount   = count($media);
[$privIcon, $privText] = privacy_label($post['privacy']);
$content  = (string) ($post['content'] ?? '');
// 짧은 글만 있는 게시물은 text-2xl 로 크게 보여준다.
$bigText  = $mCount === 0 && $content !== '' && mb_strlen($content) <= 85 && substr_count($content, "\n") < 3;
$postUrl  = url('post.php?id=' . $pid);
?>
<article class="card gap-0 overflow-hidden" id="post-<?= $pid ?>" data-post>
    <!-- 헤더 -->
    <div class="flex items-start gap-3 px-4 pt-4">
        <a href="<?= profile_url((int) $post['user_id']) ?>"><?= avatar($post, 'size-10', 'text-sm') ?></a>
        <div class="min-w-0 flex-1">
            <a href="<?= profile_url((int) $post['user_id']) ?>" class="font-semibold hover:underline"><?= e(full_name($post)) ?></a>
            <div class="flex items-center gap-1 text-xs text-muted-foreground">
                <a href="<?= $postUrl ?>" class="hover:underline" title="<?= e(date('Y년 n월 j일 H:i', strtotime($post['created_at']))) ?>"><?= time_ago($post['created_at']) ?></a>
                <span>·</span>
                <span title="<?= e($privText) ?>"><?= icon($privIcon, 'size-3') ?></span>
            </div>
        </div>
        <div class="relative" data-dropdown>
            <button type="button" class="btn btn-ghost btn-icon size-8 rounded-full text-muted-foreground" data-dropdown-trigger aria-label="게시물 옵션"><?= icon('ellipsis') ?></button>
            <div class="dropdown-menu right-0 mt-1" data-dropdown-menu hidden>
                <button type="button" class="dropdown-item" data-copy-link="<?= e($postUrl) ?>"><?= icon('link') ?> 링크 복사</button>
                <?php if ($isOwner): ?>
                    <a href="<?= url('post_edit.php?id=' . $pid) ?>" class="dropdown-item"><?= icon('pencil') ?> 게시물 수정</a>
                    <div class="dropdown-separator"></div>
                    <form method="post" action="<?= url('actions/post_delete.php') ?>" data-confirm="이 게시물을 삭제하시겠습니까? 삭제한 게시물은 복구할 수 없습니다.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="post_id" value="<?= $pid ?>">
                        <button class="dropdown-item text-destructive hover:text-destructive [&_svg]:!text-destructive"><?= icon('trash-2') ?> 삭제</button>
                    </form>
                <?php endif ?>
            </div>
        </div>
    </div>

    <!-- 본문 -->
    <?php if ($content !== ''): ?>
        <div class="px-4 pt-3 <?= $bigText ? 'pb-2 text-2xl leading-snug' : 'text-[15px] leading-relaxed' ?> break-words"><?= format_text($content) ?></div>
    <?php endif ?>

    <!-- 미디어 -->
    <?php if ($mCount): ?>
        <div class="mt-3 bg-muted">
            <?php if ($mCount === 1): $m = $media[0]; ?>
                <?php if ($m['media_type'] === 'video'): ?>
                    <video src="<?= e(media_url($m['file_path'])) ?>" controls playsinline preload="metadata" class="max-h-[640px] w-full bg-black"></video>
                <?php else: ?>
                    <button type="button" class="block w-full" data-lightbox-item data-group="post-<?= $pid ?>" data-type="image" data-src="<?= e(media_url($m['file_path'])) ?>">
                        <img src="<?= e(media_url($m['file_path'])) ?>" alt="" loading="lazy" class="mx-auto max-h-[640px] w-full object-contain">
                    </button>
                <?php endif ?>
            <?php else: ?>
                <div class="grid grid-cols-2 gap-0.5">
                    <?php foreach (array_slice($media, 0, 4) as $i => $m):
                        // 3개일 때는 첫 번째를 가로로 넓게(2칸), 나머지는 정사각형
                        $span = ($mCount === 3 && $i === 0) ? 'col-span-2 aspect-[2/1]' : 'aspect-square';
                        // 4번째 칸 위에 "+남은 개수" 덮개 표시
                        $more = ($i === 3 && $mCount > 4) ? $mCount - 4 : 0; ?>
                        <button type="button" class="group relative overflow-hidden <?= $span ?>" data-lightbox-item data-group="post-<?= $pid ?>" data-type="<?= e($m['media_type']) ?>" data-src="<?= e(media_url($m['file_path'])) ?>">
                            <?php if ($m['media_type'] === 'video'): ?>
                                <video src="<?= e(media_url($m['file_path'])) ?>#t=0.1" muted playsinline preload="metadata" class="size-full object-cover"></video>
                                <span class="absolute inset-0 flex items-center justify-center"><span class="flex size-12 items-center justify-center rounded-full bg-black/60 text-white"><?= icon('play', 'size-6 fill-current') ?></span></span>
                            <?php else: ?>
                                <img src="<?= e(media_url($m['file_path'])) ?>" alt="" loading="lazy" class="size-full object-cover transition-transform group-hover:scale-[1.02]">
                            <?php endif ?>
                            <?php if ($more): ?>
                                <span class="absolute inset-0 flex items-center justify-center bg-black/50 text-3xl font-bold text-white">+<?= $more ?></span>
                            <?php endif ?>
                        </button>
                    <?php endforeach ?>
                    <?php foreach (array_slice($media, 4) as $m): // 라이트박스 탐색용 숨김 항목 ?>
                        <span hidden data-lightbox-item data-group="post-<?= $pid ?>" data-type="<?= e($m['media_type']) ?>" data-src="<?= e(media_url($m['file_path'])) ?>"></span>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </div>
    <?php endif ?>

    <!-- 반응 요약 (0개일 때도 자리를 유지하도록 hidden 대신 invisible 사용 → 좋아요 시 레이아웃이 흔들리지 않음) -->
    <div class="flex items-center justify-between px-4 py-2.5 text-sm text-muted-foreground">
        <span class="flex items-center gap-1.5 <?= $post['like_count'] ? '' : 'invisible' ?>" data-like-summary>
            <span class="flex size-[18px] items-center justify-center rounded-full bg-primary text-primary-foreground"><?= icon('thumbs-up', 'size-2.5 fill-current') ?></span>
            <span data-like-count><?= (int) $post['like_count'] ?></span>
        </span>
        <a href="<?= $postUrl ?>" class="hover:underline <?= $post['comment_count'] ? '' : 'invisible' ?>" data-comment-summary>
            댓글 <span data-comment-count><?= (int) $post['comment_count'] ?></span>개
        </a>
    </div>

    <!-- 액션 버튼
         좋아요는 <form> 이라 JavaScript 없이도 동작하고, app.js 가 있으면 AJAX 로 가로채 새로고침 없이 처리한다.
         (form 에 class="contents" 를 주어 form 자체는 레이아웃에 영향을 주지 않고 버튼만 그리드 칸이 된다) -->
    <div class="mx-4 grid grid-cols-3 gap-1 border-y py-1">
        <form method="post" action="<?= url('actions/like.php') ?>" data-like-form class="contents">
            <?= csrf_field() ?>
            <input type="hidden" name="post_id" value="<?= $pid ?>">
            <button class="btn btn-ghost w-full text-muted-foreground <?= $post['liked'] ? '!text-primary' : '' ?>" data-like-button aria-pressed="<?= $post['liked'] ? 'true' : 'false' ?>">
                <?= icon('thumbs-up', $post['liked'] ? 'fill-current' : '') ?> 좋아요
            </button>
        </form>
        <button type="button" class="btn btn-ghost w-full text-muted-foreground" data-focus-comment="<?= $pid ?>"><?= icon('message-circle') ?> 댓글 달기</button>
        <button type="button" class="btn btn-ghost w-full text-muted-foreground" data-copy-link="<?= e($postUrl) ?>"><?= icon('share') ?> 공유하기</button>
    </div>

    <!-- 댓글 -->
    <div class="space-y-2 px-4 pb-4 pt-3">
        <?php if (!$expanded && $post['comment_count'] > count($post['comments'])): ?>
            <a href="<?= $postUrl ?>" class="text-sm font-semibold text-muted-foreground hover:underline">이전 댓글 <?= $post['comment_count'] - count($post['comments']) ?>개 더 보기</a>
        <?php endif ?>

        <div class="space-y-2" data-comment-list>
            <?php foreach ($post['comments'] as $c): partial('comment', ['c' => $c, 'postOwnerId' => (int) $post['user_id']]); endforeach ?>
        </div>

        <form method="post" action="<?= url('actions/comment_create.php') ?>" class="flex items-start gap-2 pt-1" data-comment-form>
            <?= csrf_field() ?>
            <input type="hidden" name="post_id" value="<?= $pid ?>">
            <?= avatar(current_user(), 'size-8', 'text-xs') ?>
            <div class="relative flex-1">
                <input type="text" name="content" id="comment-input-<?= $pid ?>" maxlength="2000" required autocomplete="off"
                       placeholder="댓글을 입력하세요..." class="input h-9 rounded-full border-transparent bg-muted pr-10 shadow-none">
                <button class="absolute right-1 top-1/2 flex size-7 -translate-y-1/2 items-center justify-center rounded-full text-primary hover:bg-accent disabled:opacity-40" aria-label="댓글 등록"><?= icon('send-horizontal', 'size-4') ?></button>
            </div>
        </form>
    </div>
</article>
