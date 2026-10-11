/**
 * ============================================================================
 * assets/app.js — 화면 상호작용 스크립트 (프레임워크 없는 순수 JavaScript)
 * ============================================================================
 *
 * footer.php 에서 모든 페이지에 로드된다. HTML 의 data-* 속성을 찾아 동작을 연결한다.
 *
 *   섹션                    연결되는 HTML 속성                      관련 서버 파일
 *   ---------------------------------------------------------------------------------------
 *   토스트                  [data-toast], #toaster                  header.php (플래시 메시지)
 *   드롭다운                [data-dropdown] / -trigger / -menu      header.php, post_card.php
 *   다크 모드               [data-theme-toggle]                     header.php (localStorage 'theme')
 *   확인/자동 제출/링크 복사 form[data-confirm], [data-auto-submit], [data-copy-link]
 *   좋아요                  form[data-like-form]                    actions/like.php
 *   댓글 작성/삭제          form[data-comment-form], [data-comment-delete]  actions/comment_*.php
 *   게시물 더 보기          [data-load-more]                        index.php / profile.php ?partial=1
 *   게시물 작성 다이얼로그  #composer-dialog, [data-open-composer]  composer.php → actions/post_create.php
 *   라이트박스              [data-lightbox-item], #lightbox         post_card.php, profile.php
 *
 * 설계 원칙
 *   - 이벤트 위임: document 에 리스너를 한 번만 달고 e.target.closest() 로 대상을 찾는다.
 *     → "더 보기"로 나중에 추가된 게시물 카드에도 별도 작업 없이 동작한다.
 *   - 점진적 향상: 좋아요/댓글 폼은 JS 가 없어도 일반 POST 로 동작하고, JS 가 있으면 AJAX 로 가로챈다.
 *   - 즉시 실행 함수 (function(){ ... })() 로 감싸 전역 변수를 만들지 않는다.
 */
