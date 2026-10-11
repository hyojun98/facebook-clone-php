<?php
/**
 * ============================================================================
 * index.php — 뉴스피드 (홈 화면)
 * ============================================================================
 *
 * URL
 *   /index.php                  첫 화면
 *   /index.php?page=2&partial=1 "게시물 더 보기" 버튼이 AJAX 로 호출 → JSON {html, hasMore}
 *
 * 화면 구성 (3단 레이아웃, 화면 폭에 따라 좌/우 사이드바는 숨겨짐)
 *   ┌──────────────┬──────────────────────────┬─────────────────────┐
 *   │ 왼쪽 메뉴    │ 게시물 작성 카드          │ 받은 친구 요청 (3)  │
 *   │ (lg 이상)    │ 뉴스피드 (나 + 친구 글)   │ 알 수도 있는 사람   │
 *   │              │ [게시물 더 보기]          │ 연락처(친구 목록)   │
 *   └──────────────┴──────────────────────────┴─────────────────────┘
 *
 * 사용 함수: feed_posts(), friend_requests_received(), friend_suggestions(), get_friends()
 * 사용 조각: partials/composer.php, partials/post_card.php, partials/friend_buttons.php
 */
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();          // 비로그인이면 login.php 로 이동
$meId = (int) $me['id'];

$page = max(1, (int) ($_GET['page'] ?? 1));
$posts = feed_posts($meId, $page);
// 이번 페이지가 꽉 찼으면 다음 페이지가 있을 수 있다고 판단
$hasMore = count($posts) === (int) config('per_page');

// "더 보기" AJAX 요청 → 레이아웃 없이 게시물 카드 HTML 만 JSON 으로 응답
if (isset($_GET['partial'])) {
    $html = '';
    foreach ($posts as $post) {
        $html .= render_partial('post_card', ['post' => $post]);
    }
    json_response(['html' => $html, 'hasMore' => $hasMore]);
}

// 오른쪽 사이드바 데이터
$requests = array_slice(friend_requests_received($meId), 0, 3);  // 받은 친구 요청 최대 3개
$suggestions = friend_suggestions($meId, 3);                      // 알 수도 있는 사람 3명
$contacts = get_friends($meId, 20);                               // 연락처 = 친구 20명

