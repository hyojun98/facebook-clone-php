<?php
/**
 * ============================================================================
 * post_edit.php — 게시물 수정 화면 + 저장 처리
 * ============================================================================
 *
 * URL  : /post_edit.php?id=게시물번호 (게시물 "…" 메뉴 → 게시물 수정)
 * GET  : 공개 범위 select + 본문 textarea + 첨부 미리보기(수정 불가)
 * POST : 저장 후 게시물 상세 화면으로 이동
 *
 * 작성자 본인이 아니면 게시물이 존재해도 404 로 응답한다. (남의 게시물 존재 여부를 알리지 않기 위해 403 대신 404)
 */
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();
$meId = (int) $me['id'];

// GET 은 ?id=, POST 는 hidden 필드 post_id 로 게시물 번호를 받는다.
$post = find_post((int) ($_GET['id'] ?? $_POST['post_id'] ?? 0), $meId);
if (!$post || (int) $post['user_id'] !== $meId) {
    abort(404);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $content = trim((string) ($_POST['content'] ?? ''));
    $privacy = in_array($_POST['privacy'] ?? '', ['public', 'friends', 'only_me'], true) ? $_POST['privacy'] : 'friends';
    // 사진만 있는 게시물은 본문을 비워도 되지만, 글만 있는 게시물은 본문이 필요하다.
    if ($content === '' && !$post['media']) {
        flash('error', '내용을 입력하세요.');
        redirect('post_edit.php?id=' . (int) $post['id']);
    }
    update_post((int) $post['id'], $meId, mb_substr($content, 0, 5000), $privacy);
    flash('success', '게시물이 수정되었습니다.');
    redirect('post.php?id=' . (int) $post['id']);
}

$title = '게시물 수정';
require __DIR__ . '/includes/partials/header.php';
?>
<div class="mx-auto max-w-lg px-4 py-8">
    <form method="post" class="card gap-4 py-6">
        <?= csrf_field() ?>
        <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
        <div class="card-header">
            <h1 class="card-title text-xl">게시물 수정</h1>
            <p class="card-description">내용과 공개 범위를 변경할 수 있습니다.</p>
        </div>
        <div class="card-content grid gap-4">
            <div class="grid gap-2">
                <label for="privacy" class="label">공개 범위</label>
                <select id="privacy" name="privacy" class="select">
                    <?php foreach (['public' => '🌏 전체 공개', 'friends' => '👥 친구만', 'only_me' => '🔒 나만 보기'] as $v => $l): ?>
                        <option value="<?= $v ?>" <?= $post['privacy'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="grid gap-2">
                <label for="content" class="label">내용</label>
                <textarea id="content" name="content" rows="6" maxlength="5000" class="textarea"><?= e($post['content']) ?></textarea>
            </div>
            <?php if ($post['media']): ?>
                <div class="grid grid-cols-4 gap-1">
                    <?php foreach ($post['media'] as $m): ?>
                        <div class="aspect-square overflow-hidden rounded-md bg-muted">
                            <?php if ($m['media_type'] === 'video'): ?>
                                <video src="<?= e(media_url($m['file_path'])) ?>#t=0.1" muted class="size-full object-cover"></video>
                            <?php else: ?>
                                <img src="<?= e(media_url($m['file_path'])) ?>" alt="" class="size-full object-cover">
                            <?php endif ?>
                        </div>
                    <?php endforeach ?>
                </div>
                <p class="text-xs text-muted-foreground">첨부된 사진/동영상은 수정할 수 없습니다.</p>
            <?php endif ?>
            <div class="flex justify-end gap-2">
                <a href="<?= url('post.php?id=' . (int) $post['id']) ?>" class="btn btn-outline">취소</a>
                <button class="btn btn-primary">저장</button>
            </div>
        </div>
    </form>
</div>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
