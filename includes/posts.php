<?php
/**
 * ============================================================================
 * posts.php — 게시물 / 첨부 미디어 / 좋아요 / 댓글 관련 함수
 * ============================================================================
 *
 * 관련 테이블
 *   posts       : 게시물 본문 + 공개 범위(privacy: public | friends | only_me)
 *   post_media  : 게시물에 첨부된 사진/동영상 (게시물 1 : N 미디어)
 *   post_likes  : 좋아요 (post_id + user_id 복합 기본키 → 한 사람이 한 게시물에 1번만)
 *   comments    : 댓글
 *
 * 조회 흐름
 *   feed_posts() / profile_posts() / find_post()
 *        └─ fetch_posts()      : 공개 범위 조건 + 작성자/좋아요 수/댓글 수를 한 번에 조회
 *              └─ hydrate_posts(): 미디어와 댓글을 게시물 id 목록으로 "한 번에" 가져와 붙임
 *                                 (게시물마다 쿼리를 날리는 N+1 문제 방지)
 *
 * 화면에서는 반환된 $post 배열을 partials/post_card.php 에 넘겨 카드로 그린다.
 */
declare(strict_types=1);

/**
 * 뷰어가 볼 수 있는 게시물 조건 (posts 테이블 별칭은 p)
 *
 * 볼 수 있는 경우
 *   1) 내 게시물                          (only_me 포함 전부)
 *   2) 전체 공개(public) 게시물
 *   3) 친구의 '친구만(friends)' 게시물
 *
 * @return array{0:string,1:array} [SQL 조건문, 바인딩 값 배열] — 쿼리의 WHERE 에 AND 로 붙여 쓴다.
 */
function post_privacy_clause(int $viewerId, ?int $ownerId = null): array
{
    $friendIds = friend_ids($viewerId);
    $sql = "(p.user_id = ? OR p.privacy = 'public'";
    $params = [$viewerId];
    if ($friendIds) {
        $sql .= " OR (p.privacy = 'friends' AND p.user_id IN (" . in_placeholders($friendIds) . '))';
        $params = array_merge($params, $friendIds);
    }
    $sql .= ')';
    return [$sql, $params];
}

/**
 * 공통 게시물 조회 + 부가 데이터(작성자, 좋아요, 댓글, 미디어)
 *
 * @param string $where        추가 조건 (예: 'p.user_id = ?')
 * @param array  $params       $where 의 바인딩 값
 * @param int    $viewerId     보는 사람 (공개 범위, "내가 좋아요 눌렀는지" 판단용)
 * @param int    $commentLimit 게시물마다 붙일 최근 댓글 수 (0 = 전부, 단일 게시물 화면에서 사용)
 *
 * 반환되는 각 게시물 행에 추가되는 키
 *   first_name, last_name, avatar : 작성자 정보
 *   like_count, comment_count     : 집계 수
 *   liked                         : 내가 좋아요를 눌렀는지 (bool)
 *   media                         : 첨부 미디어 배열
 *   comments                      : 댓글 배열 (작성자 정보 포함)
 */
