<?php
/**
 * ============================================================================
 * uploads.php — 파일 업로드(사진/동영상) 검증 및 저장
 * ============================================================================
 *
 * 저장 위치 (프로젝트 루트 기준, DB 에는 이 상대 경로가 저장됨)
 *   uploads/posts/2026/10/<랜덤32자>.jpg     게시물 첨부
 *   uploads/avatars/2026/10/<랜덤32자>.png   프로필 사진
 *   uploads/covers/2026/10/<랜덤32자>.webp   커버 사진
 *
 * 보안 포인트
 *   1. 확장자가 아니라 "실제 파일 내용(MIME)"으로 형식을 판별한다. (evil.php → image/png 로 위장 불가)
 *   2. 허용 목록(config.php upload.image_types / video_types)에 있는 형식만 받는다.
 *   3. 저장 파일명은 랜덤으로 새로 만들고, 확장자도 MIME 에 맞춰 서버가 정한다.
 *   4. uploads/.htaccess 와 router.php 가 uploads 폴더에서 PHP 실행을 막는다.
 */
declare(strict_types=1);

/**
 * $_FILES['x'] (multiple) → 파일 배열 목록
 *
 * PHP 는 <input name="media[]" multiple> 을 이렇게 "필드별"로 묶어서 준다.
 *   ['name' => ['a.jpg','b.mp4'], 'tmp_name' => [...], 'error' => [...], ...]
 * 다루기 쉽게 "파일별" 배열로 바꾼다.
 *   [['name'=>'a.jpg','tmp_name'=>...], ['name'=>'b.mp4', ...]]
 * 파일을 고르지 않은 칸(UPLOAD_ERR_NO_FILE)은 건너뛴다.
 */
function normalize_files(?array $files): array
{
    if (!$files || !isset($files['name'])) {
        return [];
    }
    if (!is_array($files['name'])) {
        // 단일 파일 input (name="photo")
        return $files['error'] === UPLOAD_ERR_NO_FILE ? [] : [$files];
    }
    $out = [];
    foreach ($files['name'] as $i => $name) {
        if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = [
            'name'     => $name,
            'type'     => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error'    => $files['error'][$i],
            'size'     => $files['size'][$i],
        ];
    }
    return $out;
}

/**
 * 실제 파일 내용으로 MIME 판별 (fileinfo 확장이 없으면 대체 수단 사용)
 * 브라우저가 보내 준 $file['type'] 은 사용자가 조작할 수 있으므로 믿지 않는다.
 *   1순위 finfo        : 파일 앞부분(매직 넘버)을 읽어 판별
 *   2순위 getimagesize : 이미지일 경우
 *   3순위 확장자        : 동영상인데 fileinfo 확장이 꺼진 환경을 위한 최후 수단
 */
function detect_mime(string $path, string $originalName): string
{
    if (class_exists('finfo')) {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        if ($mime) {
            return $mime;
        }
    }
    $img = @getimagesize($path);
    if ($img && !empty($img['mime'])) {
        return $img['mime'];
    }
    return match (strtolower(pathinfo($originalName, PATHINFO_EXTENSION))) {
        'mp4', 'm4v' => 'video/mp4',
        'webm'       => 'video/webm',
        'mov'        => 'video/quicktime',
        'ogv'        => 'video/ogg',
        default      => 'application/octet-stream',
    };
}

/** PHP 업로드 오류 코드 → 사용자용 한글 메시지 */
function upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => '파일 크기가 서버 허용 한도를 초과했습니다.',
        UPLOAD_ERR_PARTIAL => '파일이 일부만 업로드되었습니다.',
        default => '파일 업로드 중 오류가 발생했습니다.',
    };
}

/**
 * 파일 하나를 검증 후 저장
 * @param string[] $allowedKinds ['image'] 또는 ['image','video'] — 프로필/커버는 이미지만 허용
 * @return array{path:string,type:string} DB 에 저장할 상대 경로와 종류
 * @throws RuntimeException 검증 실패 시 (메시지를 그대로 사용자에게 보여줌)
 */
