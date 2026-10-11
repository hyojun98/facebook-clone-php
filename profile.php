<?php
/**
 * ============================================================================
 * profile.php — 프로필 화면
 * ============================================================================
 *
 * URL
 *   /profile.php                 내 프로필
 *   /profile.php?id=3            다른 사람 프로필
 *   /profile.php?id=3&tab=about  탭: posts(기본) | about | friends | photos
 *   ...&page=2&partial=1         게시물 탭 "더 보기" AJAX (JSON)
 *
 * 화면 구성
 *   [프로필 헤더] 커버 사진, 프로필 사진, 이름, 친구 수/친구 얼굴, 버튼, 탭 메뉴
 *     - 내 프로필  : 커버/프로필 사진 변경(카메라 버튼), [프로필 편집]
 *     - 남의 프로필: 관계에 맞는 친구 버튼 (친구 추가 / 요청 취소 / 확인·삭제 / 친구 끊기)
 *   [게시물 탭]   왼쪽: 소개·사진·친구 카드 / 오른쪽: (내 프로필이면) 작성 카드 + 게시물 목록
 *   [정보 탭]     소개, 직장, 학교, 거주지, 생일, 성별, 가입일
 *   [친구 탭]     친구 목록 (+ 함께 아는 친구 수)
 *   [사진 탭]     볼 수 있는 게시물의 사진/동영상 그리드 (클릭 시 라이트박스)
 *
 * 공개 범위는 profile_posts(), user_media() 가 적용한다. (친구가 아니면 '친구만' 글은 안 보임)
 */
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();
$meId = (int) $me['id'];

// id 가 없으면 내 프로필
$userId = (int) ($_GET['id'] ?? $meId);
$user = find_user($userId);
if (!$user) {
    abort(404, '사용자를 찾을 수 없습니다.');
}

// 허용된 탭 이름만 사용 (그 외 값은 게시물 탭)
$tab = in_array($_GET['tab'] ?? '', ['posts', 'about', 'friends', 'photos'], true) ? $_GET['tab'] : 'posts';
$isMe = $userId === $meId;
$status = friend_status($meId, $userId);   // self | none | request_sent | request_received | friends
$page = max(1, (int) ($_GET['page'] ?? 1));

if ($tab === 'posts') {
    $posts = profile_posts($userId, $meId, $page);
    $hasMore = count($posts) === (int) config('per_page');
    if (isset($_GET['partial'])) {
        $html = '';
        foreach ($posts as $post) {
            $html .= render_partial('post_card', ['post' => $post]);
        }
        json_response(['html' => $html, 'hasMore' => $hasMore]);
    }
}

$friends = get_friends($userId);
$friendTotal = count($friends);
// 사진 탭은 최대 120개, 게시물 탭 왼쪽 "사진" 카드는 3x3 = 9개
$media = user_media($userId, $meId, $tab === 'photos' ? 120 : 9);

$tabs = ['posts' => '게시물', 'about' => '정보', 'friends' => '친구', 'photos' => '사진/동영상'];
$genders = ['female' => '여성', 'male' => '남성', 'other' => '기타'];

// 소개/정보 탭에 표시할 행: [아이콘, HTML 문구]. 값이 비어 있는 항목(null)은 array_filter 로 제외.
// 사용자 입력값은 여기서 e() 로 이스케이프해 두고, 템플릿에서는 그대로 출력한다.
$aboutRows = array_filter([
    ['briefcase', $user['workplace'] ? e($user['workplace']) . '에서 근무' : null],
    ['graduation-cap', $user['education'] ? e($user['education']) . '에서 공부' : null],
    ['map-pin', $user['location'] ? e($user['location']) . ' 거주' : null],
    ['cake', $user['birthday'] ? date('Y년 n월 j일', strtotime($user['birthday'])) . ' 출생' : null],
    ['user', $user['gender'] ? $genders[$user['gender']] : null],
    ['clock', date('Y년 n월', strtotime($user['created_at'])) . '에 가입'],
], fn ($r) => $r[1] !== null);

