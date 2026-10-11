<?php
/**
 * ============================================================================
 * friends.php — 친구 관계(friendships 테이블) 관련 함수
 * ============================================================================
 *
 * 데이터 모델
 *   두 사람 사이의 관계는 friendships 테이블에 "한 행"으로만 저장된다.
 *     requester_id : 친구 요청을 보낸 사람
 *     addressee_id : 요청을 받은 사람
 *     status       : 'pending'(요청 중) → 수락 시 'accepted'(친구)
 *   거절/취소/친구 끊기는 행을 삭제한다.
 *   그래서 "A와 B가 친구인가?"를 확인할 때는 (A→B) 와 (B→A) 두 방향을 모두 찾아야 한다.
 *
 * 관계 상태 (friend_status 반환값) — 친구 버튼(partials/friend_buttons.php)이 이 값으로 그려진다.
 *   self             : 나 자신
 *   none             : 아무 관계 없음        → [친구 추가]
 *   request_sent     : 내가 요청을 보냄      → [요청 취소]
 *   request_received : 상대가 요청을 보냄    → [확인] [삭제]
 *   friends          : 친구                  → [친구 끊기]
 */
declare(strict_types=1);

/** 두 사람 사이의 friendships 행 (방향 무관, 없으면 null) */
function friendship_between(int $a, int $b): ?array
{
    $row = q(
        'SELECT * FROM friendships
         WHERE (requester_id = ? AND addressee_id = ?) OR (requester_id = ? AND addressee_id = ?)
         LIMIT 1',
        [$a, $b, $b, $a]
    )->fetch();
    return $row ?: null;
}

/**
 * 나($me) 기준 상대($other)와의 관계
 * self | friends | request_sent | request_received | none
 */
function friend_status(int $me, int $other): string
{
    if ($me === $other) {
        return 'self';
    }
    $f = friendship_between($me, $other);
    if (!$f) {
        return 'none';
    }
    if ($f['status'] === 'accepted') {
        return 'friends';
    }
    // pending 인 경우: 요청자가 나면 "보낸 요청", 아니면 "받은 요청"
    return (int) $f['requester_id'] === $me ? 'request_sent' : 'request_received';
}

function are_friends(int $a, int $b): bool
{
    return friend_status($a, $b) === 'friends';
}

/**
 * 친구 id 목록
 * CASE 식으로 "내가 아닌 쪽" id 를 뽑는다. (내가 requester 면 addressee 가 친구, 반대도 마찬가지)
 * 뉴스피드/공개 범위 판단 등에서 한 요청 안에 여러 번 호출되므로 static 캐시를 둔다.
 * @return int[]
 */
function friend_ids(int $userId): array
{
    static $cache = [];
    if (!isset($cache[$userId])) {
        $rows = q(
            "SELECT CASE WHEN requester_id = ? THEN addressee_id ELSE requester_id END AS fid
             FROM friendships WHERE status = 'accepted' AND (requester_id = ? OR addressee_id = ?)",
            [$userId, $userId, $userId]
        )->fetchAll(PDO::FETCH_COLUMN);
        $cache[$userId] = array_map('intval', $rows);
    }
    return $cache[$userId];
}

function friend_count(int $userId): int
{
    return count(friend_ids($userId));
}

/** 친구 목록 (사용자 정보 포함, 이름순). $limit 을 주면 앞에서부터 N명만 */
function get_friends(int $userId, ?int $limit = null): array
{
    $ids = friend_ids($userId);
    if (!$ids) {
        return [];
    }
    $sql = 'SELECT ' . USER_PUBLIC_COLUMNS . ' FROM users WHERE id IN (' . in_placeholders($ids) . ') ORDER BY first_name';
    if ($limit) {
        $sql .= ' LIMIT ' . (int) $limit;
    }
    return q($sql, $ids)->fetchAll();
}

/** 함께 아는 친구 수 = 두 사람의 친구 id 목록의 교집합 크기 */
function mutual_friend_count(int $a, int $b): int
{
    return count(array_intersect(friend_ids($a), friend_ids($b)));
}

/** 내가 받은 친구 요청 (최신순) — 친구 화면 "친구 요청" 탭, 뉴스피드 오른쪽 사이드바 */
function friend_requests_received(int $userId): array
{
    return q(
        "SELECT u.id, u.first_name, u.last_name, u.avatar, f.created_at AS requested_at
         FROM friendships f JOIN users u ON u.id = f.requester_id
         WHERE f.addressee_id = ? AND f.status = 'pending'
         ORDER BY f.created_at DESC",
        [$userId]
    )->fetchAll();
}

