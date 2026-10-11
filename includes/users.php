<?php
/**
 * ============================================================================
 * users.php — 회원(users 테이블) 관련 DB 함수
 * ============================================================================
 *
 * 사용처
 *   - login.php / register.php : find_user_by_email(), create_user()
 *   - settings.php             : update_user()
 *   - profile.php              : find_user(), user_media()
 *   - search.php               : search_users()
 *   - helpers.php current_user(): find_user()
 */
declare(strict_types=1);

/**
 * 화면에 내보내도 되는 컬럼 목록
 * password_hash 는 여기 넣지 않아, 실수로 비밀번호 해시가 화면/JSON 으로 나가는 일을 막는다.
 * (비밀번호 확인이 필요한 곳은 find_user_by_email() 처럼 SELECT * 를 쓰는 함수를 따로 사용)
 */
const USER_PUBLIC_COLUMNS = 'id, first_name, last_name, email, gender, birthday, bio, location, workplace, education, avatar, cover, created_at';

/** id 로 사용자 1명 조회 (없으면 null) */
function find_user(int $id): ?array
{
    $row = q('SELECT ' . USER_PUBLIC_COLUMNS . ' FROM users WHERE id = ?', [$id])->fetch();
    return $row ?: null;
}

/**
 * 이메일로 사용자 조회 — password_hash 포함 (로그인 검증, 비밀번호 변경 전용)
 * 이메일은 저장/조회 모두 소문자로 통일해 대소문자 차이로 중복 가입되는 것을 막는다.
 */
function find_user_by_email(string $email): ?array
{
    $row = q('SELECT * FROM users WHERE email = ?', [mb_strtolower(trim($email))])->fetch();
    return $row ?: null;
}

/**
 * 회원가입 (검증은 register.php 에서 끝난 상태로 호출)
 * 비밀번호는 password_hash() 로 bcrypt 해시하여 저장한다. (원문 비밀번호는 DB 에 남지 않음)
 * @return int 새 사용자 id
 */
function create_user(array $data): int
{
    q(
        'INSERT INTO users (first_name, last_name, email, password_hash, gender, birthday) VALUES (?, ?, ?, ?, ?, ?)',
        [
            $data['first_name'],
            $data['last_name'],
            mb_strtolower(trim($data['email'])),
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['gender'] ?: null,
            $data['birthday'] ?: null,
        ]
    );
    return (int) db()->lastInsertId();
}

/**
 * 사용자 정보 일부 수정
 * $fields 의 키가 화이트리스트($allowed)에 있을 때만 UPDATE 에 포함한다.
 * → 폼에 임의 필드(email, id 등)를 끼워 넣어도 수정되지 않는다.
 * 빈 문자열은 NULL 로 저장 (예: 소개를 지우면 bio = NULL)
 */
function update_user(int $id, array $fields): void
{
    $allowed = ['first_name', 'last_name', 'bio', 'location', 'workplace', 'education', 'avatar', 'cover', 'birthday', 'gender', 'password_hash'];
    $sets = [];
    $params = [];
    foreach ($fields as $col => $val) {
        if (in_array($col, $allowed, true)) {
            // 컬럼명은 화이트리스트를 통과한 값만 쓰므로 SQL 에 직접 넣어도 안전하다.
            $sets[] = "`$col` = ?";
            $params[] = ($val === '' ? null : $val);
        }
    }
    if (!$sets) {
        return;
    }
    $params[] = $id;
    q('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
}

/**
 * 사람 검색 (search.php)
 * "김민준"(성+이름), "민준 김"(이름 성), 이메일 일부로 검색할 수 있다.
 * LIKE 의 특수문자(%, _, \)는 이스케이프해서 사용자가 입력한 그대로 검색되게 한다.
 */
function search_users(string $term, int $limit = 30): array
{
    $term = trim($term);
    if ($term === '') {
        return [];
    }
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
    return q(
        'SELECT ' . USER_PUBLIC_COLUMNS . ' FROM users
         WHERE CONCAT(last_name, first_name) LIKE ? OR CONCAT(first_name, \' \', last_name) LIKE ? OR email LIKE ?
         ORDER BY first_name LIMIT ' . (int) $limit,
        [$like, $like, $like]
    )->fetchAll();
}

/**
 * 프로필에 올린 사진/동영상 (프로필 화면의 "사진" 카드, "사진/동영상" 탭)
 * $viewerId 가 볼 수 있는 공개 범위의 게시물에 첨부된 미디어만 돌려준다.
 * (예: 친구가 아닌 사람에게는 '친구만' 게시물의 사진이 보이지 않음)
 */
function user_media(int $ownerId, int $viewerId, int $limit = 60): array
{
    [$privSql, $privParams] = post_privacy_clause($viewerId, $ownerId);
    return q(
        "SELECT m.*, p.id AS post_id FROM post_media m
         JOIN posts p ON p.id = m.post_id
         WHERE p.user_id = ? AND $privSql
         ORDER BY p.created_at DESC, m.sort_order LIMIT " . (int) $limit,
        array_merge([$ownerId], $privParams)
    )->fetchAll();
}