$title = full_name($user);
$active = $isMe ? 'profile' : '';
if ($isMe && $tab === 'photos') {
    $active = 'photos';
}
require __DIR__ . '/includes/partials/header.php';
?>
<!-- 프로필 헤더 -->
<section class="border-b bg-card shadow-sm">
    <div class="mx-auto max-w-5xl">
        <!-- 커버 -->
        <div class="relative h-48 overflow-hidden bg-gradient-to-br from-primary/30 via-primary/10 to-muted sm:h-72 md:h-80 md:rounded-b-xl">
            <?php if ($user['cover']): ?>
                <button type="button" class="size-full" data-lightbox-item data-group="cover" data-type="image" data-src="<?= e(media_url($user['cover'])) ?>">
                    <img src="<?= e(media_url($user['cover'])) ?>" alt="커버 사진" class="size-full object-cover">
                </button>
            <?php endif ?>
            <?php if ($isMe): ?>
                <form method="post" action="<?= url('actions/profile_photo.php') ?>" enctype="multipart/form-data" class="absolute bottom-3 right-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="type" value="cover">
                    <label class="btn btn-sm bg-background/90 text-foreground shadow hover:bg-background">
                        <?= icon('camera') ?> <span class="hidden sm:inline">커버 사진 <?= $user['cover'] ? '수정' : '추가' ?></span>
                        <input type="file" name="photo" accept="image/*" class="sr-only" data-auto-submit>
                    </label>
                </form>
            <?php endif ?>
        </div>

        <!-- 프로필 정보 -->
        <div class="px-4 pb-4">
            <div class="flex flex-col items-center gap-4 md:flex-row md:items-end">
                <div class="relative -mt-20 shrink-0 md:-mt-10">
                    <?php if ($user['avatar']): ?>
                        <button type="button" class="block rounded-full" data-lightbox-item data-group="avatar" data-type="image" data-src="<?= e(media_url($user['avatar'])) ?>">
                            <?= avatar($user, 'size-40', 'text-5xl', 'ring-4 ring-card') ?>
                        </button>
                    <?php else: ?>
                        <?= avatar($user, 'size-40', 'text-5xl', 'ring-4 ring-card') ?>
                    <?php endif ?>
                    <?php if ($isMe): ?>
                        <form method="post" action="<?= url('actions/profile_photo.php') ?>" enctype="multipart/form-data" class="absolute bottom-2 right-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="type" value="avatar">
                            <label class="btn btn-secondary btn-icon cursor-pointer rounded-full shadow ring-2 ring-card" title="프로필 사진 변경">
                                <?= icon('camera') ?>
                                <input type="file" name="photo" accept="image/*" class="sr-only" data-auto-submit>
                            </label>
                        </form>
                    <?php endif ?>
                </div>

                <div class="min-w-0 flex-1 text-center md:pb-2 md:text-left">
                    <h1 class="text-3xl font-bold tracking-tight"><?= e(full_name($user)) ?></h1>
                    <a href="<?= profile_url($userId, 'friends') ?>" class="mt-1 inline-block text-sm font-medium text-muted-foreground hover:underline">친구 <?= $friendTotal ?>명</a>
                    <?php if ($friends): ?>
                        <div class="mt-2 flex justify-center -space-x-2 md:justify-start">
                            <?php foreach (array_slice($friends, 0, 8) as $f): ?>
                                <a href="<?= profile_url((int) $f['id']) ?>" title="<?= e(full_name($f)) ?>"><?= avatar($f, 'size-8', 'text-[10px]', 'ring-2 ring-card') ?></a>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                </div>

                <div class="md:pb-2">
                    <?php if ($isMe): ?>
                        <a href="<?= url('settings.php') ?>" class="btn btn-secondary"><?= icon('pencil') ?> 프로필 편집</a>
                    <?php else: ?>
                        <?php partial('friend_buttons', ['userId' => $userId, 'status' => $status]) ?>
                    <?php endif ?>
                </div>
            </div>

            <?php if ($status === 'request_received'): ?>
                <div class="mt-4 flex items-center gap-2 rounded-lg border bg-muted/50 px-4 py-3 text-sm">
                    <?= icon('user-plus', 'size-4 text-primary') ?> <span><strong><?= e(full_name($user)) ?></strong>님이 친구 요청을 보냈습니다.</span>
                </div>
            <?php endif ?>
        </div>

        <!-- 탭 -->
        <nav class="flex h-12 overflow-x-auto border-t px-2">
            <?php foreach ($tabs as $key => $label): ?>
                <a href="<?= profile_url($userId, $key === 'posts' ? '' : $key) ?>" class="tab <?= $tab === $key ? 'tab-active' : '' ?>"><?= e($label) ?></a>
            <?php endforeach ?>
        </nav>
    </div>
