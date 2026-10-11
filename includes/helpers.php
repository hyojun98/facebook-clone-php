<?php
/**
 * ============================================================================
 * helpers.php — 애플리케이션 전역에서 쓰는 공통 함수 모음
 * ============================================================================
 *
 * 섹션 구성
 *   1. 설정 / DB        : config(), db(), q(), in_placeholders()
 *   2. URL / 응답       : base_url(), url(), redirect(), redirect_back(), json_response(), abort(), require_post()
 *   3. 보안             : e() (HTML 이스케이프), csrf_token(), csrf_field(), verify_csrf()
 *   4. 인증             : current_user(), require_login(), require_guest(), login_user()
 *   5. 플래시 / 폼 유지 : flash(), pull_flashes(), with_old(), old()
 *   6. 표시용           : full_name(), initials(), avatar(), time_ago(), format_text(), icon(), partial() ...
 *
 * 이 파일의 함수는 bootstrap.php 에서 가장 먼저 로드되므로, 다른 includes/*.php 와
 * 모든 화면 파일에서 자유롭게 사용할 수 있다.
 */
declare(strict_types=1);

/* ---------------------------------------------------------------------
 * 1. 설정 / DB
 * ------------------------------------------------------------------- */

/**
 * 점(.) 표기법으로 설정값 읽기
 *   config('db.host')            → '127.0.0.1'
 *   config('upload.max_files')   → 10
 *   config('없는.키', '기본값')   → '기본값'
 */
function config(string $key, $default = null)
{
    $value = $GLOBALS['config'];
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

/**
 * PDO 연결 (요청당 1번만 연결하는 싱글턴)
 *
 * - ERRMODE_EXCEPTION    : SQL 오류 시 예외를 던져 조용히 실패하지 않게 한다.
 * - FETCH_ASSOC          : 결과를 ['컬럼명' => 값] 연관 배열로 받는다.
 * - EMULATE_PREPARES=false: MySQL 서버 측 Prepared Statement 를 사용한다.
 *   (이 때문에 같은 이름의 :param 을 두 번 쓸 수 없어서, 이 프로젝트는 모두 ? 위치 바인딩을 쓴다.)
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config('db');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $c['host'], $c['port'], $c['name'], $c['charset']);
        try {
            $pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // 접속 정보(비밀번호 등)가 화면에 노출되지 않도록 예외 메시지는 출력하지 않는다.
            http_response_code(500);
            exit('데이터베이스에 연결할 수 없습니다. config.php 설정과 database/schema.sql 실행 여부를 확인하세요.');
        }
    }
    return $pdo;
}

/**
 * Prepared Statement 로 쿼리 실행 후 PDOStatement 반환
 * 사용자 입력은 반드시 $params 로 넘겨 SQL Injection 을 막는다.
 *   q('SELECT * FROM users WHERE id = ?', [$id])->fetch();
 */
function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * IN 절용 플레이스홀더 문자열 생성
 *   in_placeholders([3, 5, 9]) → '?,?,?'
 * 빈 배열이면 '?' 하나를 돌려주지만, 호출하는 쪽에서 빈 배열을 먼저 걸러야 한다.
 */
function in_placeholders(array $values): string
{
    return implode(',', array_fill(0, max(count($values), 1), '?'));
}

/* ---------------------------------------------------------------------
 * 2. URL / 응답
 * ------------------------------------------------------------------- */

/**
 * 앱의 기준 경로(URL prefix)
 *
 * - PHP 내장 서버(php -S)로 프로젝트 루트를 열면 ''  → /index.php
 * - XAMPP htdocs/fb-clone 에 두면 '/fb-clone'       → /fb-clone/index.php
 * DOCUMENT_ROOT 와 프로젝트 폴더를 비교해 자동으로 계산하며, config.php 의 base_url 로 고정할 수도 있다.
 */
function base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $configured = config('base_url');
    if ($configured !== null) {
        return $base = rtrim($configured, '/');
    }
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $appRoot = realpath(APP_ROOT) ?: APP_ROOT;
    $rel = '';
    if ($docRoot !== '' && stripos($appRoot, $docRoot) === 0) {
        // 예) D:\xampp\htdocs\fb-clone - D:\xampp\htdocs = \fb-clone → /fb-clone
        $rel = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));
    }
    return $base = rtrim($rel, '/');
}

/** 앱 내부 링크 생성: url('profile.php?id=3') → /profile.php?id=3 (하위 폴더 배포 시 prefix 자동 포함) */
function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

/**
 * 리다이렉트 후 즉시 종료
 * 반환형 never(PHP 8.1+): 이 함수 뒤의 코드는 절대 실행되지 않음을 명시한다.
 */
