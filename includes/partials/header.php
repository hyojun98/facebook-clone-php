<?php
/**
 * ============================================================================
 * partials/header.php — 모든 화면 공통 레이아웃의 앞부분
 * ============================================================================
 *
 * 각 페이지에서 변수를 정한 뒤 require 한다. 짝이 되는 파일은 footer.php.
 *   $title = '홈'; $active = 'home';
 *   require __DIR__ . '/includes/partials/header.php';
 *   ... 페이지 본문 ...
 *   require __DIR__ . '/includes/partials/footer.php';
 *
 * 이 파일이 출력하는 것
 *   1. <head>
 *      - 다크모드 깜빡임 방지 스크립트 (CSS 가 그려지기 전에 html 에 .dark 클래스 적용)
 *      - Pretendard 웹폰트
 *      - Tailwind CSS v4 Play CDN + <style type="text/tailwindcss">
 *          · @theme inline : shadcn/ui 색상 토큰(--background, --primary ...)을 Tailwind 유틸리티(bg-primary 등)로 연결
 *          · :root / .dark : 라이트/다크 모드 실제 색상 값 (shadcn 기본 neutral 테마 + 파란색 primary)
 *          · @layer components : shadcn 컴포넌트를 흉내 낸 클래스 (.btn, .input, .card, .dropdown-menu ...)
 *   2. 상단 네비게이션 (로그인 상태 && $bare 가 아닐 때)
 *      - 왼쪽: 로고 + 검색창 / 가운데: 홈·친구·사진·알림 아이콘 / 오른쪽: 이름, 알림 드롭다운, 계정 드롭다운
 *   3. 모바일 하단 탭바 (md 미만 화면)
 *   4. 플래시 메시지 토스트 영역 (#toaster) — 4초 후 app.js 가 자동으로 닫음
 *   5. <main> 시작 태그
 *
 * @var string $title   페이지 제목
 * @var string $active  현재 메뉴 (home|friends|profile|notifications)
 * @var bool   $bare    true면 상단 네비게이션 없이 렌더 (로그인/회원가입)
 */
