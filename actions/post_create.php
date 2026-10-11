<?php
/**
 * ============================================================================
 * actions/post_create.php — 게시물 작성 처리 (POST, multipart/form-data)
 * ============================================================================
 *
 * 요청 : partials/composer.php 의 "게시물 만들기" 다이얼로그 폼
 *   _csrf     CSRF 토큰
 *   content   본문 (최대 5,000자, 비워도 됨 — 단 첨부가 있어야 함)
 *   privacy   public | friends | only_me
 *   media[]   사진/동영상 파일 (여러 개, 최대 config upload.max_files)
 *
 * 처리 순서
 *   1. 로그인 + POST + CSRF 확인
 *   2. 입력 검증 (본문 길이, 공개 범위 화이트리스트)
 *   3. 첨부 파일 검증·저장 (하나라도 실패하면 전부 취소)
 *   4. DB 저장 (실패하면 저장했던 파일 삭제)
 *   5. 플래시 메시지 남기고 원래 화면으로 돌아감 (PRG 패턴)
 */
require __DIR__ . '/../includes/bootstrap.php';
$me = require_login();
require_post();

$content = trim((string) ($_POST['content'] ?? ''));
// 허용된 값이 아니면 기본값 'friends' (폼을 조작해 이상한 값을 보내도 안전)
$privacy = in_array($_POST['privacy'] ?? '', ['public', 'friends', 'only_me'], true) ? $_POST['privacy'] : 'friends';

if (mb_strlen($content) > 5000) {
    flash('error', '게시물은 5,000자 이내로 작성하세요.');
    redirect_back();
}

// $media: [['path' => 'uploads/posts/...', 'type' => 'image'], ...]
[$media, $error] = store_post_media($_FILES['media'] ?? null);
if ($error) {
    flash('error', $error);
    redirect_back();
}

if ($content === '' && !$media) {
    flash('error', '내용을 입력하거나 사진/동영상을 첨부하세요.');
    redirect_back();
}

try {
    create_post((int) $me['id'], $content, $privacy, $media);
} catch (Throwable $e) {
    // DB 저장 실패 시 디스크에 남은 파일 정리
    foreach ($media as $m) {
        delete_upload($m['path']);
    }
    flash('error', '게시물을 저장하지 못했습니다.');
    redirect_back();
}

flash('success', '게시물이 등록되었습니다.');
redirect_back();
