<?php
/**
 * ============================================================================
 * search.php — 사람 검색 화면
 * ============================================================================
 *
 * URL : /search.php?q=검색어  (상단 네비게이션 검색창에서 Enter)
 *
 * 이름(성+이름, 이름 성) 또는 이메일 일부로 검색하고, 결과마다
 * 관계(친구/나), 함께 아는 친구 수, 거주지, 친구 버튼을 보여준다.
 * 검색 로직은 includes/users.php 의 search_users().
 */
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();
$meId = (int) $me['id'];

$term = trim((string) ($_GET['q'] ?? ''));
$results = search_users($term);   // 검색어가 비어 있으면 빈 배열

$title = $term !== '' ? "\"$term\" 검색 결과" : '검색';
require __DIR__ . '/includes/partials/header.php';
?>
<div class="mx-auto max-w-2xl space-y-4 px-0 py-4 sm:px-4">
    <form method="get" class="card flex-row items-center gap-2 p-3">
        <div class="relative flex-1">
            <?= icon('search', 'pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground') ?>
            <input type="search" name="q" value="<?= e($term) ?>" placeholder="이름 또는 이메일로 사람 검색" class="input pl-9" autofocus>
        </div>
        <button class="btn btn-primary">검색</button>
    </form>

    <?php if ($term !== ''): ?>
        <div class="card gap-1 p-2 sm:p-4">
            <h1 class="px-2 pb-2 text-lg font-bold">사람 <span class="font-normal text-muted-foreground"><?= count($results) ?></span></h1>
            <?php if (!$results): ?>
                <p class="py-10 text-center text-muted-foreground">"<?= e($term) ?>"에 대한 검색 결과가 없습니다.</p>
            <?php endif ?>
            <?php foreach ($results as $u):
                $status = friend_status($meId, (int) $u['id']);
                $mutual = $status === 'self' ? 0 : mutual_friend_count($meId, (int) $u['id']); ?>
                <div class="flex items-center gap-3 rounded-lg p-2 hover:bg-accent/50">
                    <a href="<?= profile_url((int) $u['id']) ?>"><?= avatar($u, 'size-14', 'text-base') ?></a>
                    <div class="min-w-0 flex-1">
                        <a href="<?= profile_url((int) $u['id']) ?>" class="block truncate font-semibold hover:underline"><?= e(full_name($u)) ?></a>
                        <p class="truncate text-sm text-muted-foreground">
                            <?= match ($status) { 'friends' => '친구', 'self' => '나', default => '' } ?>
                            <?= $mutual ? " · 함께 아는 친구 {$mutual}명" : '' ?>
                            <?= $u['location'] ? ' · ' . e($u['location']) : '' ?>
                        </p>
                    </div>
                    <?php if ($status !== 'self' && $status !== 'friends'): ?>
                        <?php partial('friend_buttons', ['userId' => (int) $u['id'], 'status' => $status, 'size' => 'btn-sm']) ?>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</div>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