$title  = $title ?? '';
$active = $active ?? '';
$bare   = $bare ?? false;
$me     = current_user();
$appName = config('app_name');
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="base-url" content="<?= e(base_url()) ?>">
    <title><?= e($title ? "$title | $appName" : $appName) ?></title>

    <!-- 다크모드 깜빡임 방지 -->
    <script>
        (function () {
            try {
                var t = localStorage.getItem('theme');
                if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard@v1.3.9/dist/web/variable/pretendardvariable-dynamic-subset.min.css">

    <!-- Tailwind CSS v4 (Play CDN) -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style type="text/tailwindcss">
        @custom-variant dark (&:is(.dark *));

        /* shadcn/ui 테마 토큰 → Tailwind 유틸리티 */
        @theme inline {
            --font-sans: "Pretendard Variable", Pretendard, ui-sans-serif, system-ui, -apple-system, sans-serif;
            --color-background: var(--background);
            --color-foreground: var(--foreground);
            --color-card: var(--card);
            --color-card-foreground: var(--card-foreground);
            --color-popover: var(--popover);
            --color-popover-foreground: var(--popover-foreground);
            --color-primary: var(--primary);
            --color-primary-foreground: var(--primary-foreground);
            --color-secondary: var(--secondary);
            --color-secondary-foreground: var(--secondary-foreground);
            --color-muted: var(--muted);
            --color-muted-foreground: var(--muted-foreground);
            --color-accent: var(--accent);
            --color-accent-foreground: var(--accent-foreground);
            --color-destructive: var(--destructive);
            --color-border: var(--border);
            --color-input: var(--input);
            --color-ring: var(--ring);
            --radius-sm: calc(var(--radius) * 0.6);
            --radius-md: calc(var(--radius) * 0.8);
            --radius-lg: var(--radius);
            --radius-xl: calc(var(--radius) * 1.4);
            --radius-2xl: calc(var(--radius) * 1.8);
        }

        :root {
            --radius: 0.625rem;
            --background: oklch(1 0 0);
            --foreground: oklch(0.145 0 0);
            --card: oklch(1 0 0);
            --card-foreground: oklch(0.145 0 0);
            --popover: oklch(1 0 0);
            --popover-foreground: oklch(0.145 0 0);
            --primary: oklch(0.546 0.245 262.881);
            --primary-foreground: oklch(0.985 0 0);
            --secondary: oklch(0.97 0 0);
            --secondary-foreground: oklch(0.205 0 0);
            --muted: oklch(0.97 0 0);
            --muted-foreground: oklch(0.556 0 0);
            --accent: oklch(0.97 0 0);
            --accent-foreground: oklch(0.205 0 0);
            --destructive: oklch(0.577 0.245 27.325);
            --border: oklch(0.922 0 0);
            --input: oklch(0.922 0 0);
            --ring: oklch(0.708 0 0);
            --page: oklch(0.975 0.002 264);
        }

        .dark {
            --background: oklch(0.145 0 0);
            --foreground: oklch(0.985 0 0);
            --card: oklch(0.205 0 0);
            --card-foreground: oklch(0.985 0 0);
            --popover: oklch(0.205 0 0);
            --popover-foreground: oklch(0.985 0 0);
            --primary: oklch(0.623 0.214 259.815);
            --primary-foreground: oklch(0.985 0 0);
            --secondary: oklch(0.269 0 0);
            --secondary-foreground: oklch(0.985 0 0);
            --muted: oklch(0.269 0 0);
            --muted-foreground: oklch(0.708 0 0);
            --accent: oklch(0.269 0 0);
            --accent-foreground: oklch(0.985 0 0);
            --destructive: oklch(0.704 0.191 22.216);
            --border: oklch(1 0 0 / 10%);
            --input: oklch(1 0 0 / 15%);
            --ring: oklch(0.556 0 0);
            --page: oklch(0.145 0 0);
        }

        @layer base {
            * { @apply border-border outline-ring/50; }
            html { background: var(--page); }
            body { @apply text-foreground antialiased; background: var(--page); }
            [x-cloak], [hidden] { display: none !important; }
        }

        /* shadcn/ui 컴포넌트 스타일 */
        @layer components {
            .btn { @apply inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all cursor-pointer select-none
                     disabled:pointer-events-none disabled:opacity-50 outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50
                     h-9 px-4 py-2 [&_svg]:size-4 [&_svg]:shrink-0; }
            .btn-primary { @apply bg-primary text-primary-foreground shadow-xs hover:bg-primary/90; }
            .btn-secondary { @apply bg-secondary text-secondary-foreground hover:bg-secondary/80; }
            .btn-outline { @apply border bg-background shadow-xs hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50; }
            .btn-ghost { @apply hover:bg-accent hover:text-accent-foreground dark:hover:bg-accent/50; }
            .btn-destructive { @apply bg-destructive text-white shadow-xs hover:bg-destructive/90; }
            .btn-link { @apply text-primary underline-offset-4 hover:underline; }
            .btn-sm { @apply h-8 gap-1.5 px-3; }
            .btn-lg { @apply h-10 px-6; }
            .btn-icon { @apply size-9 p-0; }

            .input { @apply flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none
                       placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]
                       disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30; }
            .textarea { @apply flex min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none
                          placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] md:text-sm dark:bg-input/30; }
            .select { @apply h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] dark:bg-input/30; }
            .label { @apply flex items-center gap-2 text-sm leading-none font-medium select-none; }

            .card { @apply bg-card text-card-foreground flex flex-col rounded-xl border shadow-sm; }
            .card-header { @apply flex flex-col gap-1.5 px-6 pt-6; }
            .card-title { @apply leading-none font-semibold; }
            .card-description { @apply text-muted-foreground text-sm; }
            .card-content { @apply px-6; }

            .badge { @apply inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 gap-1; }
            .badge-default { @apply border-transparent bg-primary text-primary-foreground; }
            .badge-secondary { @apply border-transparent bg-secondary text-secondary-foreground; }
            .badge-destructive { @apply border-transparent bg-destructive text-white; }
            .count-badge { @apply absolute -top-1 -right-1 min-w-5 h-5 px-1 rounded-full bg-destructive text-white text-[11px] font-semibold flex items-center justify-center ring-2 ring-background; }

            .dropdown-menu { @apply absolute z-50 min-w-[12rem] overflow-hidden rounded-md border bg-popover p-1 text-popover-foreground shadow-md; }
            .dropdown-item { @apply relative flex w-full cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-none select-none hover:bg-accent hover:text-accent-foreground [&_svg]:size-4 [&_svg]:text-muted-foreground; }
            .dropdown-separator { @apply -mx-1 my-1 h-px bg-border; }

            .tab { @apply inline-flex items-center gap-2 h-full px-4 text-sm font-medium text-muted-foreground border-b-2 border-transparent hover:text-foreground transition-colors; }
            .tab-active { @apply text-primary border-primary; }

            .nav-item { @apply relative flex items-center justify-center h-12 w-full max-w-28 rounded-md text-muted-foreground hover:bg-accent transition-colors; }
            .nav-item-active { @apply text-primary hover:bg-transparent after:absolute after:-bottom-1 after:inset-x-0 after:h-[3px] after:rounded-full after:bg-primary; }

            .side-link { @apply flex items-center gap-3 rounded-lg px-2 py-2 text-sm font-medium hover:bg-accent transition-colors; }
        }
    </style>
