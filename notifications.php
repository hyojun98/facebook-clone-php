<?php
/**
 * ============================================================================
 * notifications.php — 알림 화면
 * ============================================================================
 *
 * URL
 *   /notifications.php                 전체 알림 (최근 100개)
 *   /notifications.php?filter=unread   읽지 않은 알림만
 *   /notifications.php?open=15         알림 클릭: 15번 알림 읽음 처리 → 관련 화면으로 이동
 *
 * 알림 항목 하나는 partials/notification_item.php 가 그린다.
 * (상단 네비게이션의 종 아이콘 드롭다운도 같은 조각을 compact 모드로 사용)
 */
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();
$meId = (int) $me['id'];

// 알림 클릭 → 읽음 처리 후 대상 페이지로 이동
// 모든 알림 링크를 이 주소로 거치게 해서, 어디서 클릭하든 읽음 처리가 빠지지 않게 한다.
if (isset($_GET['open'])) {
    $n = mark_notification_read($meId, (int) $_GET['open']);   // 내 알림이 아니면 null
    if (!$n) {
        redirect('notifications.php');
    }
    // 이동 링크 계산에는 이름이 필요 없으므로 빈 이름을 채워 notification_meta() 를 재사용
    header('Location: ' . notification_meta($n + ['first_name' => '', 'last_name' => ''])['link']);
    exit;
}

$filter = ($_GET['filter'] ?? '') === 'unread' ? 'unread' : 'all';
$items = get_notifications($meId, 100, $filter === 'unread');
$unread = unread_notification_count($meId);

$title = '알림';
$active = 'notifications';
require __DIR__ . '/includes/partials/header.php';
?>
<div class="mx-auto max-w-2xl px-0 py-4 sm:px-4">
    <div class="card gap-0 p-2 sm:p-4">
        <div class="flex items-center justify-between px-2 pb-2">
            <h1 class="text-2xl font-bold">알림</h1>
            <?php if ($unread): ?>
                <form method="post" action="<?= url('actions/notifications_read.php') ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-ghost btn-sm text-primary"><?= icon('check-check') ?> 모두 읽음으로 표시</button>
                </form>
            <?php endif ?>
        </div>

        <div class="flex gap-2 px-2 pb-3">
            <a href="<?= url('notifications.php') ?>" class="btn btn-sm rounded-full <?= $filter === 'all' ? 'bg-primary/10 text-primary hover:bg-primary/15' : 'btn-ghost' ?>">전체</a>
            <a href="<?= url('notifications.php?filter=unread') ?>" class="btn btn-sm rounded-full <?= $filter === 'unread' ? 'bg-primary/10 text-primary hover:bg-primary/15' : 'btn-ghost' ?>">
                읽지 않음 <?php if ($unread): ?><span class="badge badge-destructive h-5 rounded-full px-1.5"><?= $unread ?></span><?php endif ?>
            </a>
        </div>

        <?php if (!$items): ?>
            <div class="flex flex-col items-center gap-3 py-16 text-center">
                <div class="flex size-14 items-center justify-center rounded-full bg-muted"><?= icon('bell-off', 'size-7 text-muted-foreground') ?></div>
                <p class="text-muted-foreground"><?= $filter === 'unread' ? '읽지 않은 알림이 없습니다.' : '아직 알림이 없습니다.' ?></p>
            </div>
        <?php endif ?>

        <div class="space-y-0.5">
            <?php foreach ($items as $n): partial('notification_item', ['n' => $n]); endforeach ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
