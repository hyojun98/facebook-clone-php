<?php
/**
 * ============================================================================
 * partials/footer.php — 공통 레이아웃의 끝부분 (header.php 와 짝)
 * ============================================================================
 *
 * - </main> 닫기
 * - 라이트박스(사진/동영상 크게 보기) 틀: data-lightbox-item 요소를 클릭하면 app.js 가 열어 준다.
 * - 스크립트 로드 (body 끝에 두어 HTML 이 먼저 그려지게 함)
 *     lucide.min.js : <i data-lucide="..."> 를 SVG 아이콘으로 변환
 *     assets/app.js : 드롭다운, 좋아요/댓글 AJAX, 게시물 작성 다이얼로그, 라이트박스 등 모든 상호작용
 */
?>
</main>

<!-- 사진/동영상 라이트박스 (평소에는 hidden, 열리면 화면 전체를 덮는 검은 배경) -->
<div id="lightbox" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/90 p-4" hidden>
    <button type="button" class="btn btn-icon absolute right-4 top-4 rounded-full bg-white/10 text-white hover:bg-white/20" data-lightbox-close aria-label="닫기"><?= icon('x') ?></button>
    <button type="button" class="btn btn-icon absolute left-4 top-1/2 -translate-y-1/2 rounded-full bg-white/10 text-white hover:bg-white/20" data-lightbox-prev aria-label="이전"><?= icon('chevron-left') ?></button>
    <button type="button" class="btn btn-icon absolute right-4 top-1/2 -translate-y-1/2 rounded-full bg-white/10 text-white hover:bg-white/20" data-lightbox-next aria-label="다음"><?= icon('chevron-right') ?></button>
    <div id="lightbox-body" class="flex max-h-full max-w-5xl items-center justify-center"></div>
</div>

<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
<script src="<?= url('assets/app.js') ?>"></script>
</body>
</html>