</head>
<body class="min-h-screen font-sans">
<?php if (!$bare && $me):
    // 네비게이션 배지/드롭다운에 쓰는 값 (모든 화면에서 요청마다 조회)
    $unreadCount = unread_notification_count((int) $me['id']);   // 종 아이콘 빨간 숫자
    $requestCount = pending_request_count((int) $me['id']);      // 친구 아이콘 빨간 숫자
    $navNotifications = get_notifications((int) $me['id'], 8);   // 알림 드롭다운의 최근 8개
?>
<header class="sticky top-0 z-40 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80">
    <div class="mx-auto flex h-14 max-w-screen-2xl items-center gap-2 px-3 sm:px-4">
        <!-- 로고 + 검색 -->
        <div class="flex min-w-0 flex-1 items-center gap-2">
            <a href="<?= url('index.php') ?>" class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary text-2xl font-black text-primary-foreground" aria-label="홈">f</a>
            <form action="<?= url('search.php') ?>" method="get" class="relative hidden w-full max-w-60 sm:block">
                <?= icon('search', 'pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground') ?>
                <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="<?= e($appName) ?> 검색" class="input h-10 rounded-full border-transparent bg-muted pl-9 shadow-none">
            </form>
            <a href="<?= url('search.php') ?>" class="btn btn-ghost btn-icon rounded-full sm:hidden" aria-label="검색"><?= icon('search') ?></a>
        </div>

        <!-- 가운데 메뉴 -->
        <nav class="hidden flex-1 items-center justify-center gap-1 md:flex">
            <a href="<?= url('index.php') ?>" class="nav-item <?= $active === 'home' ? 'nav-item-active' : '' ?>" title="홈"><?= icon('house', 'size-6') ?></a>
            <a href="<?= url('friends.php') ?>" class="nav-item <?= $active === 'friends' ? 'nav-item-active' : '' ?>" title="친구">
                <span class="relative"><?= icon('users', 'size-6') ?><?php if ($requestCount): ?><span class="count-badge -right-3"><?= $requestCount ?></span><?php endif ?></span>
            </a>
            <a href="<?= profile_url((int) $me['id'], 'photos') ?>" class="nav-item <?= $active === 'photos' ? 'nav-item-active' : '' ?>" title="사진"><?= icon('images', 'size-6') ?></a>
            <a href="<?= url('notifications.php') ?>" class="nav-item <?= $active === 'notifications' ? 'nav-item-active' : '' ?>" title="알림"><?= icon('bell', 'size-6') ?></a>
        </nav>

        <!-- 오른쪽 메뉴 -->
        <div class="flex flex-1 items-center justify-end gap-2">
            <a href="<?= profile_url((int) $me['id']) ?>" class="hidden items-center gap-2 rounded-full py-1 pl-1 pr-3 text-sm font-semibold hover:bg-accent lg:flex">
                <?= avatar($me, 'size-7', 'text-[10px]') ?>
                <span class="max-w-32 truncate"><?= e(full_name($me)) ?></span>
            </a>

            <a href="<?= url('friends.php?tab=requests') ?>" class="btn btn-secondary btn-icon relative size-10 rounded-full md:hidden" aria-label="친구">
                <?= icon('users') ?>
                <?php if ($requestCount): ?><span class="count-badge"><?= $requestCount ?></span><?php endif ?>
            </a>

            <!-- 알림 드롭다운
                 data-dropdown(감싸는 요소) / data-dropdown-trigger(버튼) / data-dropdown-menu(메뉴, hidden 속성으로 숨김)
                 → assets/app.js 의 "드롭다운" 섹션이 클릭 시 열고 닫는다. -->
            <div class="relative" data-dropdown>
                <button type="button" class="btn btn-secondary btn-icon relative size-10 rounded-full" data-dropdown-trigger aria-label="알림">
                    <?= icon('bell') ?>
                    <?php if ($unreadCount): ?><span class="count-badge" id="nav-noti-badge"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span><?php endif ?>
                </button>
                <div class="dropdown-menu right-0 mt-2 w-[22rem] max-w-[calc(100vw-1.5rem)] p-0" data-dropdown-menu hidden>
                    <div class="flex items-center justify-between px-4 pb-2 pt-3">
                        <h3 class="text-lg font-bold">알림</h3>
                        <?php if ($unreadCount): ?>
                            <form method="post" action="<?= url('actions/notifications_read.php') ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-link btn-sm h-auto px-0 text-xs">모두 읽음으로 표시</button>
                            </form>
                        <?php endif ?>
                    </div>
                    <div class="max-h-[60vh] overflow-y-auto p-1">
                        <?php if (!$navNotifications): ?>
                            <p class="px-4 py-8 text-center text-sm text-muted-foreground">새로운 알림이 없습니다.</p>
                        <?php endif ?>
                        <?php foreach ($navNotifications as $n): partial('notification_item', ['n' => $n, 'compact' => true]); endforeach ?>
                    </div>
                    <div class="border-t p-1">
                        <a href="<?= url('notifications.php') ?>" class="dropdown-item justify-center font-medium text-primary">모든 알림 보기</a>
                    </div>
                </div>
            </div>

            <!-- 계정 드롭다운 -->
            <div class="relative" data-dropdown>
                <button type="button" class="rounded-full outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50" data-dropdown-trigger aria-label="계정 메뉴">
                    <?= avatar($me, 'size-10', 'text-sm') ?>
                </button>
                <div class="dropdown-menu right-0 mt-2 w-64" data-dropdown-menu hidden>
                    <a href="<?= profile_url((int) $me['id']) ?>" class="dropdown-item gap-3 py-2">
                        <?= avatar($me, 'size-9', 'text-xs') ?>
                        <div class="min-w-0">
                            <p class="truncate font-semibold"><?= e(full_name($me)) ?></p>
                            <p class="truncate text-xs text-muted-foreground"><?= e($me['email']) ?></p>
                        </div>
                    </a>
                    <div class="dropdown-separator"></div>
                    <a href="<?= profile_url((int) $me['id']) ?>" class="dropdown-item"><?= icon('user') ?> 내 프로필</a>
                    <a href="<?= url('friends.php') ?>" class="dropdown-item"><?= icon('users') ?> 친구</a>
                    <a href="<?= url('settings.php') ?>" class="dropdown-item"><?= icon('settings') ?> 설정</a>
                    <button type="button" class="dropdown-item" data-theme-toggle>
                        <?= icon('moon', 'dark:hidden') ?><?= icon('sun', 'hidden dark:block') ?>
                        <span class="dark:hidden">다크 모드</span><span class="hidden dark:inline">라이트 모드</span>
                    </button>
                    <div class="dropdown-separator"></div>
                    <form method="post" action="<?= url('logout.php') ?>">
                        <?= csrf_field() ?>
                        <button class="dropdown-item"><?= icon('log-out') ?> 로그아웃</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- 모바일 하단 탭 -->
