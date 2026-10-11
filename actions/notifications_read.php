<?php
/**
 * ============================================================================
 * actions/notifications_read.php — 모든 알림 읽음 처리 (POST)
 * ============================================================================
 *
 * 요청 : 상단 알림 드롭다운 / 알림 화면의 "모두 읽음으로 표시" 버튼
 *   _csrf
 *
 * 개별 알림의 읽음 처리는 알림을 클릭할 때 notifications.php?open=ID 에서 따로 한다.
 */
require __DIR__ . '/../includes/bootstrap.php';
$me = require_login();
require_post();

mark_all_notifications_read((int) $me['id']);

if (is_ajax()) {
    json_response(['ok' => true]);
}
redirect_back('notifications.php');