</section>

<div class="mx-auto max-w-5xl px-0 py-4 sm:px-4">
<?php if ($tab === 'posts'): ?>
    <div class="grid gap-4 lg:grid-cols-[2fr_3fr]">
        <!-- 왼쪽: 소개 / 사진 / 친구 -->
        <div class="space-y-4 lg:sticky lg:top-[4.5rem] lg:self-start">
            <div class="card gap-3 p-4">
                <h2 class="text-lg font-bold">소개</h2>
                <?php if ($user['bio']): ?><p class="text-center text-[15px]"><?= e($user['bio']) ?></p><?php endif ?>
                <?php if ($isMe): ?><a href="<?= url('settings.php') ?>" class="btn btn-secondary w-full">소개 수정</a><?php endif ?>
                <ul class="space-y-3 text-[15px]">
                    <?php foreach ($aboutRows as [$ic, $text]): ?>
                        <li class="flex items-center gap-3"><?= icon($ic, 'size-5 shrink-0 text-muted-foreground') ?><span><?= $text ?></span></li>
                    <?php endforeach ?>
                </ul>
            </div>

            <div class="card gap-3 p-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold">사진</h2>
                    <a href="<?= profile_url($userId, 'photos') ?>" class="text-sm text-primary hover:underline">모두 보기</a>
                </div>
                <?php if ($media): ?>
                    <div class="grid grid-cols-3 gap-1 overflow-hidden rounded-lg">
                        <?php foreach ($media as $m): ?>
                            <a href="<?= url('post.php?id=' . (int) $m['post_id']) ?>" class="relative aspect-square bg-muted">
                                <?php if ($m['media_type'] === 'video'): ?>
                                    <video src="<?= e(media_url($m['file_path'])) ?>#t=0.1" muted preload="metadata" class="size-full object-cover"></video>
                                    <?= icon('play', 'absolute left-1.5 top-1.5 size-4 fill-white text-white drop-shadow') ?>
                                <?php else: ?>
                                    <img src="<?= e(media_url($m['file_path'])) ?>" alt="" loading="lazy" class="size-full object-cover">
                                <?php endif ?>
                            </a>
                        <?php endforeach ?>
                    </div>
                <?php else: ?>
                    <p class="text-sm text-muted-foreground">표시할 사진이 없습니다.</p>
                <?php endif ?>
            </div>

            <div class="card gap-3 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold">친구</h2>
                        <p class="text-sm text-muted-foreground">친구 <?= $friendTotal ?>명</p>
                    </div>
                    <a href="<?= profile_url($userId, 'friends') ?>" class="text-sm text-primary hover:underline">모두 보기</a>
                </div>
                <div class="grid grid-cols-3 gap-x-2 gap-y-3">
                    <?php foreach (array_slice($friends, 0, 9) as $f): ?>
                        <a href="<?= profile_url((int) $f['id']) ?>" class="group">
                            <div class="aspect-square overflow-hidden rounded-lg">
                                <?= avatar($f, 'size-full', 'text-2xl', '!rounded-lg') ?>
                            </div>
                            <p class="mt-1 truncate text-xs font-semibold group-hover:underline"><?= e(full_name($f)) ?></p>
                        </a>
                    <?php endforeach ?>
                </div>
            </div>
        </div>

        <!-- 오른쪽: 게시물 -->
        <div class="space-y-4">
            <?php if ($isMe): partial('composer'); endif ?>
            <div class="space-y-4" id="feed">
                <?php foreach ($posts as $post): partial('post_card', ['post' => $post]); endforeach ?>
            </div>
            <?php if (!$posts): ?>
                <div class="card items-center px-6 py-12 text-center text-muted-foreground">
                    <?= icon('file-text', 'size-8 mb-2') ?>
                    <p>표시할 게시물이 없습니다.</p>
                </div>
            <?php elseif ($hasMore): ?>
                <button type="button" class="btn btn-outline w-full" data-load-more="<?= e(url('profile.php?id=' . $userId)) ?>" data-page="<?= $page + 1 ?>">
                    <?= icon('loader-circle', 'hidden animate-spin') ?> 게시물 더 보기
                </button>
            <?php endif ?>
        </div>
    </div>

