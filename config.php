<?php
/**
 * ============================================================================
 * config.php — 애플리케이션 기본 설정
 * ============================================================================
 *
 * 이 파일은 배열을 return 하고, includes/bootstrap.php 가 읽어 들인다.
 * 코드에서는 config('db.host'), config('upload.max_files') 처럼 점(.) 표기로 꺼내 쓴다.
 *
 * 로컬 개발 환경(setup.bat)을 쓰면 config.local.php 가 자동 생성되어 DB 항목을 덮어쓴다.
 * 그래서 이 파일은 직접 고치지 않아도 되며, 다른 서버에 배포할 때만 DB 접속 정보를 수정하면 된다.
 */
return [
    // 로고/브라우저 탭 제목에 표시되는 서비스 이름
    'app_name' => 'facebook',

    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'fb_clone',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    // null 이면 자동 감지 (예: XAMPP htdocs/fb-clone → "/fb-clone")
    'base_url' => null,

    // 시간 표시(time_ago) 기준 시간대
    'timezone' => 'Asia/Seoul',

    // 업로드 정책 — includes/uploads.php 가 사용
    //   image_types / video_types : 허용할 MIME 형식 => 저장할 확장자 (여기 없는 형식은 거부)
    'upload' => [
        'max_files'      => 10,                // 게시물 1개당 첨부 최대 개수
        'max_image_size' => 10 * 1024 * 1024,  // 10MB
        'max_video_size' => 100 * 1024 * 1024, // 100MB (php.ini upload_max_filesize / post_max_size 도 함께 조정)
        'image_types'    => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'],
        'video_types'    => ['video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov', 'video/ogg' => 'ogv'],
    ],

    // 뉴스피드/프로필에서 한 번에 불러오는 게시물 수 ("더 보기" 1회 분량)
    'per_page' => 10,
];