function fetch_posts(string $where, array $params, int $viewerId, int $limit = 10, int $offset = 0, int $commentLimit = 3): array
{
    [$privSql, $privParams] = post_privacy_clause($viewerId);
    // 바인딩 순서 = SQL 안의 ? 순서: [liked 서브쿼리의 뷰어 id] → [$where 값] → [공개 범위 값]
    $sql = "SELECT p.*, u.first_name, u.last_name, u.avatar,
                   (SELECT COUNT(*) FROM post_likes l WHERE l.post_id = p.id) AS like_count,
                   (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count,
                   EXISTS(SELECT 1 FROM post_likes l2 WHERE l2.post_id = p.id AND l2.user_id = ?) AS liked
            FROM posts p
            JOIN users u ON u.id = p.user_id
            WHERE ($where) AND $privSql
            ORDER BY p.created_at DESC, p.id DESC
            LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset;

    $posts = q($sql, array_merge([$viewerId], $params, $privParams))->fetchAll();
    return hydrate_posts($posts, $commentLimit);
}

/**
 * 게시물 목록에 미디어/댓글을 붙인다.
 * 게시물 id 목록으로 IN 쿼리를 2번(미디어 1번, 댓글 1번)만 실행한 뒤 PHP 에서 게시물별로 나눈다.
 */
function hydrate_posts(array $posts, int $commentLimit): array
{
    if (!$posts) {
        return [];
    }
    $ids = array_map(fn ($p) => (int) $p['id'], $posts);
    $ph = in_placeholders($ids);

    // post_id => [미디어, 미디어, ...]
    $media = [];
    foreach (q("SELECT * FROM post_media WHERE post_id IN ($ph) ORDER BY sort_order, id", $ids)->fetchAll() as $m) {
        $media[(int) $m['post_id']][] = $m;
    }

    // post_id => [댓글, 댓글, ...] (오래된 순)
    $comments = [];
    $rows = q(
        "SELECT c.*, u.first_name, u.last_name, u.avatar
         FROM comments c JOIN users u ON u.id = c.user_id
         WHERE c.post_id IN ($ph) ORDER BY c.created_at, c.id",
        $ids
    )->fetchAll();
    foreach ($rows as $c) {
        $comments[(int) $c['post_id']][] = $c;
    }

    foreach ($posts as &$p) {
        $pid = (int) $p['id'];
        $p['media'] = $media[$pid] ?? [];
        $list = $comments[$pid] ?? [];
        // 피드에서는 최근 N개만 (array_slice 음수 offset = 뒤에서부터), 단일 게시물 화면은 전부
        $p['comments'] = $commentLimit > 0 ? array_slice($list, -$commentLimit) : $list;
        // MySQL 이 문자열로 돌려주는 집계값을 정수/불리언으로 정리
        $p['like_count'] = (int) $p['like_count'];
        $p['comment_count'] = (int) $p['comment_count'];
        $p['liked'] = (bool) $p['liked'];
    }
    unset($p);
    return $posts;
}

/**
 * 뉴스피드: 나 + 친구들의 게시물 (공개 범위 적용), 페이지 단위
 * $page 는 1부터. 한 페이지 개수는 config.php 의 per_page.
 */
function feed_posts(int $userId, int $page = 1): array
{
    $per = (int) config('per_page', 10);
    $authorIds = array_merge([$userId], friend_ids($userId));
    return fetch_posts(
        'p.user_id IN (' . in_placeholders($authorIds) . ')',
        $authorIds,
        $userId,
        $per,
        ($page - 1) * $per
    );
}

/** 프로필 화면의 게시물 목록: $ownerId 가 쓴 글 중 $viewerId 가 볼 수 있는 것 */
function profile_posts(int $ownerId, int $viewerId, int $page = 1): array
{
    $per = (int) config('per_page', 10);
    return fetch_posts('p.user_id = ?', [$ownerId], $viewerId, $per, ($page - 1) * $per);
}

/**
 * 게시물 1개 (권한 확인 포함). 볼 권한이 없거나 없는 게시물이면 null → 화면에서 404 처리.
 * $commentLimit 0 = 댓글 전부 (post.php)
 */
function find_post(int $postId, int $viewerId, int $commentLimit = 0): ?array
{
    $posts = fetch_posts('p.id = ?', [$postId], $viewerId, 1, 0, $commentLimit);
    return $posts[0] ?? null;
}

/** 권한 확인 없이 원본 행 (좋아요/댓글/삭제 처리에서 작성자 확인용) */
function find_post_raw(int $postId): ?array
{
    $row = q('SELECT * FROM posts WHERE id = ?', [$postId])->fetch();
    return $row ?: null;
}

/** 단건 권한 확인 (post_privacy_clause 와 같은 규칙을 PHP 로 구현) */
function can_view_post(int $viewerId, array $post): bool
{
    $owner = (int) $post['user_id'];
    return $owner === $viewerId
        || $post['privacy'] === 'public'
        || ($post['privacy'] === 'friends' && are_friends($viewerId, $owner));
}

/* ---------------------------------------------------------------------
 * 작성 / 수정 / 삭제
 * ------------------------------------------------------------------- */

/**
 * 게시물 작성 (actions/post_create.php)
 * 게시물과 미디어 행을 트랜잭션으로 묶어서, 중간에 실패하면 둘 다 저장되지 않게 한다.
 * (파일 자체는 이미 uploads/ 에 저장된 상태로 들어오며, 실패 시 호출한 쪽에서 파일을 지운다.)
 *
 * @param array $media [['path' => 'uploads/..', 'type' => 'image'|'video'], ...]
 * @return int 새 게시물 id
 */
function create_post(int $userId, string $content, string $privacy, array $media): int
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        q('INSERT INTO posts (user_id, content, privacy) VALUES (?, ?, ?)', [$userId, $content !== '' ? $content : null, $privacy]);
        $postId = (int) $pdo->lastInsertId();
        foreach (array_values($media) as $i => $m) {
            // sort_order: 사용자가 고른 순서대로 표시하기 위한 번호
            q('INSERT INTO post_media (post_id, file_path, media_type, sort_order) VALUES (?, ?, ?, ?)', [$postId, $m['path'], $m['type'], $i]);
        }
        $pdo->commit();
        return $postId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** 게시물 수정 (본문, 공개 범위). WHERE 에 user_id 를 넣어 본인 글만 수정되게 한다. */
function update_post(int $postId, int $userId, string $content, string $privacy): bool
{
    return q(
        'UPDATE posts SET content = ?, privacy = ? WHERE id = ? AND user_id = ?',
        [$content !== '' ? $content : null, $privacy, $postId, $userId]
    )->rowCount() > 0;
}

/**
 * 게시물 삭제 (작성자 본인만)
 * DB 의 ON DELETE CASCADE 로 post_media / post_likes / comments / notifications 행이 함께 지워지고,
 * 디스크에 있는 첨부 파일은 여기서 직접 삭제한다.
 */
function delete_post(int $postId, int $userId): bool
{
    $post = find_post_raw($postId);
    if (!$post || (int) $post['user_id'] !== $userId) {
        return false;
    }
    // 행이 지워지기 전에 파일 경로를 먼저 읽어 둔다.
    $files = q('SELECT file_path FROM post_media WHERE post_id = ?', [$postId])->fetchAll(PDO::FETCH_COLUMN);
    q('DELETE FROM posts WHERE id = ?', [$postId]);
    foreach ($files as $f) {
        delete_upload($f);
    }
    return true;
}

/* ---------------------------------------------------------------------
 * 좋아요 / 댓글
 * ------------------------------------------------------------------- */

/**
 * 좋아요 토글 (actions/like.php)
 * - 이미 눌렀으면 DELETE 가 1행을 지움 → "좋아요 취소" + 작성자에게 갔던 알림 회수
 * - 안 눌렀으면 INSERT → "좋아요" + 작성자에게 알림
 * @return array{liked:bool,count:int} 화면의 버튼 색/숫자를 갱신할 값
 */
function toggle_like(int $userId, int $postId): array
{
    $post = find_post_raw($postId);
    if (!$post || !can_view_post($userId, $post)) {
        abort(404);
    }
    $deleted = q('DELETE FROM post_likes WHERE post_id = ? AND user_id = ?', [$postId, $userId])->rowCount() > 0;
    if ($deleted) {
        delete_notification((int) $post['user_id'], $userId, 'like', $postId);
    } else {
        q('INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)', [$postId, $userId]);
        notify((int) $post['user_id'], $userId, 'like', $postId);
    }
    $count = (int) q('SELECT COUNT(*) FROM post_likes WHERE post_id = ?', [$postId])->fetchColumn();
    return ['liked' => !$deleted, 'count' => $count];
}

/** 좋아요 누른 사람 목록 (post.php 하단 "좋아요 N개" 카드) */
function post_likers(int $postId, int $limit = 50): array
{
    return q(
        'SELECT u.id, u.first_name, u.last_name, u.avatar FROM post_likes l
         JOIN users u ON u.id = l.user_id WHERE l.post_id = ? ORDER BY l.created_at DESC LIMIT ' . (int) $limit,
        [$postId]
    )->fetchAll();
}

/**
 * 댓글 작성 (actions/comment_create.php)
 * 볼 수 없는 게시물에는 댓글을 달 수 없다. 작성 후 게시물 작성자에게 알림.
 * @return array 작성된 댓글 행 (작성자 정보 포함) → partials/comment.php 로 바로 렌더링
 */
function add_comment(int $userId, int $postId, string $content): array
{
    $post = find_post_raw($postId);
    if (!$post || !can_view_post($userId, $post)) {
        abort(404);
    }
    q('INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)', [$postId, $userId, $content]);
    $id = (int) db()->lastInsertId();
    notify((int) $post['user_id'], $userId, 'comment', $postId);

    return q(
        'SELECT c.*, u.first_name, u.last_name, u.avatar FROM comments c JOIN users u ON u.id = c.user_id WHERE c.id = ?',
        [$id]
    )->fetch();
}

/**
 * 댓글 삭제 — 댓글 작성자 또는 게시물 작성자만 가능
 * @return int|null 성공 시 댓글이 달려 있던 게시물 id (댓글 수 재계산용), 권한 없으면 null
 */
function delete_comment(int $userId, int $commentId): ?int
{
    $row = q(
        'SELECT c.id, c.user_id, c.post_id, p.user_id AS post_owner
         FROM comments c JOIN posts p ON p.id = c.post_id WHERE c.id = ?',
        [$commentId]
    )->fetch();
    if (!$row || ($userId !== (int) $row['user_id'] && $userId !== (int) $row['post_owner'])) {
        return null;
    }
    q('DELETE FROM comments WHERE id = ?', [$commentId]);
    return (int) $row['post_id'];
}

function comment_count(int $postId): int
{
    return (int) q('SELECT COUNT(*) FROM comments WHERE post_id = ?', [$postId])->fetchColumn();
}