<?php elseif ($tab === 'about'): ?>
    <div class="card gap-4 p-6">
        <h2 class="text-xl font-bold">정보</h2>
        <?php if ($user['bio']): ?>
            <p class="rounded-lg bg-muted/60 p-4 text-[15px]"><?= e($user['bio']) ?></p>
        <?php endif ?>
        <ul class="divide-y">
            <?php foreach ($aboutRows as [$ic, $text]): ?>
                <li class="flex items-center gap-4 py-3">
                    <span class="flex size-10 items-center justify-center rounded-full bg-muted"><?= icon($ic, 'size-5 text-muted-foreground') ?></span>
                    <span class="text-[15px]"><?= $text ?></span>
                </li>
            <?php endforeach ?>
        </ul>
        <?php if ($isMe): ?><a href="<?= url('settings.php') ?>" class="btn btn-secondary self-start"><?= icon('pencil') ?> 정보 수정</a><?php endif ?>
    </div>

<?php elseif ($tab === 'friends'): ?>
    <div class="card gap-4 p-4 sm:p-6">
        <h2 class="text-xl font-bold">친구 <span class="text-muted-foreground"><?= $friendTotal ?></span></h2>
        <?php if (!$friends): ?>
            <p class="py-8 text-center text-muted-foreground">표시할 친구가 없습니다.</p>
        <?php endif ?>
        <div class="grid gap-3 sm:grid-cols-2">
            <?php foreach ($friends as $f): $mutual = $isMe ? 0 : mutual_friend_count($meId, (int) $f['id']); ?>
                <div class="flex items-center gap-3 rounded-xl border p-3">
                    <a href="<?= profile_url((int) $f['id']) ?>"><?= avatar($f, 'size-20', 'text-xl', '!rounded-lg') ?></a>
                    <div class="min-w-0 flex-1">
                        <a href="<?= profile_url((int) $f['id']) ?>" class="block truncate font-semibold hover:underline"><?= e(full_name($f)) ?></a>
                        <?php if ($mutual): ?><p class="text-sm text-muted-foreground">함께 아는 친구 <?= $mutual ?>명</p><?php endif ?>
                        <?php if ($f['location']): ?><p class="truncate text-sm text-muted-foreground"><?= e($f['location']) ?></p><?php endif ?>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </div>

<?php else: /* photos */ ?>
    <div class="card gap-4 p-4 sm:p-6">
        <h2 class="text-xl font-bold">사진 및 동영상</h2>
        <?php if (!$media): ?>
            <p class="py-8 text-center text-muted-foreground">표시할 사진이 없습니다.</p>
        <?php endif ?>
        <div class="grid grid-cols-3 gap-1 sm:grid-cols-4 md:grid-cols-5">
            <?php foreach ($media as $m): ?>
                <button type="button" class="relative aspect-square overflow-hidden rounded-md bg-muted" data-lightbox-item data-group="profile-media" data-type="<?= e($m['media_type']) ?>" data-src="<?= e(media_url($m['file_path'])) ?>">
                    <?php if ($m['media_type'] === 'video'): ?>
                        <video src="<?= e(media_url($m['file_path'])) ?>#t=0.1" muted preload="metadata" class="size-full object-cover"></video>
                        <span class="absolute inset-0 flex items-center justify-center"><span class="flex size-10 items-center justify-center rounded-full bg-black/60 text-white"><?= icon('play', 'size-5 fill-current') ?></span></span>
                    <?php else: ?>
                        <img src="<?= e(media_url($m['file_path'])) ?>" alt="" loading="lazy" class="size-full object-cover transition-transform hover:scale-105">
                    <?php endif ?>
                </button>
            <?php endforeach ?>
        </div>
    </div>
<?php endif ?>
</div>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
