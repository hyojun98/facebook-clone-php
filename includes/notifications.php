<?php
/**
 * ============================================================================
 * notifications.php — 알림(notifications 테이블) 관련 함수
 * ============================================================================
 *
 * 알림 1건 = "actor(행동한 사람)가 user(받는 사람)에게 type(무엇을) 했다"
 *
 *   type            발생 시점                              연결 화면
 *   ---------------------------------------------------------------------------
 *   friend_request  친구 요청을 받았을 때                   friends.php?tab=requests
 *   friend_accept   내가 보낸 요청을 상대가 수락했을 때     상대 프로필
 *   like            내 게시물에 좋아요                      post.php?id=게시물
 *   comment         내 게시물에 댓글                        post.php?id=게시물
 *
 * 알림을 만드는 곳: friends.php(요청/수락), posts.php(좋아요/댓글)
 * 알림을 보여주는 곳: 상단 네비게이션 종 아이콘 드롭다운(header.php), notifications.php
 * is_read: 0 이면 읽지 않음(파란 점, 빨간 숫자 배지에 포함)
 */
declare(strict_types=1);

/**
 * 알림 생성
 * @param int      $userId  알림 받는 사람
 * @param int      $actorId 행동한 사람
 * @param string   $type    friend_request | friend_accept | like | comment
 * @param int|null $postId  게시물 관련 알림이면 게시물 id
 */
function notify(int $userId, int $actorId, string $type, ?int $postId = null): void
{
    if ($userId === $actorId) {
        return; // 자기 자신에게는 알림을 보내지 않음 (내 글에 내가 좋아요 등)
    }
    // 같은 알림 중복 방지 (좋아요 토글 반복 등)
    // → 기존 알림을 지우고 새로 넣어, 같은 사람의 같은 행동은 최신 1건만 남는다.
    //   댓글은 여러 번 달 수 있으므로 매번 새 알림을 만든다.
    if (in_array($type, ['like', 'friend_request', 'friend_accept'], true)) {
        delete_notification($userId, $actorId, $type, $postId);
    }
    q(
        'INSERT INTO notifications (user_id, actor_id, type, post_id) VALUES (?, ?, ?, ?)',
        [$userId, $actorId, $type, $postId]
    );
}

/**
 * 알림 삭제 (좋아요 취소, 친구 요청 취소/처리 시 알림 회수)
 * SQL 에서 "post_id = NULL" 은 항상 거짓이므로, NULL 일 때는 IS NULL 로 따로 비교한다.
 */
function delete_notification(int $userId, int $actorId, string $type, ?int $postId = null): void
{
    if ($postId === null) {
        q('DELETE FROM notifications WHERE user_id = ? AND actor_id = ? AND type = ? AND post_id IS NULL', [$userId, $actorId, $type]);
    } else {
        q('DELETE FROM notifications WHERE user_id = ? AND actor_id = ? AND type = ? AND post_id = ?', [$userId, $actorId, $type, $postId]);
    }
}

/**
 * 알림 목록 (최신순)
 * actor 의 이름/프로필 사진과, 게시물 알림이면 게시물 본문(미리보기용)을 함께 가져온다.
 * @param bool $unreadOnly true 면 읽지 않은 알림만 ("읽지 않음" 필터)
 */
function get_notifications(int $userId, int $limit = 50, bool $unreadOnly = false): array
{
    $sql = 'SELECT n.*, u.first_name, u.last_name, u.avatar, p.content AS post_content
            FROM notifications n
            JOIN users u ON u.id = n.actor_id
            LEFT JOIN posts p ON p.id = n.post_id
            WHERE n.user_id = ?' . ($unreadOnly ? ' AND n.is_read = 0' : '') . '
            ORDER BY n.created_at DESC, n.id DESC LIMIT ' . (int) $limit;
    return q($sql, [$userId])->fetchAll();
}

/** 읽지 않은 알림 수 (상단 종 아이콘의 빨간 배지) */
function unread_notification_count(int $userId): int
{
    return (int) q('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$userId])->fetchColumn();
}

/**
 * 알림 1건 읽음 처리 (알림 클릭 시 notifications.php?open=ID 에서 호출)
 * WHERE 에 user_id 를 넣어 남의 알림은 열 수 없게 한다.
 * @return array|null 알림 행 (이동할 링크 계산용)
 */
function mark_notification_read(int $userId, int $id): ?array
{
    $n = q('SELECT * FROM notifications WHERE id = ? AND user_id = ?', [$id, $userId])->fetch();
    if (!$n) {
        return null;
    }
    q('UPDATE notifications SET is_read = 1 WHERE id = ?', [$id]);
    return $n;
}

/** "모두 읽음으로 표시" 버튼 */
function mark_all_notifications_read(int $userId): void
{
    q('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [$userId]);
}

/**
 * 알림 문구, 아이콘, 이동 링크
 *
 * @return array{text:string,icon:string,color:string,link:string}
 *   text  : 화면에 그대로 출력되는 HTML (이름은 e() 로 이스케이프 후 <strong> 으로 감쌈)
 *   icon  : 프로필 사진 우측 하단 작은 원 안의 Lucide 아이콘 이름
 *   color : 그 작은 원의 배경색 클래스
 *   link  : 알림 클릭 시 이동할 URL
 */
function notification_meta(array $n): array
{
    $name = '<strong class="font-semibold">' . e(full_name($n)) . '</strong>';
    // 게시물 알림이면 본문 앞 40자를 따옴표로 보여준다. (여러 줄 → 한 줄로 정리)
    $snippet = '';
    if (!empty($n['post_content'])) {
        $text = mb_strimwidth(trim(preg_replace('/\s+/u', ' ', $n['post_content'])), 0, 40, '…');
        $snippet = ' "' . e($text) . '"';
    }
    return match ($n['type']) {
        'friend_request' => [
            'text' => "{$name}님이 친구 요청을 보냈습니다.",
            'icon' => 'user-plus', 'color' => 'bg-primary',
            'link' => url('friends.php?tab=requests'),
        ],
        'friend_accept' => [
            'text' => "{$name}님이 친구 요청을 수락했습니다.",
            'icon' => 'user-check', 'color' => 'bg-emerald-500',
            'link' => profile_url((int) $n['actor_id']),
        ],
        'like' => [
            'text' => "{$name}님이 회원님의 게시물을 좋아합니다.{$snippet}",
            'icon' => 'thumbs-up', 'color' => 'bg-primary',
            'link' => url('post.php?id=' . (int) $n['post_id']),
        ],
        'comment' => [
            'text' => "{$name}님이 회원님의 게시물에 댓글을 남겼습니다.{$snippet}",
            'icon' => 'message-circle', 'color' => 'bg-emerald-500',
            'link' => url('post.php?id=' . (int) $n['post_id']),
        ],
        default => [
            'text' => "{$name}님의 새 소식이 있습니다.",
            'icon' => 'bell', 'color' => 'bg-muted-foreground',
            'link' => profile_url((int) $n['actor_id']),
        ],
    };
}
