<?php
/**
 * ============================================================================
 * login.php — 로그인 화면 + 로그인 처리
 * ============================================================================
 *
 * GET  : 로그인 폼 표시 (왼쪽 브랜드 문구 + 오른쪽 로그인 카드, 상단 네비게이션 없음 $bare=true)
 * POST : 이메일/비밀번호 확인 → 성공 시 세션에 user_id 저장 후 뉴스피드로 이동
 *
 * 보안
 *   - CSRF 토큰 검증
 *   - password_verify() 로 bcrypt 해시 비교
 *   - "이메일이 없음"과 "비밀번호 틀림"을 구분하지 않고 같은 메시지 → 가입된 이메일 추측 방지
 *   - 로그인 성공 시 세션 ID 재발급 (login_user)
 */
require __DIR__ . '/includes/bootstrap.php';
require_guest();   // 이미 로그인했으면 뉴스피드로

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $user = $email !== '' ? find_user_by_email($email) : null;
    if ($user && password_verify($password, $user['password_hash'])) {
        // PHP 의 기본 해시 알고리즘/비용이 바뀌었으면 로그인하는 김에 새 방식으로 다시 해시해 둔다.
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            update_user((int) $user['id'], ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        }
        login_user((int) $user['id']);
        redirect('index.php');
    }
    $error = '이메일 또는 비밀번호가 올바르지 않습니다.';
    with_old($_POST);   // 입력했던 이메일을 폼에 다시 채우기 위해 보관
}

$title = '로그인';
$bare = true;
require __DIR__ . '/includes/partials/header.php';
?>
<div class="flex min-h-screen items-center justify-center px-4 py-12">
    <div class="grid w-full max-w-5xl items-center gap-10 md:grid-cols-2">
        <div class="text-center md:text-left">
            <h1 class="text-5xl font-extrabold tracking-tight text-primary md:text-6xl"><?= e(config('app_name')) ?></h1>
            <p class="mt-4 text-xl leading-snug md:text-2xl">
                <?= e(config('app_name')) ?>에서 전세계에 있는 친구, 가족, 지인들과 함께 이야기를 나눠보세요.
            </p>
        </div>

        <div class="mx-auto w-full max-w-sm">
            <div class="card gap-6 py-6">
                <div class="card-header">
                    <h2 class="card-title text-xl">로그인</h2>
                    <p class="card-description">이메일과 비밀번호를 입력하세요.</p>
                </div>
                <form method="post" class="card-content grid gap-4" novalidate>
                    <?= csrf_field() ?>
                    <?php if ($error): ?>
                        <div class="flex items-start gap-2 rounded-lg border border-destructive/50 bg-destructive/5 px-3 py-2.5 text-sm text-destructive">
                            <?= icon('circle-alert', 'size-4 mt-0.5 shrink-0') ?><span><?= e($error) ?></span>
                        </div>
                    <?php endif ?>
                    <div class="grid gap-2">
                        <label for="email" class="label">이메일</label>
                        <input id="email" type="email" name="email" value="<?= e(old('email')) ?>" class="input" placeholder="name@example.com" autocomplete="email" required autofocus>
                    </div>
                    <div class="grid gap-2">
                        <label for="password" class="label">비밀번호</label>
                        <input id="password" type="password" name="password" class="input" autocomplete="current-password" required>
                    </div>
                    <button class="btn btn-primary btn-lg w-full text-base">로그인</button>

                    <div class="relative my-1 text-center text-xs uppercase text-muted-foreground">
                        <span class="absolute inset-x-0 top-1/2 h-px bg-border"></span>
                        <span class="relative bg-card px-2">또는</span>
                    </div>
                    <a href="<?= url('register.php') ?>" class="btn btn-lg mx-auto bg-emerald-600 px-6 text-base text-white hover:bg-emerald-600/90">새 계정 만들기</a>
                </form>
            </div>
            <p class="mt-4 text-center text-xs text-muted-foreground">
                데모 계정: <code class="rounded bg-muted px-1 py-0.5">minjun@example.com</code> / <code class="rounded bg-muted px-1 py-0.5">password</code>
            </p>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
