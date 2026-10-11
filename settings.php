<?php
/**
 * ============================================================================
 * settings.php — 설정 화면 (프로필 사진, 프로필 정보, 비밀번호)
 * ============================================================================
 *
 * 한 화면에 폼이 여러 개 있고, hidden 필드 form 값으로 어떤 폼이 전송됐는지 구분한다.
 *   form=profile       이름, 소개, 직장, 학교, 거주지, 생년월일, 성별 저장
 *   form=password      현재 비밀번호 확인 후 새 비밀번호로 변경
 *   form=remove_photo  프로필/커버 사진 삭제 (type=avatar|cover)
 * 사진 "업로드"는 actions/profile_photo.php 로 따로 전송된다.
 *
 * 저장에 성공하면 리다이렉트(PRG), 실패하면 $errors 와 함께 같은 화면을 다시 그린다.
 */
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();
$meId = (int) $me['id'];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $form = $_POST['form'] ?? '';

    // ---- 프로필 정보 ----
    if ($form === 'profile') {
        $data = [
            'last_name'  => trim((string) ($_POST['last_name'] ?? '')),
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'bio'        => mb_substr(trim((string) ($_POST['bio'] ?? '')), 0, 255),
            'location'   => mb_substr(trim((string) ($_POST['location'] ?? '')), 0, 100),
            'workplace'  => mb_substr(trim((string) ($_POST['workplace'] ?? '')), 0, 100),
            'education'  => mb_substr(trim((string) ($_POST['education'] ?? '')), 0, 100),
            'birthday'   => (string) ($_POST['birthday'] ?? ''),
            'gender'     => in_array($_POST['gender'] ?? '', ['female', 'male', 'other'], true) ? $_POST['gender'] : '',
        ];
        if ($data['last_name'] === '' || mb_strlen($data['last_name']) > 50)   $errors['last_name'] = '성을 입력하세요.';
        if ($data['first_name'] === '' || mb_strlen($data['first_name']) > 50) $errors['first_name'] = '이름을 입력하세요.';
        if ($data['birthday'] !== '') {
            // 형식 + 실제 날짜 검증: '2023-02-30' 은 createFromFormat 이 3월 2일로 바꾸므로 원래 값과 달라져 걸러진다.
            $dt = DateTime::createFromFormat('Y-m-d', $data['birthday']);
            if (!$dt || $dt->format('Y-m-d') !== $data['birthday']) $errors['birthday'] = '올바른 날짜를 입력하세요.';
        }
        if (!$errors) {
            update_user($meId, $data);
            flash('success', '프로필이 저장되었습니다.');
            redirect('settings.php');
        }
    // ---- 비밀번호 변경 ----
    } elseif ($form === 'password') {
        // $me 에는 password_hash 가 없으므로 해시를 포함한 행을 따로 조회
        $row = find_user_by_email($me['email']);
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        if (!password_verify($current, $row['password_hash'])) $errors['current_password'] = '현재 비밀번호가 올바르지 않습니다.';
        if (mb_strlen($new) < 8) $errors['new_password'] = '새 비밀번호는 8자 이상이어야 합니다.';
        elseif ($new !== ($_POST['new_password_confirm'] ?? '')) $errors['new_password_confirm'] = '비밀번호가 일치하지 않습니다.';
        if (!$errors) {
            update_user($meId, ['password_hash' => password_hash($new, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);   // 비밀번호 변경 후 세션 ID 도 새로 발급
            flash('success', '비밀번호가 변경되었습니다.');
            redirect('settings.php');
        }
    // ---- 사진 삭제 ----
    } elseif ($form === 'remove_photo') {
        $type = ($_POST['type'] ?? '') === 'cover' ? 'cover' : 'avatar';
        delete_upload($me[$type]);
        update_user($meId, [$type => null]);
        flash('success', $type === 'cover' ? '커버 사진을 삭제했습니다.' : '프로필 사진을 삭제했습니다.');
        redirect('settings.php');
    }
}

// 템플릿 도우미
//   $v('bio')   : 입력칸 값 — 검증 실패로 다시 그릴 때는 방금 입력한 값, 처음엔 DB 값 (이스케이프 포함)
//   $err('bio') : 필드 아래 빨간 오류 문구
$v = fn (string $k) => e($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[$k]) ? (string) $_POST[$k] : (string) ($me[$k] ?? ''));
$err = fn (string $k) => isset($errors[$k]) ? '<p class="text-xs text-destructive">' . e($errors[$k]) . '</p>' : '';