function store_upload(array $file, string $folder, array $allowedKinds = ['image', 'video']): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(upload_error_message((int) $file['error']));
    }
    // HTTP 업로드로 들어온 임시 파일인지 확인 (서버의 다른 파일 경로를 끼워 넣는 공격 방지)
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('잘못된 업로드 요청입니다.');
    }

    $mime = detect_mime($file['tmp_name'], $file['name']);
    $imageTypes = config('upload.image_types');   // ['image/jpeg' => 'jpg', ...]
    $videoTypes = config('upload.video_types');   // ['video/mp4' => 'mp4', ...]

    // 형식별로 허용 여부, 저장 확장자, 최대 크기 결정
    if (in_array('image', $allowedKinds, true) && isset($imageTypes[$mime])) {
        $kind = 'image';
        $ext = $imageTypes[$mime];
        $max = (int) config('upload.max_image_size');
    } elseif (in_array('video', $allowedKinds, true) && isset($videoTypes[$mime])) {
        $kind = 'video';
        $ext = $videoTypes[$mime];
        $max = (int) config('upload.max_video_size');
    } else {
        throw new RuntimeException(sprintf('"%s"은(는) 지원하지 않는 파일 형식입니다.', $file['name']));
    }

    if ($file['size'] > $max) {
        throw new RuntimeException(sprintf('"%s" 파일이 너무 큽니다. (최대 %dMB)', $file['name'], $max / 1024 / 1024));
    }

    // 년/월 폴더로 나눠 한 폴더에 파일이 너무 많이 쌓이지 않게 한다.
    $relDir = 'uploads/' . $folder . '/' . date('Y/m');
    $absDir = APP_ROOT . '/' . $relDir;
    // mkdir 경쟁 상황(동시에 두 요청이 만드는 경우)을 고려해 실패 후 한 번 더 is_dir 확인
    if (!is_dir($absDir) && !mkdir($absDir, 0775, true) && !is_dir($absDir)) {
        throw new RuntimeException('업로드 폴더를 만들 수 없습니다.');
    }

    // 원래 파일명은 쓰지 않는다 (경로 조작, 덮어쓰기, 한글 파일명 문제 방지)
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $absDir . '/' . $filename)) {
        throw new RuntimeException('파일을 저장하지 못했습니다.');
    }
    return ['path' => $relDir . '/' . $filename, 'type' => $kind];
}

/**
 * 게시물 첨부 파일 일괄 처리. 하나라도 실패하면 이미 저장된 파일은 삭제.
 * ("10개 중 3개만 올라간 게시물"이 생기지 않도록 전부 성공 또는 전부 실패)
 * @return array{0:array,1:?string} [media, error] — error 가 null 이면 성공
 */
function store_post_media(?array $files): array
{
    $list = normalize_files($files);
    $max = (int) config('upload.max_files');
    if (count($list) > $max) {
        return [[], "사진/동영상은 최대 {$max}개까지 첨부할 수 있습니다."];
    }
    $saved = [];
    try {
        foreach ($list as $file) {
            $saved[] = store_upload($file, 'posts');
        }
    } catch (RuntimeException $e) {
        foreach ($saved as $m) {
            delete_upload($m['path']);
        }
        return [[], $e->getMessage()];
    }
    return [$saved, null];
}

/**
 * 업로드 파일 삭제 (게시물 삭제, 프로필 사진 교체 시)
 * uploads/ 로 시작하고 '..' 이 없는 경로만 지워, 다른 폴더의 파일이 지워지는 일을 막는다.
 */
function delete_upload(?string $relPath): void
{
    if (!$relPath || !str_starts_with($relPath, 'uploads/') || str_contains($relPath, '..')) {
        return;
    }
    $abs = APP_ROOT . '/' . $relPath;
    if (is_file($abs)) {
        @unlink($abs);
    }
}