(function () {
    'use strict';

    // jQuery 스타일의 짧은 선택자 도우미
    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

    // HTML 을 동적으로 추가한 뒤 호출 → 새로 생긴 <i data-lucide> 를 SVG 아이콘으로 변환
    const refreshIcons = () => window.lucide && window.lucide.createIcons();

    /* ------------------------------------------------------------------
     * 토스트 (shadcn Sonner 스타일)
     * ---------------------------------------------------------------- */
    // 토스트를 부드럽게 사라지게 한 뒤 DOM 에서 제거
    function dismissToast(el) {
        el.style.transition = 'opacity .2s, transform .2s';
        el.style.opacity = '0';
        el.style.transform = 'translateY(8px)';
        setTimeout(() => el.remove(), 200);
    }

    // JS 에서 토스트 띄우기 (AJAX 오류, 링크 복사 완료 등). type: success | error | info
    // message 는 textContent 로 넣어 HTML 로 해석되지 않게 한다(XSS 방지).
    function toast(message, type = 'info') {
        const iconName = { success: 'circle-check', error: 'circle-alert' }[type] || 'info';
        const color = { success: 'text-emerald-500', error: 'text-destructive' }[type] || 'text-primary';
        const el = document.createElement('div');
        el.className = 'toast pointer-events-auto flex items-start gap-3 rounded-lg border bg-popover p-4 text-sm text-popover-foreground shadow-lg';
        el.innerHTML = `<i data-lucide="${iconName}" class="size-5 shrink-0 ${color}"></i><p class="flex-1"></p>`;
        el.querySelector('p').textContent = message;
        $('#toaster').appendChild(el);
        refreshIcons();
        setTimeout(() => dismissToast(el), 3500);
    }

    // 서버가 그려 준 플래시 메시지 토스트는 4초 뒤 자동으로 닫는다.
    $$('[data-toast]').forEach((el) => setTimeout(() => dismissToast(el), 4000));

    /* ------------------------------------------------------------------
     * AJAX 헬퍼
     * ---------------------------------------------------------------- */
    /**
     * 폼을 AJAX(fetch)로 전송하고 JSON 응답을 돌려준다.
     * - FormData(form) 에 hidden _csrf 필드가 포함되므로 CSRF 검증도 그대로 통과한다.
     * - X-Requested-With / Accept 헤더 → 서버의 is_ajax() 가 true 가 되어 리다이렉트 대신 JSON 으로 응답.
     * - HTTP 오류(4xx/5xx)면 서버가 보낸 error 메시지로 예외를 던진다.
     */
    async function postForm(form) {
        const res = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            credentials: 'same-origin',
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.error || '요청을 처리하지 못했습니다.');
        return data;
    }

    /* ------------------------------------------------------------------
     * 드롭다운
     * ---------------------------------------------------------------- */
    // 열려 있는 드롭다운 메뉴를 모두 닫는다. (except 로 넘긴 메뉴는 제외)
    function closeDropdowns(except) {
        $$('[data-dropdown-menu]').forEach((m) => {
            if (m !== except) m.hidden = true;
        });
    }

    // 트리거 클릭 → 해당 메뉴 토글 (다른 메뉴는 닫음) / 메뉴 바깥 클릭 → 전부 닫기
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-dropdown-trigger]');
        if (trigger) {
            const menu = $('[data-dropdown-menu]', trigger.closest('[data-dropdown]'));
            closeDropdowns(menu);
            menu.hidden = !menu.hidden;
            return;
        }
        if (!e.target.closest('[data-dropdown-menu]')) closeDropdowns();
    });

    /* ------------------------------------------------------------------
     * 다크 모드
     * ---------------------------------------------------------------- */
    document.addEventListener('click', (e) => {
        if (!e.target.closest('[data-theme-toggle]')) return;
        // <html class="dark"> 를 켜고 끄면 header.php 의 .dark { --background: ... } 색상이 적용된다.
        // 선택은 localStorage 에 저장되고, 다음 방문 시 header.php <head> 의 스크립트가 미리 적용한다.
        const dark = document.documentElement.classList.toggle('dark');
        try { localStorage.setItem('theme', dark ? 'dark' : 'light'); } catch (err) {}
    });

    /* ------------------------------------------------------------------
     * 확인 대화상자 / 파일 선택 시 자동 제출 / 링크 복사
     * ---------------------------------------------------------------- */
    // <form data-confirm="정말 삭제할까요?"> → 전송 전에 확인 대화상자. 취소하면 전송 중단.
    // 마지막 인자 true(캡처 단계): 다른 submit 처리기보다 먼저 실행되게 한다.
    document.addEventListener('submit', (e) => {
        const form = e.target.closest('form[data-confirm]');
        if (form && !confirm(form.dataset.confirm)) e.preventDefault();
    }, true);

    // <input type="file" data-auto-submit> → 파일을 고르는 즉시 폼 전송 (프로필/커버 사진 변경)

    document.addEventListener('change', (e) => {
        if (e.target.matches('[data-auto-submit]') && e.target.files.length) {
            e.target.form.submit();
        }
    });

    // [data-copy-link="/post.php?id=3"] → 전체 URL 로 바꿔 클립보드에 복사 (실패 시 prompt 로 보여줌)
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-copy-link]');
        if (!btn) return;
        const link = new URL(btn.dataset.copyLink, location.origin).href;
        try {
            await navigator.clipboard.writeText(link);
            toast('링크가 복사되었습니다.', 'success');
        } catch (err) {
            prompt('아래 링크를 복사하세요.', link);
        }
        closeDropdowns();
    });

    /* ------------------------------------------------------------------
     * 좋아요
     * ---------------------------------------------------------------- */
    document.addEventListener('submit', async (e) => {
        const form = e.target.closest('form[data-like-form]');
        if (!form) return;
        e.preventDefault();
        const card = form.closest('[data-post]');
        const btn = $('[data-like-button]', form);
        btn.disabled = true;   // 응답 전 연타 방지
        try {
            // 서버 응답: { liked: true|false, count: 숫자 } → 버튼 색, 아이콘 채움, 숫자 갱신
            const data = await postForm(form);
            btn.classList.toggle('!text-primary', data.liked);
            btn.setAttribute('aria-pressed', String(data.liked));
            const svg = $('svg', btn);
            if (svg) svg.classList.toggle('fill-current', data.liked);
            $('[data-like-count]', card).textContent = data.count;
            $('[data-like-summary]', card).classList.toggle('invisible', data.count === 0);
        } catch (err) {
            toast(err.message, 'error');
        } finally {
            btn.disabled = false;
        }
    });

    /* ------------------------------------------------------------------
     * 댓글 작성 / 삭제
     * ---------------------------------------------------------------- */
    // 카드의 "댓글 N개" 숫자 갱신 (0개면 자리만 남기고 숨김)
    function updateCommentCount(card, count) {
        $('[data-comment-count]', card).textContent = count;
        $('[data-comment-summary]', card).classList.toggle('invisible', count === 0);
    }

    document.addEventListener('submit', async (e) => {
        const form = e.target.closest('form[data-comment-form]');
        if (!form) return;
        e.preventDefault();
        const input = form.elements.content;
        if (!input.value.trim()) return;
        const card = form.closest('[data-post]');
        const btn = $('button', form);
        btn.disabled = true;
        try {
            // 서버 응답: { html: 댓글 HTML(partials/comment.php), count: 전체 댓글 수 }
            const data = await postForm(form);
            $('[data-comment-list]', card).insertAdjacentHTML('beforeend', data.html);
            updateCommentCount(card, data.count);
            input.value = '';
            refreshIcons();
        } catch (err) {
            toast(err.message, 'error');
        } finally {
            btn.disabled = false;
            input.focus();
        }
    });

    document.addEventListener('submit', async (e) => {
        const form = e.target.closest('form[data-comment-delete]');
        if (!form) return;
        e.preventDefault();
        if (!confirm('댓글을 삭제하시겠습니까?')) return;
        const card = form.closest('[data-post]');
        try {
            const data = await postForm(form);
            form.closest('[data-comment]').remove();
            updateCommentCount(card, data.count);
        } catch (err) {
            toast(err.message, 'error');
        }
    });

    // [댓글 달기] 버튼 → 해당 게시물의 댓글 입력칸으로 스크롤 + 포커스
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-focus-comment]');
        if (!btn) return;
        const input = document.getElementById('comment-input-' + btn.dataset.focusComment);
        if (input) {
            input.focus();
            input.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    /* ------------------------------------------------------------------
     * 게시물 더 보기
     * ---------------------------------------------------------------- */
    // [게시물 더 보기] → data-load-more URL 에 ?page=N&partial=1 을 붙여 요청
    // 서버 응답 { html: 카드 HTML, hasMore: 다음 페이지 여부 } → #feed 끝에 붙이고, 더 없으면 버튼 제거
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-load-more]');
        if (!btn) return;
        const spinner = $('svg', btn);
        btn.disabled = true;
        if (spinner) spinner.classList.remove('hidden');
        try {
            const url = new URL(btn.dataset.loadMore, location.origin);
            url.searchParams.set('page', btn.dataset.page);
            url.searchParams.set('partial', '1');
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } });
            const data = await res.json();
            $('#feed').insertAdjacentHTML('beforeend', data.html);
            refreshIcons();
            if (data.hasMore) {
                btn.dataset.page = String(Number(btn.dataset.page) + 1);
            } else {
                btn.remove();
            }
        } catch (err) {
            toast('게시물을 불러오지 못했습니다.', 'error');
        } finally {
            btn.disabled = false;
            if (spinner) spinner.classList.add('hidden');
        }
    });

    /* ------------------------------------------------------------------
     * 게시물 작성 다이얼로그 + 첨부 미리보기
     * ---------------------------------------------------------------- */
    const dialog = $('#composer-dialog');
    if (dialog) {
        const form = $('#composer-form');
        const text = $('[data-composer-text]', form);
        const input = $('[data-media-input]', form);
        const submit = $('[data-composer-submit]', form);
        const previewWrap = $('[data-preview-wrap]', form);
        const previewGrid = $('[data-preview-grid]', form);
        const maxFiles = Number(form.dataset.maxFiles || 10);
        // 첨부할 파일 목록의 "진짜 원본". 파일 input 은 선택할 때마다 값이 통째로 바뀌므로,
        // 이 배열에 누적해 두고 sync() 에서 input.files 를 이 배열로 다시 만든다.
        let files = [];

        // 다이얼로그 열기. withMedia=true([사진]/[동영상] 버튼)면 파일 선택창을 바로 띄운다.
        const open = (withMedia) => {
            dialog.hidden = false;
            document.body.style.overflow = 'hidden';
            if (withMedia) input.click();
            else setTimeout(() => text.focus(), 50);
        };
        const close = () => {
            dialog.hidden = true;
            document.body.style.overflow = '';
        };

        // files 배열 → (1) input.files 반영 (2) 미리보기 그리드 다시 그리기 (3) [게시] 버튼 상태 갱신
        const sync = () => {
            // DataTransfer로 input.files 재구성 (개별 삭제 반영)
            const dt = new DataTransfer();
            files.forEach((f) => dt.items.add(f));
            input.files = dt.files;

            // 이전 미리보기용 blob URL 해제 (메모리 누수 방지)
            previewGrid.querySelectorAll('[data-object-url]').forEach((el) => URL.revokeObjectURL(el.dataset.objectUrl));
            previewGrid.innerHTML = '';
            previewWrap.classList.toggle('hidden', files.length === 0);
            previewGrid.className = 'grid gap-1.5 ' + (files.length === 1 ? 'grid-cols-1' : 'grid-cols-2');

            files.forEach((file, i) => {
                // 업로드 전에 브라우저 메모리의 파일을 바로 보여주는 임시 URL (blob:...)
                const objectUrl = URL.createObjectURL(file);
                const item = document.createElement('div');
                item.className = 'group relative overflow-hidden rounded-md bg-muted ' + (files.length === 1 ? '' : 'aspect-square');
                item.dataset.objectUrl = objectUrl;
                const mediaEl = file.type.startsWith('video/')
                    ? Object.assign(document.createElement('video'), { src: objectUrl, muted: true, controls: files.length === 1, className: 'size-full max-h-80 w-full object-cover' })
                    : Object.assign(document.createElement('img'), { src: objectUrl, alt: '', className: 'size-full max-h-80 w-full object-cover' });
                item.appendChild(mediaEl);
                const rm = document.createElement('button');
                rm.type = 'button';
                rm.className = 'absolute left-1.5 top-1.5 flex size-7 items-center justify-center rounded-full bg-black/60 text-white opacity-0 transition-opacity group-hover:opacity-100';
                rm.setAttribute('aria-label', '첨부 삭제');
                rm.innerHTML = '<i data-lucide="x" class="size-4"></i>';
                rm.addEventListener('click', () => { files.splice(i, 1); sync(); });
                item.appendChild(rm);
                previewGrid.appendChild(item);
            });
            refreshIcons();
            updateSubmit();
        };

        // 본문도 첨부도 없으면 [게시] 비활성화
        const updateSubmit = () => {
            submit.disabled = text.value.trim() === '' && files.length === 0;
        };

        // 파일 추가: 이미지/동영상만 받고, 최대 개수를 넘으면 잘라낸다. (최종 검증은 서버가 다시 함)
        const addFiles = (list) => {
            const accepted = Array.from(list).filter((f) => /^(image|video)\//.test(f.type));
            if (accepted.length < list.length) toast('사진 또는 동영상 파일만 첨부할 수 있습니다.', 'error');
            files = files.concat(accepted);
            if (files.length > maxFiles) {
                toast(`최대 ${maxFiles}개까지 첨부할 수 있습니다.`, 'error');
                files = files.slice(0, maxFiles);
            }
            sync();
        };

        $$('[data-open-composer]').forEach((btn) =>
            btn.addEventListener('click', () => open(btn.dataset.openComposer === 'media'))
        );
        $$('[data-close-composer]', dialog).forEach((btn) => btn.addEventListener('click', close));
        dialog.addEventListener('mousedown', (e) => { if (e.target === dialog) close(); });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !dialog.hidden) close(); });

        text.addEventListener('input', updateSubmit);
        input.addEventListener('change', () => {
            // input.files 는 sync() 에서 덮어쓰므로 새로 선택한 파일만 추가
            const picked = Array.from(input.files).filter((f) => !files.includes(f));
            addFiles(picked);
        });
        $('[data-clear-media]', form).addEventListener('click', () => { files = []; sync(); });

        // 드래그 앤 드롭
        form.addEventListener('dragover', (e) => { e.preventDefault(); form.classList.add('ring-2', 'ring-primary'); });
        form.addEventListener('dragleave', (e) => { if (!form.contains(e.relatedTarget)) form.classList.remove('ring-2', 'ring-primary'); });
        form.addEventListener('drop', (e) => {
            e.preventDefault();
            form.classList.remove('ring-2', 'ring-primary');
            if (e.dataTransfer.files.length) addFiles(e.dataTransfer.files);
        });

        // 전송 시작: 버튼을 "게시 중..."으로 바꿔 중복 전송 방지 (전송 자체는 일반 폼 POST)
        form.addEventListener('submit', () => {
            submit.disabled = true;
            submit.innerHTML = '<i data-lucide="loader-circle" class="size-4 animate-spin"></i> 게시 중...';
            refreshIcons();
        });
    }

    /* ------------------------------------------------------------------
     * 라이트박스
     * ---------------------------------------------------------------- */
    const lightbox = $('#lightbox');
    if (lightbox) {
        const body = $('#lightbox-body');
        // 같은 data-group(예: post-12)을 가진 항목들을 묶어서 ←/→ 로 넘겨 볼 수 있게 한다.
        let group = [];   // 현재 묶음의 요소 목록
        let index = 0;    // 지금 보고 있는 위치

        const render = () => {
            const item = group[index];
            body.innerHTML = '';
            const el = item.dataset.type === 'video'
                ? Object.assign(document.createElement('video'), { src: item.dataset.src, controls: true, autoplay: true, playsInline: true })
                : Object.assign(document.createElement('img'), { src: item.dataset.src, alt: '' });
            el.className = 'max-h-[90vh] max-w-full rounded-md object-contain';
            body.appendChild(el);
            const multi = group.length > 1;
            $('[data-lightbox-prev]', lightbox).hidden = !multi;
            $('[data-lightbox-next]', lightbox).hidden = !multi;
        };
        const show = (item) => {
            group = $$(`[data-lightbox-item][data-group="${item.dataset.group}"]`);
            index = Math.max(0, group.indexOf(item));
            lightbox.hidden = false;
            document.body.style.overflow = 'hidden';
            render();
        };
        const hide = () => {
            lightbox.hidden = true;
            body.innerHTML = '';
            document.body.style.overflow = '';
        };
        const step = (d) => { index = (index + d + group.length) % group.length; render(); };

        document.addEventListener('click', (e) => {
            const item = e.target.closest('[data-lightbox-item]');
            if (item) { e.preventDefault(); show(item); }
        });
        $('[data-lightbox-close]', lightbox).addEventListener('click', hide);
        $('[data-lightbox-prev]', lightbox).addEventListener('click', () => step(-1));
        $('[data-lightbox-next]', lightbox).addEventListener('click', () => step(1));
        lightbox.addEventListener('click', (e) => { if (e.target === lightbox || e.target === body) hide(); });
        document.addEventListener('keydown', (e) => {
            if (lightbox.hidden) return;
            if (e.key === 'Escape') hide();
            if (e.key === 'ArrowLeft') step(-1);
            if (e.key === 'ArrowRight') step(1);
        });
    }

    // 처음 페이지 로드 시 모든 아이콘 변환
    refreshIcons();
})();