function redirect(string $path): never
{
    $target = preg_match('#^https?://#', $path) ? $path : url($path);
    header('Location: ' . $target);
    exit;
}

/**
 * 직전 페이지(Referer)로 돌아가기
 * 다른 사이트로 튕겨 나가는 Open Redirect 를 막기 위해, Referer 의 호스트가 현재 호스트와 같을 때만 사용한다.
 */
function redirect_back(string $fallback = 'index.php'): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($ref !== '' && parse_url($ref, PHP_URL_HOST) === explode(':', $host)[0]) {
        header('Location: ' . $ref);
        exit;
    }
    redirect($fallback);
}

/**
 * fetch() 로 보낸 AJAX 요청인지 판별
 * assets/app.js 는 X-Requested-With 와 Accept: application/json 헤더를 함께 보낸다.
 * AJAX 면 JSON 으로, 일반 폼 전송이면 리다이렉트로 응답하는 "점진적 향상" 처리에 사용한다.
 */
function is_ajax(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

/** JSON 응답 후 종료 (한글이 \uXXXX 로 깨지지 않게 JSON_UNESCAPED_UNICODE) */
function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 오류 응답 (404, 403, 419 ...)
 * AJAX 요청이면 {"error": "..."} JSON, 일반 요청이면 partials/error.php 오류 화면을 보여준다.
 */
function abort(int $status, string $message = ''): never
{
    http_response_code($status);
    if (is_ajax()) {
        json_response(['error' => $message ?: 'Error'], $status);
    }
    // 아래 변수($status, $title, $message)는 error.php 템플릿에서 사용된다.
    $title = $status === 404 ? '페이지를 찾을 수 없습니다' : '오류가 발생했습니다';
    $message = $message ?: ($status === 404 ? '요청하신 페이지가 존재하지 않거나 볼 수 있는 권한이 없습니다.' : '');
    require APP_ROOT . '/includes/partials/error.php';
    exit;
}

/**
 * actions/*.php 의 첫 줄에서 호출: POST 요청 + CSRF 토큰이 맞는지 확인
 */
function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        abort(405, '허용되지 않은 요청입니다.');
    }
    // post_max_size 초과 시 $_POST/$_FILES 가 통째로 비어서 도착함
    // → CSRF 토큰도 사라지므로, "세션 만료" 대신 "용량 초과"라는 정확한 메시지를 보여준다.
    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        if (is_ajax()) {
            json_response(['error' => '업로드 용량이 서버 허용 한도(post_max_size)를 초과했습니다.'], 413);
        }
        flash('error', '업로드 용량이 서버 허용 한도(post_max_size)를 초과했습니다.');
        redirect_back();
    }
    verify_csrf();
}

/* ---------------------------------------------------------------------
 * 3. 보안
 * ------------------------------------------------------------------- */

/**
 * HTML 출력용 이스케이프 (XSS 방지)
 * 사용자가 입력한 값을 화면에 찍을 때는 항상 <?= e($값) ?> 형태로 출력한다.
 *   '<script>' → '&lt;script&gt;'
 */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * CSRF 토큰 (세션당 1개, 64자리 랜덤 hex)
 * - 폼: csrf_field() 로 hidden input 삽입
 * - AJAX: 폼 안의 hidden input 이 FormData 로 함께 전송된다.
 *   (header.php 의 <meta name="csrf-token"> 에도 넣어 두었다.)
 */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

/** 폼 안에 넣는 숨김 필드: <input type="hidden" name="_csrf" value="..."> */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/**
 * 요청에 담긴 CSRF 토큰 검증. 불일치 시 419 응답.
 * hash_equals: 문자열 비교 시간을 일정하게 해 타이밍 공격을 막는다.
 */
function verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        abort(419, '세션이 만료되었습니다. 페이지를 새로고침 후 다시 시도하세요.');
    }
}

/* ---------------------------------------------------------------------
 * 4. 인증
 * ------------------------------------------------------------------- */

/**
 * 로그인한 사용자 정보 (비로그인이면 null)
 * 세션에는 user_id 만 저장하고, 사용자 정보는 요청마다 DB 에서 1번 읽어 static 변수에 캐시한다.
 * (static 초기값 false = "아직 조회 안 함", null = "조회했지만 비로그인")
 */
function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = isset($_SESSION['user_id']) ? find_user((int) $_SESSION['user_id']) : null;
        if ($user === null) {
            // 탈퇴 등으로 DB 에 사용자가 없으면 세션도 정리
            unset($_SESSION['user_id']);
        }
    }
    return $user;
}

