<?php
/**
 * ============================================================================
 * register.php — 회원가입 화면 + 가입 처리
 * ============================================================================
 *
 * 입력 항목 : 성, 이름, 이메일, 비밀번호(8자 이상) + 확인, 생년월일(년/월/일 select), 성별
 * 검증 실패 : $errors['필드'] 에 메시지를 담아 같은 화면을 다시 그린다. (입력값은 old() 로 유지)
 * 가입 성공 : 자동 로그인 → 친구 화면의 "알 수도 있는 사람" 탭으로 이동
 */
require __DIR__ . '/includes/bootstrap.php';
require_guest();

$errors = [];   // ['email' => '이미 사용 중인 이메일입니다.', ...]

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $data = [
        'last_name'  => trim((string) ($_POST['last_name'] ?? '')),
        'first_name' => trim((string) ($_POST['first_name'] ?? '')),
        'email'      => trim((string) ($_POST['email'] ?? '')),
        'password'   => (string) ($_POST['password'] ?? ''),
        'password_confirm' => (string) ($_POST['password_confirm'] ?? ''),
        'gender'     => (string) ($_POST['gender'] ?? ''),
        'birthday'   => '',
    ];

    if ($data['last_name'] === '' || mb_strlen($data['last_name']) > 50)   $errors['last_name'] = '성을 입력하세요.';
    if ($data['first_name'] === '' || mb_strlen($data['first_name']) > 50) $errors['first_name'] = '이름을 입력하세요.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = '올바른 이메일 주소를 입력하세요.';
    } elseif (find_user_by_email($data['email'])) {
        $errors['email'] = '이미 사용 중인 이메일입니다.';
    }
    if (mb_strlen($data['password']) < 8)                 $errors['password'] = '비밀번호는 8자 이상이어야 합니다.';
    elseif ($data['password'] !== $data['password_confirm']) $errors['password_confirm'] = '비밀번호가 일치하지 않습니다.';
    if (!in_array($data['gender'], ['female', 'male', 'other'], true)) $errors['gender'] = '성별을 선택하세요.';

    // 생년월일: checkdate() 로 2월 30일 같은 존재하지 않는 날짜를 걸러낸다.
    $y = (int) ($_POST['birth_year'] ?? 0);
    $m = (int) ($_POST['birth_month'] ?? 0);
    $d = (int) ($_POST['birth_day'] ?? 0);
    if ($y && $m && $d && checkdate($m, $d, $y)) {
        $data['birthday'] = sprintf('%04d-%02d-%02d', $y, $m, $d);
    } else {
        $errors['birthday'] = '올바른 생년월일을 선택하세요.';
    }

    if (!$errors) {
        $id = create_user($data);
        login_user($id);
        flash('success', '가입을 환영합니다! 친구를 찾아보세요 👋');
        redirect('friends.php?tab=suggestions');
    }
    with_old($_POST);
}