/** 내가 보낸 친구 요청 (최신순) — 친구 화면 "보낸 요청" 탭 */
function friend_requests_sent(int $userId): array
{
    return q(
        "SELECT u.id, u.first_name, u.last_name, u.avatar, f.created_at AS requested_at
         FROM friendships f JOIN users u ON u.id = f.addressee_id
         WHERE f.requester_id = ? AND f.status = 'pending'
         ORDER BY f.created_at DESC",
        [$userId]
    )->fetchAll();
}

/** 받은 친구 요청 수 — 상단 네비게이션의 친구 아이콘 빨간 배지 */
function pending_request_count(int $userId): int
{
    return (int) q("SELECT COUNT(*) FROM friendships WHERE addressee_id = ? AND status = 'pending'", [$userId])->fetchColumn();
}

/**
 * 알 수도 있는 사람: 관계가 없는 사용자 중 함께 아는 친구 수 순
 *
 * 1) SQL: 나 자신과, 이미 어떤 관계(친구/요청)든 있는 사람을 제외한 최근 가입자 200명
 * 2) PHP: 각 후보의 "함께 아는 친구 수"를 계산해 내림차순 정렬 후 $limit 명
 * 결과 행에는 'mutual' 키가 추가된다.
 */
function friend_suggestions(int $userId, int $limit = 12): array
{
    $users = q(
        'SELECT ' . USER_PUBLIC_COLUMNS . ' FROM users u
         WHERE u.id <> ?
           AND NOT EXISTS (
             SELECT 1 FROM friendships f
             WHERE (f.requester_id = ? AND f.addressee_id = u.id) OR (f.requester_id = u.id AND f.addressee_id = ?)
           )
         ORDER BY u.created_at DESC LIMIT 200',
        [$userId, $userId, $userId]
    )->fetchAll();

    foreach ($users as &$u) {
        $u['mutual'] = mutual_friend_count($userId, (int) $u['id']);
    }
    unset($u); // 참조(&$u) 해제 — 이후 $u 를 다시 쓸 때 마지막 원소가 덮어써지는 PHP 함정 방지
    usort($users, fn ($a, $b) => $b['mutual'] <=> $a['mutual']);
    return array_slice($users, 0, $limit);
}

/* ---------------------------------------------------------------------
 * 액션 (actions/friend.php 에서 호출). 성공 여부를 bool 로 돌려준다.
 * ------------------------------------------------------------------- */

/** 친구 요청 보내기: 자기 자신 / 없는 사용자 / 이미 관계가 있는 경우는 실패 */
function send_friend_request(int $from, int $to): bool
{
    if ($from === $to || !find_user($to) || friendship_between($from, $to)) {
        return false;
    }
    q('INSERT INTO friendships (requester_id, addressee_id) VALUES (?, ?)', [$from, $to]);
    notify($to, $from, 'friend_request');  // 상대에게 "친구 요청" 알림
    return true;
}

/**
 * 받은 요청 수락: pending → accepted
 * 받은 "친구 요청" 알림은 처리했으므로 지우고, 요청 보낸 사람에게 "수락" 알림을 보낸다.
 */
function accept_friend_request(int $me, int $from): bool
{
    $stmt = q(
        "UPDATE friendships SET status = 'accepted' WHERE requester_id = ? AND addressee_id = ? AND status = 'pending'",
        [$from, $me]
    );
    if ($stmt->rowCount() === 0) {
        return false; // 이미 처리됐거나 요청이 취소된 경우
    }
    delete_notification($me, $from, 'friend_request');
    notify($from, $me, 'friend_accept');
    return true;
}

/** 받은 요청 거절(삭제): 행 삭제 + 알림 삭제. 상대에게는 알리지 않는다. */
function decline_friend_request(int $me, int $from): bool
{
    $ok = q(
        "DELETE FROM friendships WHERE requester_id = ? AND addressee_id = ? AND status = 'pending'",
        [$from, $me]
    )->rowCount() > 0;
    delete_notification($me, $from, 'friend_request');
    return $ok;
}

/** 보낸 요청 취소: 행 삭제 + 상대에게 갔던 "친구 요청" 알림도 회수 */
function cancel_friend_request(int $me, int $to): bool
{
    $ok = q(
        "DELETE FROM friendships WHERE requester_id = ? AND addressee_id = ? AND status = 'pending'",
        [$me, $to]
    )->rowCount() > 0;
    delete_notification($to, $me, 'friend_request');
    return $ok;
}

/** 친구 끊기: 누가 먼저 요청했든 accepted 행을 삭제 */
function unfriend(int $me, int $other): bool
{
    return q(
        "DELETE FROM friendships WHERE status = 'accepted'
         AND ((requester_id = ? AND addressee_id = ?) OR (requester_id = ? AND addressee_id = ?))",
        [$me, $other, $other, $me]
    )->rowCount() > 0;
}