/** 로그인 사용자 id (비로그인 0) */
function current_user_id(): int
{
    return (int) (current_user()['id'] ?? 0);
}

/**
 * 로그인 필수 페이지의 첫 줄에서 호출. 비로그인이면 로그인 화면으로 보낸다.
 * @return array 로그인 사용자 행 (페이지에서 $me 로 받아 사용)
 */
function require_login(): array
{
    $user = current_user();
    if (!$user) {
        if (is_ajax()) {
            json_response(['error' => '로그인이 필요합니다.'], 401);
        }
        flash('info', '로그인이 필요합니다.');
        redirect('login.php');
    }
    return $user;
}

/** 로그인/회원가입 화면처럼 "비로그인 전용" 페이지에서 호출. 이미 로그인했으면 뉴스피드로. */
function require_guest(): void
{
    if (current_user()) {
        redirect('index.php');
    }
}

/**
 * 로그인 처리
 * session_regenerate_id(true): 로그인 직전 세션 ID 를 새로 발급해 "세션 고정(Session Fixation)" 공격을 막는다.
 */
function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

/* ---------------------------------------------------------------------
 * 5. 플래시 메시지 / 폼 입력 유지
 * ------------------------------------------------------------------- */

/**
 * 다음 화면에 1회만 보여줄 메시지 저장 (PRG 패턴: POST → Redirect → GET 후 토스트로 표시)
 * @param string $type success | error | info
 */
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/** 저장된 플래시 메시지를 꺼내고 세션에서 삭제 (header.php 의 토스트 영역에서 호출) */
function pull_flashes(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}

/**
 * 검증 실패 시 사용자가 입력한 값을 세션에 보관 → 폼을 다시 그릴 때 old() 로 채운다.
 * 비밀번호와 CSRF 토큰은 보관하지 않는다.
 */
function with_old(array $input): void
{
    unset($input['password'], $input['password_confirm'], $input['_csrf']);
    $_SESSION['_old'] = $input;
}

/** 직전에 입력했던 값 (with_old 로 저장된 값을 한 번 읽고 세션에서 제거) */
function old(string $key, string $default = ''): string
{
    static $old = null;
    if ($old === null) {
        $old = $_SESSION['_old'] ?? [];
        unset($_SESSION['_old']);
    }
    return (string) ($old[$key] ?? $default);
}

/* ---------------------------------------------------------------------
 * 6. 표시용
 * ------------------------------------------------------------------- */

/**
 * 표시용 이름
 *   한글 이름: 성 + 이름 붙여서   (last=김, first=민준 → "김민준")
 *   그 외   : First Last         (first=John, last=Doe → "John Doe")
 * 사용자 행뿐 아니라 게시물/댓글/알림 행(first_name, last_name 컬럼 포함)에도 그대로 쓸 수 있다.
 */
function full_name(array $u): string
{
    $first = trim((string) ($u['first_name'] ?? ''));
    $last  = trim((string) ($u['last_name'] ?? ''));
    // 한글 이름은 "성이름", 그 외는 "First Last"
    if (preg_match('/\p{Hangul}/u', $first . $last)) {
        return $last . $first;
    }
    return trim($first . ' ' . $last);
}

/**
 * 프로필 사진이 없을 때 원 안에 표시할 글자
 *   한글: 이름 앞 2글자 ("민준"), 영문: 이니셜 2글자 ("JD")
 */
function initials(array $u): string
{
    $name = full_name($u);
    if (preg_match('/\p{Hangul}/u', $name)) {
        return mb_substr((string) ($u['first_name'] ?? $name), 0, 2);
    }
    $first = mb_substr((string) ($u['first_name'] ?? ''), 0, 1);
    $last  = mb_substr((string) ($u['last_name'] ?? ''), 0, 1);
    return mb_strtoupper($first . $last);
}

/** DB 에 저장된 상대 경로(uploads/...)를 브라우저 URL 로 변환 */
function media_url(?string $path): string
{
    return $path ? url($path) : '';
}

/** 프로필 링크: profile_url(3, 'friends') → /profile.php?id=3&tab=friends */
function profile_url(int $userId, string $tab = ''): string
{
    return url('profile.php?id=' . $userId . ($tab ? '&tab=' . urlencode($tab) : ''));
}

/**
 * 상대 시간 표기 (페이스북 스타일)
 *   1분 미만 → "방금 전", 1시간 미만 → "N분", 하루 미만 → "N시간", 일주일 미만 → "N일"
 *   그 이후 → "3월 5일" (올해) / "2024년 3월 5일" (작년 이전)
 */
