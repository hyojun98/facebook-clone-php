<?php
/**
 * ============================================================================
 * partials/friend_buttons.php — 관계 상태에 따른 친구 버튼 묶음
 * ============================================================================
 *
 * 사용처: 프로필 헤더, 친구 화면 카드, 검색 결과, 뉴스피드 친구 요청 사이드바
 *
 *   status            버튼                       전송되는 op
 *   ---------------------------------------------------------------
 *   none              [친구 추가]                send
 *   request_sent      [요청 취소]                cancel
 *   request_received  [확인] [삭제]              accept / decline
 *   friends           [친구 끊기] (확인 대화상자) unfriend
 *   self              (버튼 없음)
 *
 * 각 버튼은 actions/friend.php 로 POST 하는 작은 폼이다.
 *
 * 관계 상태에 따른 친구 버튼
 * @var int    $userId  상대 사용자
 * @var string $status  friend_status() 결과
 * @var string $size    '' | 'btn-sm'
 * @var bool   $block   버튼 너비 100%
 */
$size  = $size ?? '';
$block = !empty($block);
$w = $block ? 'w-full' : '';
$action = url('actions/friend.php');
// 버튼 하나(= POST 폼 하나)를 출력하는 작은 함수. $confirm 이 있으면 전송 전에 확인 대화상자를 띄운다(app.js data-confirm).
$form = function (string $op, string $label, string $iconName, string $variant, string $confirm = '') use ($action, $userId, $size, $w) {
    ?>
    <form method="post" action="<?= $action ?>" class="<?= $w ?>" <?= $confirm ? 'data-confirm="' . e($confirm) . '"' : '' ?>>
        <?= csrf_field() ?>
        <input type="hidden" name="op" value="<?= e($op) ?>">
        <input type="hidden" name="user_id" value="<?= (int) $userId ?>">
        <button class="btn <?= $variant ?> <?= $size ?> <?= $w ?>"><?= icon($iconName) ?> <?= e($label) ?></button>
    </form>
    <?php
};
?>
<div class="flex <?= $block ? 'flex-col' : 'flex-wrap' ?> gap-2">
<?php switch ($status):
    case 'none':
        $form('send', '친구 추가', 'user-plus', 'btn-primary');
        break;
    case 'request_sent':
        $form('cancel', '요청 취소', 'user-x', 'btn-secondary');
        break;
    case 'request_received':
        $form('accept', '확인', 'user-check', 'btn-primary');
        $form('decline', '삭제', 'x', 'btn-secondary');
        break;
    case 'friends':
        $form('unfriend', '친구 끊기', 'user-minus', 'btn-secondary', '정말 친구를 끊으시겠습니까?');
        break;
endswitch ?>
</div>
