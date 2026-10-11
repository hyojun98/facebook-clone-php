<?php
/**
 * ============================================================================
 * friends.php — 친구 화면
 * ============================================================================
 *
 * URL : /friends.php?tab=requests | suggestions | all | sent  (기본 requests)
 *
 *   탭            보여주는 사람                         카드 버튼
 *   ----------------------------------------------------------------------
 *   requests      나에게 친구 요청을 보낸 사람          [확인] [삭제]
 *   suggestions   알 수도 있는 사람 (함께 아는 친구 순) [친구 추가]
 *   all           내 친구                              [프로필 보기]
 *   sent          내가 요청을 보낸 사람                 [요청 취소]
 *
 * 레이아웃: 넓은 화면은 왼쪽 메뉴 + 오른쪽 카드 그리드, 좁은 화면은 위쪽 가로 탭 버튼
 * 버튼은 partials/friend_buttons.php → actions/friend.php 로 전송된다.
 * (참고: includes/friends.php 는 같은 이름이지만 DB 함수 파일이다)
 */
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();
$meId = (int) $me['id'];

// 탭 키 => [메뉴 이름, Lucide 아이콘]
$tabs = [
    'requests'    => ['친구 요청', 'user-plus'],
    'suggestions' => ['알 수도 있는 사람', 'user-search'],
    'all'         => ['모든 친구', 'users'],
    'sent'        => ['보낸 요청', 'send'],
];
$tab = array_key_exists($_GET['tab'] ?? '', $tabs) ? $_GET['tab'] : 'requests';

$requestCount = pending_request_count($meId);

// 탭별로 보여줄 사람 목록
$people = match ($tab) {
    'requests'    => friend_requests_received($meId),
    'suggestions' => friend_suggestions($meId, 30),
    'all'         => get_friends($meId),
    'sent'        => friend_requests_sent($meId),
};
// 탭이 곧 관계 상태이므로, 사람마다 friend_status() 를 다시 조회하지 않고 탭으로 버튼 종류를 정한다.
$statusFor = [
    'requests' => 'request_received',
    'suggestions' => 'none',
    'all' => 'friends',
    'sent' => 'request_sent',
][$tab];

$empty = [
    'requests'    => '새로운 친구 요청이 없습니다.',
    'suggestions' => '추천할 사람이 없습니다.',
    'all'         => '아직 친구가 없습니다. 알 수도 있는 사람을 확인해보세요.',
    'sent'        => '보낸 친구 요청이 없습니다.',
][$tab];

$title = '친구';
$active = 'friends';
require __DIR__ . '/includes/partials/header.php';
?>
<div class="mx-auto flex max-w-screen-2xl">
    <!-- 사이드 메뉴 -->
    <aside class="sticky top-14 hidden h-[calc(100vh-3.5rem)] w-80 shrink-0 border-r bg-card p-3 lg:block">
        <h1 class="mb-3 px-2 text-2xl font-bold">친구</h1>
        <nav class="space-y-1">
            <?php foreach ($tabs as $key => [$label, $ic]): ?>
                <a href="<?= url('friends.php?tab=' . $key) ?>" class="side-link <?= $tab === $key ? 'bg-accent' : '' ?>">
                    <span class="flex size-9 items-center justify-center rounded-full <?= $tab === $key ? 'bg-primary text-primary-foreground' : 'bg-muted' ?>"><?= icon($ic) ?></span>
                    <span class="flex-1"><?= e($label) ?></span>
                    <?php if ($key === 'requests' && $requestCount): ?><span class="badge badge-destructive rounded-full"><?= $requestCount ?></span><?php endif ?>
                </a>
            <?php endforeach ?>
        </nav>
    </aside>

    <section class="min-w-0 flex-1 p-4">
        <!-- 모바일 탭 -->
        <div class="mb-4 flex gap-2 overflow-x-auto lg:hidden">
            <?php foreach ($tabs as $key => [$label, $ic]): ?>
                <a href="<?= url('friends.php?tab=' . $key) ?>" class="btn btn-sm shrink-0 rounded-full <?= $tab === $key ? 'btn-primary' : 'btn-secondary' ?>">
                    <?= e($label) ?><?php if ($key === 'requests' && $requestCount): ?> <span class="rounded-full bg-destructive px-1.5 text-[11px] text-white"><?= $requestCount ?></span><?php endif ?>
                </a>
            <?php endforeach ?>
        </div>

        <div class="mb-4 flex items-baseline gap-2">
            <h2 class="text-xl font-bold"><?= e($tabs[$tab][0]) ?></h2>
            <span class="text-muted-foreground"><?= count($people) ?></span>
        </div>

        <?php if (!$people): ?>
            <div class="card items-center gap-3 px-6 py-16 text-center">
                <div class="flex size-14 items-center justify-center rounded-full bg-muted"><?= icon($tabs[$tab][1], 'size-7 text-muted-foreground') ?></div>
                <p class="text-muted-foreground"><?= e($empty) ?></p>
                <?php if ($tab !== 'suggestions'): ?>
                    <a href="<?= url('friends.php?tab=suggestions') ?>" class="btn btn-primary btn-sm mt-1">알 수도 있는 사람 보기</a>
                <?php endif ?>
            </div>
        <?php endif ?>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6">
            <?php foreach ($people as $p):
                $mutual = $p['mutual'] ?? mutual_friend_count($meId, (int) $p['id']); ?>
                <div class="card gap-0 overflow-hidden">
                    <a href="<?= profile_url((int) $p['id']) ?>" class="block aspect-square overflow-hidden">
                        <?= avatar($p, 'size-full', 'text-5xl', '!rounded-none') ?>
                    </a>
                    <div class="flex flex-1 flex-col gap-2 p-3">
                        <div>
                            <a href="<?= profile_url((int) $p['id']) ?>" class="block truncate font-semibold hover:underline"><?= e(full_name($p)) ?></a>
                            <p class="h-5 truncate text-xs text-muted-foreground">
                                <?php if (isset($p['requested_at'])): ?><?= time_ago($p['requested_at']) ?> · <?php endif ?>
                                <?= $mutual ? "함께 아는 친구 {$mutual}명" : '' ?>
                            </p>
                        </div>
                        <div class="mt-auto">
                            <?php if ($tab === 'all'): ?>
                                <a href="<?= profile_url((int) $p['id']) ?>" class="btn btn-secondary btn-sm w-full"><?= icon('user') ?> 프로필 보기</a>
                            <?php else: ?>
                                <?php partial('friend_buttons', ['userId' => (int) $p['id'], 'status' => $statusFor, 'size' => 'btn-sm', 'block' => true]) ?>
                            <?php endif ?>
                        </div>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </section>
</div>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