function time_ago(string $datetime): string
{
    $ts = strtotime($datetime);
    $diff = time() - $ts;
    if ($diff < 60) return '방금 전';
    if ($diff < 3600) return floor($diff / 60) . '분';
    if ($diff < 86400) return floor($diff / 3600) . '시간';
    if ($diff < 604800) return floor($diff / 86400) . '일';
    if (date('Y', $ts) === date('Y')) return date('n월 j일', $ts);
    return date('Y년 n월 j일', $ts);
}

/**
 * 게시물/댓글 본문 → 안전한 HTML
 * 1) e() 로 먼저 전부 이스케이프 (HTML 태그 무력화)
 * 2) http(s):// 로 시작하는 문자열만 <a> 링크로 변환
 * 3) 줄바꿈을 <br> 로 변환
 * 순서가 중요하다: 이스케이프를 먼저 해야 사용자 입력에 섞인 태그가 실행되지 않는다.
 */
function format_text(?string $text): string
{
    $html = e($text);
    $html = preg_replace(
        '~(https?://[^\s<]+)~u',
        '<a href="$1" target="_blank" rel="noopener noreferrer nofollow" class="text-primary hover:underline break-all">$1</a>',
        $html
    );
    return nl2br($html);
}

/**
 * shadcn Avatar 스타일: 이미지가 없으면 이니셜 폴백
 * (users / posts / comments / notifications 행 모두 지원)
 *
 * @param array  $u     사용자 정보가 담긴 행 (avatar, first_name, last_name 필요)
 * @param string $size  Tailwind 크기 클래스 (size-10 = 40px)
 * @param string $text  이니셜 글자 크기 클래스
 * @param string $extra 추가 클래스 (ring-4 등)
 */
function avatar(array $u, string $size = 'size-10', string $text = 'text-sm', string $extra = ''): string
{
    $name = e(full_name($u));
    if (!empty($u['avatar'])) {
        return '<img src="' . e(media_url($u['avatar'])) . '" alt="' . $name . '" class="' . $size . ' ' . $extra . ' shrink-0 rounded-full object-cover bg-muted">';
    }
    // 사용자마다 항상 같은 배경색이 나오도록 "사용자 id % 색상 수" 로 고른다.
    // 행 종류마다 사용자 id 컬럼이 다르다: 알림=actor_id, 게시물/댓글=user_id, 사용자=id
    $colors = ['bg-blue-500', 'bg-emerald-500', 'bg-violet-500', 'bg-amber-500', 'bg-rose-500', 'bg-cyan-600', 'bg-indigo-500', 'bg-teal-500'];
    $uid = (int) ($u['actor_id'] ?? $u['user_id'] ?? $u['id'] ?? 0);
    $color = $colors[$uid % count($colors)];
    return '<span class="' . $size . ' ' . $text . ' ' . $color . ' ' . $extra . ' shrink-0 inline-flex items-center justify-center rounded-full font-semibold text-white select-none" role="img" aria-label="' . $name . '">' . e(initials($u)) . '</span>';
}

/**
 * Lucide 아이콘 자리 표시자
 * <i data-lucide="house"> 를 출력하면 footer 에서 로드한 lucide.js 가 실제 SVG 로 바꿔 준다.
 * 아이콘 이름: https://lucide.dev/icons
 */
function icon(string $name, string $class = 'size-5'): string
{
    return '<i data-lucide="' . e($name) . '" class="' . e($class) . '"></i>';
}

/**
 * 게시물 공개 범위 → [아이콘 이름, 한글 라벨]
 *   public → 전체 공개, friends → 친구, only_me → 나만 보기
 */
function privacy_label(string $privacy): array
{
    return match ($privacy) {
        'public'  => ['earth', '전체 공개'],
        'only_me' => ['lock', '나만 보기'],
        default   => ['users', '친구'],
    };
}

/**
 * 조각 템플릿(includes/partials/*.php) 출력
 *   partial('post_card', ['post' => $post]);
 * extract() 로 배열 키를 변수로 풀어서 템플릿 안에서 $post 처럼 쓸 수 있게 한다.
 * 함수 안에서 require 하므로 템플릿은 호출한 페이지의 변수와 섞이지 않는다.
 */
function partial(string $name, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    require APP_ROOT . '/includes/partials/' . $name . '.php';
}

/** partial() 결과를 문자열로 반환 (AJAX 응답에 HTML 조각을 담을 때 사용) */
function render_partial(string $name, array $vars = []): string
{
    ob_start();
    partial($name, $vars);
    return (string) ob_get_clean();
}