$title = '설정';
require __DIR__ . '/includes/partials/header.php';
?>
<div class="mx-auto max-w-2xl space-y-6 px-4 py-8">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">설정</h1>
        <p class="text-muted-foreground">프로필 정보와 계정 보안을 관리합니다.</p>
    </div>

    <!-- 사진 -->
    <section class="card gap-6 py-6">
        <div class="card-header">
            <h2 class="card-title">프로필 사진 및 커버</h2>
            <p class="card-description">JPG, PNG, GIF, WEBP 형식을 지원합니다.</p>
        </div>
        <div class="card-content space-y-4">
            <?php foreach (['avatar' => '프로필 사진', 'cover' => '커버 사진'] as $type => $label): ?>
                <div class="flex items-center gap-4">
                    <?php if ($type === 'avatar'): ?>
                        <?= avatar($me, 'size-16', 'text-lg') ?>
                    <?php else: ?>
                        <div class="h-16 w-28 overflow-hidden rounded-md bg-gradient-to-br from-primary/30 to-muted">
                            <?php if ($me['cover']): ?><img src="<?= e(media_url($me['cover'])) ?>" alt="" class="size-full object-cover"><?php endif ?>
                        </div>
                    <?php endif ?>
                    <div class="flex-1 text-sm font-medium"><?= $label ?></div>
                    <form method="post" action="<?= url('actions/profile_photo.php') ?>" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="type" value="<?= $type ?>">
                        <label class="btn btn-outline btn-sm"><?= icon('upload') ?> 업로드
                            <input type="file" name="photo" accept="image/*" class="sr-only" data-auto-submit>
                        </label>
                    </form>
                    <?php if ($me[$type]): ?>
                        <form method="post" data-confirm="<?= $label ?>을 삭제하시겠습니까?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="form" value="remove_photo">
                            <input type="hidden" name="type" value="<?= $type ?>">
                            <button class="btn btn-ghost btn-sm text-destructive hover:text-destructive"><?= icon('trash-2') ?></button>
                        </form>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </div>
    </section>

    <!-- 프로필 정보 -->
    <form method="post" class="card gap-6 py-6">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="profile">
        <div class="card-header">
            <h2 class="card-title">프로필 정보</h2>
            <p class="card-description">다른 사용자에게 표시되는 정보입니다.</p>
        </div>
        <div class="card-content grid gap-4">
            <div class="grid grid-cols-2 gap-3">
                <div class="grid gap-2"><label class="label" for="last_name">성</label><input id="last_name" name="last_name" value="<?= $v('last_name') ?>" class="input"><?= $err('last_name') ?></div>
                <div class="grid gap-2"><label class="label" for="first_name">이름</label><input id="first_name" name="first_name" value="<?= $v('first_name') ?>" class="input"><?= $err('first_name') ?></div>
            </div>
            <div class="grid gap-2">
                <label class="label" for="bio">소개</label>
                <textarea id="bio" name="bio" rows="3" maxlength="255" class="textarea" placeholder="자신을 소개해보세요"><?= $v('bio') ?></textarea>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="grid gap-2"><label class="label" for="workplace">직장</label><input id="workplace" name="workplace" value="<?= $v('workplace') ?>" class="input"></div>
                <div class="grid gap-2"><label class="label" for="education">학교</label><input id="education" name="education" value="<?= $v('education') ?>" class="input"></div>
                <div class="grid gap-2"><label class="label" for="location">거주지</label><input id="location" name="location" value="<?= $v('location') ?>" class="input"></div>
                <div class="grid gap-2"><label class="label" for="birthday">생년월일</label><input id="birthday" type="date" name="birthday" value="<?= $v('birthday') ?>" class="input"><?= $err('birthday') ?></div>
            </div>
            <div class="grid gap-2">
                <label class="label" for="gender">성별</label>
                <select id="gender" name="gender" class="select">
                    <option value="">선택 안 함</option>
                    <?php foreach (['female' => '여성', 'male' => '남성', 'other' => '기타'] as $g => $l): ?>
                        <option value="<?= $g ?>" <?= $v('gender') === $g ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>
        <div class="flex justify-end border-t px-6 pt-6"><button class="btn btn-primary">저장</button></div>
    </form>

    <!-- 비밀번호 -->
    <form method="post" class="card gap-6 py-6">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="password">
        <div class="card-header">
            <h2 class="card-title">비밀번호 변경</h2>
            <p class="card-description">로그인 이메일: <?= e($me['email']) ?></p>
        </div>
        <div class="card-content grid gap-4">
            <div class="grid gap-2"><label class="label" for="current_password">현재 비밀번호</label><input id="current_password" type="password" name="current_password" class="input" autocomplete="current-password"><?= $err('current_password') ?></div>
            <div class="grid gap-2"><label class="label" for="new_password">새 비밀번호</label><input id="new_password" type="password" name="new_password" class="input" autocomplete="new-password"><?= $err('new_password') ?></div>
            <div class="grid gap-2"><label class="label" for="new_password_confirm">새 비밀번호 확인</label><input id="new_password_confirm" type="password" name="new_password_confirm" class="input" autocomplete="new-password"><?= $err('new_password_confirm') ?></div>
        </div>
        <div class="flex justify-end border-t px-6 pt-6"><button class="btn btn-primary">비밀번호 변경</button></div>
    </form>
</div>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