$title = '회원가입';
$bare = true;   // 상단 네비게이션 없이 렌더링
// 템플릿 도우미: $err('email') → 필드 아래 빨간 오류 문구, $invalid('email') → 입력칸 빨간 테두리 클래스
$err = fn (string $k) => isset($errors[$k]) ? '<p class="text-xs text-destructive">' . e($errors[$k]) . '</p>' : '';
$invalid = fn (string $k) => isset($errors[$k]) ? 'border-destructive ring-destructive/20 ring-[3px]' : '';
// 생년월일 select 의 선택값 (처음엔 20년 전 오늘 날짜)
$oy = (int) old('birth_year', (string) ((int) date('Y') - 20));
$om = (int) old('birth_month', date('n'));
$od = (int) old('birth_day', date('j'));
require __DIR__ . '/includes/partials/header.php';
?>
<div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
    <a href="<?= url('login.php') ?>" class="mb-6 text-5xl font-extrabold tracking-tight text-primary"><?= e(config('app_name')) ?></a>

    <div class="card w-full max-w-md gap-6 py-6">
        <div class="card-header text-center">
            <h1 class="card-title text-2xl">새 계정 만들기</h1>
            <p class="card-description">빠르고 쉽게 가입하세요.</p>
        </div>

        <form method="post" class="card-content grid gap-4" novalidate>
            <?= csrf_field() ?>
            <div class="grid grid-cols-2 gap-3">
                <div class="grid gap-1.5">
                    <label for="last_name" class="label">성</label>
                    <input id="last_name" name="last_name" value="<?= e(old('last_name')) ?>" class="input <?= $invalid('last_name') ?>" placeholder="홍" autofocus>
                    <?= $err('last_name') ?>
                </div>
                <div class="grid gap-1.5">
                    <label for="first_name" class="label">이름</label>
                    <input id="first_name" name="first_name" value="<?= e(old('first_name')) ?>" class="input <?= $invalid('first_name') ?>" placeholder="길동">
                    <?= $err('first_name') ?>
                </div>
            </div>

            <div class="grid gap-1.5">
                <label for="email" class="label">이메일</label>
                <input id="email" type="email" name="email" value="<?= e(old('email')) ?>" class="input <?= $invalid('email') ?>" placeholder="name@example.com" autocomplete="email">
                <?= $err('email') ?>
            </div>

            <div class="grid gap-1.5">
                <label for="password" class="label">비밀번호</label>
                <input id="password" type="password" name="password" class="input <?= $invalid('password') ?>" placeholder="8자 이상" autocomplete="new-password">
                <?= $err('password') ?>
            </div>
            <div class="grid gap-1.5">
                <label for="password_confirm" class="label">비밀번호 확인</label>
                <input id="password_confirm" type="password" name="password_confirm" class="input <?= $invalid('password_confirm') ?>" autocomplete="new-password">
                <?= $err('password_confirm') ?>
            </div>

            <div class="grid gap-1.5">
                <span class="label">생년월일</span>
                <div class="grid grid-cols-3 gap-2">
                    <select name="birth_year" class="select <?= $invalid('birthday') ?>" aria-label="년">
                        <?php for ($i = (int) date('Y'); $i >= 1905; $i--): ?><option value="<?= $i ?>" <?= $oy === $i ? 'selected' : '' ?>><?= $i ?>년</option><?php endfor ?>
                    </select>
                    <select name="birth_month" class="select <?= $invalid('birthday') ?>" aria-label="월">
                        <?php for ($i = 1; $i <= 12; $i++): ?><option value="<?= $i ?>" <?= $om === $i ? 'selected' : '' ?>><?= $i ?>월</option><?php endfor ?>
                    </select>
                    <select name="birth_day" class="select <?= $invalid('birthday') ?>" aria-label="일">
                        <?php for ($i = 1; $i <= 31; $i++): ?><option value="<?= $i ?>" <?= $od === $i ? 'selected' : '' ?>><?= $i ?>일</option><?php endfor ?>
                    </select>
                </div>
                <?= $err('birthday') ?>
            </div>

            <div class="grid gap-1.5">
                <span class="label">성별</span>
                <div class="grid grid-cols-3 gap-2">
                    <?php foreach (['female' => '여성', 'male' => '남성', 'other' => '기타'] as $val => $label): ?>
                        <label class="flex h-9 cursor-pointer items-center justify-between rounded-md border px-3 text-sm shadow-xs has-[:checked]:border-primary has-[:checked]:bg-primary/5 <?= $invalid('gender') ?>">
                            <?= $label ?>
                            <input type="radio" name="gender" value="<?= $val ?>" class="accent-primary" <?= old('gender') === $val ? 'checked' : '' ?>>
                        </label>
                    <?php endforeach ?>
                </div>
                <?= $err('gender') ?>
            </div>

            <button class="btn btn-lg mt-2 w-full bg-emerald-600 text-base text-white hover:bg-emerald-600/90">가입하기</button>
            <p class="text-center text-sm text-muted-foreground">
                이미 계정이 있으신가요? <a href="<?= url('login.php') ?>" class="font-medium text-primary hover:underline">로그인</a>
            </p>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
