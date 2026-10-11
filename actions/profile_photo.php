<?php
/**
 * ============================================================================
 * actions/profile_photo.php — 프로필 사진 / 커버 사진 변경 (POST, multipart)
 * ============================================================================
 *
 * 요청 : profile.php 의 카메라 버튼, settings.php 의 [업로드] 버튼
 *        (파일을 고르면 app.js 의 data-auto-submit 이 폼을 바로 전송)
 *   _csrf
 *   type   avatar(프로필 사진) | cover(커버 사진)
 *   photo  이미지 파일 1개 (동영상 불가)
 *
 * 새 파일을 저장한 뒤 이전 파일은 디스크에서 지운다.
 * (사진 삭제는 settings.php 의 form=remove_photo 에서 처리)
 */
require __DIR__ . '/../includes/bootstrap.php';
$me = require_login();
require_post();

$type = ($_POST['type'] ?? '') === 'cover' ? 'cover' : 'avatar';
$files = normalize_files($_FILES['photo'] ?? null);

if (!$files) {
    flash('error', '사진을 선택하세요.');
    redirect_back();
}

try {
    // 폴더: uploads/avatars 또는 uploads/covers, 허용 형식: 이미지만
    $saved = store_upload($files[0], $type === 'cover' ? 'covers' : 'avatars', ['image']);
} catch (RuntimeException $e) {
    flash('error', $e->getMessage());
    redirect_back();
}

delete_upload($me[$type]);  // 이전 사진 파일 삭제 (없으면 아무것도 안 함)
update_user((int) $me['id'], [$type => $saved['path']]);

flash('success', $type === 'cover' ? '커버 사진이 변경되었습니다.' : '프로필 사진이 변경되었습니다.');
redirect_back();