// header.php 가 사용하는 변수: 브라우저 탭 제목, 상단 메뉴에서 강조할 항목
$title = '홈';
$active = 'home';
require __DIR__ . '/includes/partials/header.php';
?>
<div class="mx-auto flex max-w-screen-2xl justify-center gap-6 px-0 sm:px-4">
    <!-- 왼쪽 사이드바 -->
    <aside class="sticky top-14 hidden h-[calc(100vh-3.5rem)] w-72 shrink-0 overflow-y-auto py-4 lg:block">
        <nav class="space-y-1">
            <a href="<?= profile_url($meId) ?>" class="side-link"><?= avatar($me, 'size-9', 'text-xs') ?><?= e(full_name($me)) ?></a>
            <a href="<?= url('friends.php') ?>" class="side-link">
                <span class="flex size-9 items-center justify-center rounded-full bg-primary/10 text-primary"><?= icon('users') ?></span> 친구
            </a>
            <a href="<?= url('notifications.php') ?>" class="side-link">
                <span class="flex size-9 items-center justify-center rounded-full bg-amber-500/10 text-amber-500"><?= icon('bell') ?></span> 알림
            </a>
            <a href="<?= profile_url($meId, 'photos') ?>" class="side-link">
                <span class="flex size-9 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-500"><?= icon('images') ?></span> 사진 및 동영상
            </a>
            <a href="<?= url('search.php') ?>" class="side-link">
                <span class="flex size-9 items-center justify-center rounded-full bg-violet-500/10 text-violet-500"><?= icon('search') ?></span> 사람 찾기
            </a>
            <a href="<?= url('settings.php') ?>" class="side-link">
                <span class="flex size-9 items-center justify-center rounded-full bg-muted text-muted-foreground"><?= icon('settings') ?></span> 설정
            </a>
        </nav>
        <p class="mt-6 px-2 text-xs text-muted-foreground">개인정보처리방침 · 이용약관 · 광고 · 쿠키<br><?= e(config('app_name')) ?> clone © <?= date('Y') ?></p>
    </aside>

    <!-- 뉴스피드 -->
    <section class="w-full max-w-[590px] space-y-4 py-4">
        <?php partial('composer') ?>

        <div class="space-y-4" id="feed">
            <?php foreach ($posts as $post): partial('post_card', ['post' => $post]); endforeach ?>
        </div>

        <?php if (!$posts): ?>
            <div class="card items-center gap-3 px-6 py-12 text-center">
                <div class="flex size-14 items-center justify-center rounded-full bg-muted"><?= icon('newspaper', 'size-7 text-muted-foreground') ?></div>
                <h3 class="text-lg font-semibold">아직 표시할 게시물이 없어요</h3>
                <p class="text-sm text-muted-foreground">친구를 추가하거나 첫 게시물을 작성해보세요.</p>
                <a href="<?= url('friends.php?tab=suggestions') ?>" class="btn btn-primary mt-2"><?= icon('user-plus') ?> 친구 찾기</a>
            </div>
        <?php elseif ($hasMore): ?>
            <button type="button" class="btn btn-outline w-full" data-load-more="<?= e(url('index.php')) ?>" data-page="<?= $page + 1 ?>">
                <?= icon('loader-circle', 'hidden animate-spin') ?> 게시물 더 보기
            </button>
        <?php endif ?>
    </section>

    <!-- 오른쪽 사이드바 -->
    <aside class="sticky top-14 hidden h-[calc(100vh-3.5rem)] w-80 shrink-0 space-y-4 overflow-y-auto py-4 xl:block">
        <?php if ($requests): ?>
            <div>
                <div class="mb-2 flex items-center justify-between px-2">
                    <h3 class="font-semibold text-muted-foreground">친구 요청</h3>
                    <a href="<?= url('friends.php?tab=requests') ?>" class="text-sm text-primary hover:underline">모두 보기</a>
                </div>
                <?php foreach ($requests as $r): ?>
                    <div class="flex gap-3 rounded-lg p-2 hover:bg-accent/60">
                        <a href="<?= profile_url((int) $r['id']) ?>"><?= avatar($r, 'size-14', 'text-base') ?></a>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-baseline justify-between gap-2">
                                <a href="<?= profile_url((int) $r['id']) ?>" class="truncate font-semibold hover:underline"><?= e(full_name($r)) ?></a>
                                <span class="shrink-0 text-xs text-muted-foreground"><?= time_ago($r['requested_at']) ?></span>
                            </div>
                            <div class="mt-2"><?php partial('friend_buttons', ['userId' => (int) $r['id'], 'status' => 'request_received', 'size' => 'btn-sm']) ?></div>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
            <div class="h-px bg-border"></div>
        <?php endif ?>

        <?php if ($suggestions): ?>
            <div>
                <div class="mb-2 flex items-center justify-between px-2">
                    <h3 class="font-semibold text-muted-foreground">알 수도 있는 사람</h3>
                    <a href="<?= url('friends.php?tab=suggestions') ?>" class="text-sm text-primary hover:underline">더 보기</a>
                </div>
                <?php foreach ($suggestions as $s): ?>
                    <div class="flex items-center gap-3 rounded-lg p-2 hover:bg-accent/60">
                        <a href="<?= profile_url((int) $s['id']) ?>"><?= avatar($s, 'size-10', 'text-sm') ?></a>
                        <div class="min-w-0 flex-1">
                            <a href="<?= profile_url((int) $s['id']) ?>" class="block truncate text-sm font-semibold hover:underline"><?= e(full_name($s)) ?></a>
                            <p class="text-xs text-muted-foreground"><?= $s['mutual'] ? "함께 아는 친구 {$s['mutual']}명" : '새로운 회원' ?></p>
                        </div>
                        <form method="post" action="<?= url('actions/friend.php') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="op" value="send">
                            <input type="hidden" name="user_id" value="<?= (int) $s['id'] ?>">
                            <button class="btn btn-secondary btn-icon size-8" title="친구 추가"><?= icon('user-plus') ?></button>
                        </form>
                    </div>
                <?php endforeach ?>
            </div>
            <div class="h-px bg-border"></div>
        <?php endif ?>

        <div>
            <h3 class="mb-2 px-2 font-semibold text-muted-foreground">연락처</h3>
            <?php if (!$contacts): ?>
                <p class="px-2 text-sm text-muted-foreground">아직 친구가 없습니다.</p>
            <?php endif ?>
            <?php foreach ($contacts as $c): ?>
                <a href="<?= profile_url((int) $c['id']) ?>" class="side-link">
                    <?= avatar($c, 'size-9', 'text-xs') ?>
                    <?= e(full_name($c)) ?>
                </a>
            <?php endforeach ?>
        </div>
    </aside>
</div>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
