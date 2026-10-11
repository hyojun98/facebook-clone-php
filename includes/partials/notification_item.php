<?php
/**
 * ============================================================================
 * partials/notification_item.php — 알림 1개
 * ============================================================================
 *
 * 사용처: 상단 알림 드롭다운(header.php, compact=true), 알림 화면(notifications.php)
 *
 * 구성: [행동한 사람 사진 + 종류 아이콘 배지] [문구 + 시간] [읽지 않음 파란 점]
 * 링크는 notifications.php?open=ID 로 가서 읽음 처리 후 실제 대상 화면으로 이동한다.
 * 문구/아이콘/색상은 notification_meta() 가 알림 종류에 따라 정한다.
 *
 * @var array $n        알림 행
 * @var bool  $compact  드롭다운용 작은 크기
 */
$compact = $compact ?? false;
$meta = notification_meta($n);
$unread = !(int) $n['is_read'];
?>
<a href="<?= url('notifications.php?open=' . (int) $n['id']) ?>"
   class="flex items-center gap-3 rounded-lg p-2 transition-colors hover:bg-accent <?= $compact ? '' : 'sm:p-3' ?>">
    <span class="relative shrink-0">
        <?= avatar($n, $compact ? 'size-12' : 'size-14', 'text-base') ?>
        <span class="absolute -bottom-1 -right-1 flex size-6 items-center justify-center rounded-full text-white ring-2 ring-popover <?= $meta['color'] ?>">
            <?= icon($meta['icon'], 'size-3.5') ?>
        </span>
    </span>
    <span class="min-w-0 flex-1">
        <span class="line-clamp-3 text-sm <?= $unread ? '' : 'text-muted-foreground' ?>"><?= $meta['text'] ?></span>
        <span class="mt-0.5 block text-xs <?= $unread ? 'font-semibold text-primary' : 'text-muted-foreground' ?>"><?= time_ago($n['created_at']) ?></span>
    </span>
    <?php if ($unread): ?><span class="size-3 shrink-0 rounded-full bg-primary" aria-label="읽지 않음"></span><?php endif ?>
</a>
