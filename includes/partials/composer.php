<?php
/**
 * ============================================================================
 * partials/composer.php — 게시물 작성 카드 + "게시물 만들기" 다이얼로그
 * ============================================================================
 *
 * 사용처: 뉴스피드(index.php), 내 프로필(profile.php)
 *
 * 1) 작성 카드: "OO님, 무슨 생각을 하고 계신가요?" 버튼, [사진] [동영상] 버튼
 *    → 클릭하면 아래 다이얼로그가 열린다. (data-open-composer, [사진]/[동영상]은 파일 선택창까지 바로 열림)
 * 2) 다이얼로그(#composer-dialog): 실제 전송 폼 → actions/post_create.php (multipart/form-data)
 *    - 공개 범위 select (기본: 친구만)
 *    - 본문 textarea
 *    - 첨부 미리보기 그리드: app.js 가 선택한 파일을 미리 보여주고 개별 삭제/전체 삭제를 지원
 *    - 드래그 앤 드롭으로도 파일 추가 가능
 *    - [게시] 버튼은 본문이나 첨부가 있을 때만 활성화
 * 다이얼로그 동작(열기/닫기/미리보기)은 assets/app.js 의 "게시물 작성 다이얼로그" 섹션 참고.
 */
$me = current_user();
$firstName = e($me['first_name']);
$maxFiles = (int) config('upload.max_files');
?>
<div class="card gap-0 p-4">
    <div class="flex items-center gap-2">
        <a href="<?= profile_url((int) $me['id']) ?>"><?= avatar($me, 'size-10', 'text-sm') ?></a>
        <button type="button" class="h-10 flex-1 rounded-full bg-muted px-4 text-left text-muted-foreground transition-colors hover:bg-accent dark:hover:bg-input/50" data-open-composer>
            <?= $firstName ?>님, 무슨 생각을 하고 계신가요?
        </button>
    </div>
    <div class="mt-3 grid grid-cols-2 gap-1 border-t pt-2">
        <button type="button" class="btn btn-ghost text-muted-foreground" data-open-composer="media"><?= icon('image', '!size-5 text-emerald-500') ?> 사진</button>
        <button type="button" class="btn btn-ghost text-muted-foreground" data-open-composer="media"><?= icon('clapperboard', '!size-5 text-rose-500') ?> 동영상</button>
    </div>
</div>

<!-- 게시물 만들기 다이얼로그 (shadcn Dialog 스타일) -->
<div id="composer-dialog" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4 sm:items-center" hidden role="dialog" aria-modal="true" aria-labelledby="composer-title">
    <form method="post" action="<?= url('actions/post_create.php') ?>" enctype="multipart/form-data"
          class="card relative w-full max-w-lg gap-0 shadow-lg" id="composer-form" data-max-files="<?= $maxFiles ?>">
        <?= csrf_field() ?>
        <div class="flex items-center justify-center border-b px-4 py-3">
            <h2 id="composer-title" class="text-lg font-semibold">게시물 만들기</h2>
            <button type="button" class="btn btn-secondary btn-icon absolute right-3 top-2.5 rounded-full" data-close-composer aria-label="닫기"><?= icon('x') ?></button>
        </div>

        <div class="flex items-center gap-3 px-4 pt-4">
            <?= avatar($me, 'size-10', 'text-sm') ?>
            <div>
                <p class="font-semibold"><?= e(full_name($me)) ?></p>
                <select name="privacy" class="select mt-0.5 h-7 w-auto bg-muted py-0 pl-2 pr-7 text-xs font-medium shadow-none">
                    <option value="public">🌏 전체 공개</option>
                    <option value="friends" selected>👥 친구만</option>
                    <option value="only_me">🔒 나만 보기</option>
                </select>
            </div>
        </div>

        <div class="max-h-[55vh] overflow-y-auto px-4 pt-3">
            <textarea name="content" rows="4" maxlength="5000" data-composer-text
                      class="w-full resize-none border-0 bg-transparent text-lg outline-none placeholder:text-muted-foreground"
                      placeholder="<?= $firstName ?>님, 무슨 생각을 하고 계신가요?"></textarea>

            <div class="relative mb-3 hidden rounded-lg border p-2" data-preview-wrap>
                <button type="button" class="btn btn-secondary btn-icon absolute right-3 top-3 z-10 size-8 rounded-full shadow" data-clear-media aria-label="모든 첨부 삭제"><?= icon('x') ?></button>
                <div class="grid grid-cols-2 gap-1.5" data-preview-grid></div>
            </div>
        </div>

        <div class="px-4 pb-4">
            <div class="mb-3 flex items-center justify-between rounded-lg border px-4 py-2" data-dropzone>
                <span class="text-sm font-semibold">게시물에 추가</span>
                <div class="flex items-center gap-1">
                    <label class="btn btn-ghost btn-icon rounded-full" title="사진/동영상">
                        <?= icon('images', '!size-6 text-emerald-500') ?>
                        <input type="file" name="media[]" multiple accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm,video/quicktime,video/ogg" class="sr-only" data-media-input>
                    </label>
                </div>
            </div>
            <p class="mb-3 text-xs text-muted-foreground">사진(JPG, PNG, GIF, WEBP) · 동영상(MP4, WEBM, MOV) 최대 <?= $maxFiles ?>개</p>
            <button class="btn btn-primary w-full" data-composer-submit disabled>게시</button>
        </div>
    </form>
</div>