<nav class="fixed inset-x-0 bottom-0 z-40 flex h-14 items-center justify-around border-t bg-background md:hidden">
    <a href="<?= url('index.php') ?>" class="flex flex-1 justify-center <?= $active === 'home' ? 'text-primary' : 'text-muted-foreground' ?>"><?= icon('house', 'size-6') ?></a>
    <a href="<?= url('friends.php') ?>" class="flex flex-1 justify-center <?= $active === 'friends' ? 'text-primary' : 'text-muted-foreground' ?>"><?= icon('users', 'size-6') ?></a>
    <a href="<?= url('notifications.php') ?>" class="relative flex flex-1 justify-center <?= $active === 'notifications' ? 'text-primary' : 'text-muted-foreground' ?>">
        <span class="relative"><?= icon('bell', 'size-6') ?><?php if ($unreadCount): ?><span class="count-badge"><?= $unreadCount ?></span><?php endif ?></span>
    </a>
    <a href="<?= profile_url((int) $me['id']) ?>" class="flex flex-1 justify-center <?= $active === 'profile' ? 'text-primary' : 'text-muted-foreground' ?>"><?= icon('circle-user-round', 'size-6') ?></a>
</nav>
<?php endif ?>

<!-- 플래시 메시지 (shadcn Sonner 스타일 토스트) -->
<div id="toaster" class="pointer-events-none fixed bottom-20 right-4 z-[100] flex w-[calc(100%-2rem)] max-w-sm flex-col gap-2 md:bottom-4">
    <?php foreach (pull_flashes() as $f): ?>
        <div class="toast pointer-events-auto flex items-start gap-3 rounded-lg border bg-popover p-4 text-sm text-popover-foreground shadow-lg" data-toast>
            <?= icon(match ($f['type']) { 'success' => 'circle-check', 'error' => 'circle-alert', default => 'info' },
                'size-5 shrink-0 ' . match ($f['type']) { 'success' => 'text-emerald-500', 'error' => 'text-destructive', default => 'text-primary' }) ?>
            <p class="flex-1"><?= e($f['message']) ?></p>
        </div>
    <?php endforeach ?>
</div>

<main class="<?= $bare ? '' : 'pb-16 md:pb-0' ?>">
