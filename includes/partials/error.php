<?php
/**
 * ============================================================================
 * partials/error.php — 오류 화면 (404, 403, 419 ...)
 * ============================================================================
 *
 * helpers.php 의 abort() 가 직접 require 한다. (abort 함수 안의 변수를 그대로 사용)
 * 로그인 상태면 상단 네비게이션을 포함해 그리고, 비로그인이면 네비게이션 없이($bare) 그린다.
 *
 * @var int $status @var string $title @var string $message
 */
$bare = !current_user();
require __DIR__ . '/header.php';
?>
<div class="mx-auto flex max-w-md flex-col items-center px-4 py-24 text-center">
    <div class="mb-6 flex size-16 items-center justify-center rounded-full bg-muted"><?= icon('ghost', 'size-8 text-muted-foreground') ?></div>
    <p class="text-sm font-medium text-muted-foreground"><?= (int) $status ?></p>
    <h1 class="mt-1 text-2xl font-bold tracking-tight"><?= e($title) ?></h1>
    <?php if ($message): ?><p class="mt-2 text-muted-foreground"><?= e($message) ?></p><?php endif ?>
    <a href="<?= url('index.php') ?>" class="btn btn-primary mt-6"><?= icon('house') ?> 홈으로 가기</a>
</div>
<?php require __DIR__ . '/footer.php'; ?>
