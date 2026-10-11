<?php
/**
 * ============================================================================
 * actions/friend.php — 친구 관계 변경 (POST)
 * ============================================================================
 *
 * 요청 : partials/friend_buttons.php 의 버튼들, 뉴스피드 사이드바의 [+] 버튼
 *   _csrf
 *   user_id  상대 사용자 id
 *   op       send     친구 요청 보내기
 *            accept   받은 요청 수락
 *            decline  받은 요청 삭제(거절)
 *            cancel   보낸 요청 취소
 *            unfriend 친구 끊기
 *
 * 처리 후 결과를 플래시 메시지(토스트)로 보여주고 원래 화면으로 돌아간다.
 * 실제 DB 처리와 알림 생성은 includes/friends.php 의 함수가 담당한다.
 */
require __DIR__ . '/../includes/bootstrap.php';
$me = require_login();
require_post();

$meId = (int) $me['id'];
$userId = (int) ($_POST['user_id'] ?? 0);
$other = find_user($userId);
if (!$other) {
    abort(404, '사용자를 찾을 수 없습니다.');
}
$name = full_name($other);

// match 는 선택된 op 의 함수만 실행한다. [성공 여부, 성공 시 메시지]
[$ok, $message] = match ($_POST['op'] ?? '') {
    'send'     => [send_friend_request($meId, $userId),   "{$name}님에게 친구 요청을 보냈습니다."],
    'accept'   => [accept_friend_request($meId, $userId), "{$name}님과 친구가 되었습니다."],
    'decline'  => [decline_friend_request($meId, $userId), '친구 요청을 삭제했습니다.'],
    'cancel'   => [cancel_friend_request($meId, $userId), '친구 요청을 취소했습니다.'],
    'unfriend' => [unfriend($meId, $userId),              "{$name}님과 친구를 끊었습니다."],
    default    => [false, ''],
};

if ($ok) {
    flash('success', $message);
} else {
    // 예: 두 탭에서 같은 요청을 동시에 수락한 경우 등
    flash('error', '요청을 처리할 수 없습니다. 이미 처리되었을 수 있습니다.');
}
redirect_back('friends.php');
