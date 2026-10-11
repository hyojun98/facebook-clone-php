# Facebook Clone — 화면 기능 및 소스 구조

이 문서는 프로젝트의 **화면별 기능**과 **소스 코드 구조**를 설명합니다.
설치·실행 방법은 [README.md](README.md)를 참고하세요.

- 언어/런타임: PHP 8.1+ (프레임워크 없음, PDO), MySQL 8.4
- UI: Tailwind CSS v4 (Play CDN) + shadcn/ui 디자인 토큰, Lucide 아이콘, Pretendard 폰트
- 프론트엔드 스크립트: 순수 JavaScript 파일 하나 (`assets/app.js`)

---

## 목차

1. [전체 구조 한눈에 보기](#1-전체-구조-한눈에-보기)
2. [폴더 / 파일 구조](#2-폴더--파일-구조)
3. [화면별 기능](#3-화면별-기능)
4. [처리기 (actions)](#4-처리기-actions)
5. [데이터베이스](#5-데이터베이스)
6. [핵심 로직 설명](#6-핵심-로직-설명)
7. [프론트엔드 (디자인 / JavaScript)](#7-프론트엔드-디자인--javascript)
8. [로컬 개발 도구](#8-로컬-개발-도구)
9. [기능 추가 가이드](#9-기능-추가-가이드)

---

## 1. 전체 구조 한눈에 보기

### 요청 처리 흐름

```
[브라우저]
   │  GET /profile.php?id=2
   ▼
[router.php] ── 내부 경로(includes/, .runtime/ ...)면 403 차단
   │
   ▼
[profile.php]  ← "화면" 파일 (루트의 *.php)
   │ 1. require includes/bootstrap.php   (설정 · 세션 · 함수 파일 로드)
   │ 2. require_login()                  (비로그인 → login.php)
   │ 3. includes/*.php 함수로 데이터 조회  (find_user, profile_posts ...)
   │ 4. partials/header.php              (<head>, 상단 네비게이션)
   │ 5. 본문 HTML + partial('post_card') (조각 템플릿)
   │ 6. partials/footer.php              (app.js 로드)
   ▼
[HTML 응답] → 브라우저에서 assets/app.js 가 버튼/폼에 동작 연결
```

### 데이터 변경(POST) 흐름 — PRG 패턴

```
[폼 전송]  POST /actions/friend.php  (op=accept, user_id=4, _csrf=...)
   │
   ▼
[actions/friend.php]
   │ 1. bootstrap → require_login() → require_post() (POST + CSRF 검증)
   │ 2. includes/friends.php 의 accept_friend_request() 호출 → DB 변경 + 알림 생성
   │ 3. flash('success', '...')  ← 다음 화면에 보여줄 메시지
   │ 4. redirect_back()          ← 원래 화면으로 이동 (새로고침해도 중복 전송 안 됨)
   ▼
[원래 화면 GET] → header.php 가 플래시 메시지를 토스트로 표시
```

좋아요·댓글처럼 화면 이동 없이 처리하는 기능은 `app.js` 가 같은 폼을 **AJAX(fetch)** 로 보내고,
서버는 `is_ajax()` 로 판별해 리다이렉트 대신 **JSON** 으로 응답합니다.
(JavaScript 가 꺼져 있어도 일반 폼 전송으로 동작합니다.)

### 계층 구분

| 계층 | 위치 | 역할 |
| --- | --- | --- |
| 화면 (Page) | 루트 `*.php` | URL 하나 = 파일 하나. 데이터 조회 후 HTML 출력 |
| 처리기 (Action) | `actions/*.php` | POST 전용. 데이터 변경 후 리다이렉트 또는 JSON |
| 도메인 함수 | `includes/*.php` | DB 조회/변경 로직 (사용자, 친구, 게시물, 알림, 업로드) |
| 조각 템플릿 | `includes/partials/*.php` | 여러 화면에서 재사용하는 HTML (게시물 카드, 친구 버튼 ...) |
| 클라이언트 | `assets/app.js` | 드롭다운, AJAX, 업로드 미리보기, 라이트박스 |

---

## 2. 폴더 / 파일 구조

```
php/
├── index.php              뉴스피드 (홈)
├── login.php              로그인
├── register.php           회원가입
├── logout.php             로그아웃 (POST)
├── profile.php            프로필 (?id=, &tab=posts|about|friends|photos)
├── friends.php            친구 (?tab=requests|suggestions|all|sent)
├── notifications.php      알림 (?filter=unread, ?open=알림ID)
├── post.php               게시물 상세 (?id=)
├── post_edit.php          게시물 수정 (?id=)
├── search.php             사람 검색 (?q=)
├── settings.php           설정 (프로필 사진/정보, 비밀번호)
│
├── actions/               POST 처리기
│   ├── post_create.php        게시물 작성 (사진/동영상 업로드)
│   ├── post_delete.php        게시물 삭제
│   ├── like.php               좋아요 토글 (AJAX)
│   ├── comment_create.php     댓글 작성 (AJAX)
│   ├── comment_delete.php     댓글 삭제 (AJAX)
│   ├── friend.php             친구 요청/수락/거절/취소/끊기
│   ├── notifications_read.php 알림 모두 읽음
│   └── profile_photo.php      프로필/커버 사진 변경
│
├── includes/
│   ├── bootstrap.php      공통 진입점 (설정 로드, 세션 시작, 아래 파일 로드)
│   ├── helpers.php        DB 연결, URL, CSRF, 인증, 플래시, 표시용 함수
│   ├── users.php          회원 조회/생성/수정/검색, 프로필 미디어
│   ├── friends.php        친구 관계, 친구 요청, 알 수도 있는 사람
│   ├── posts.php          게시물 조회(공개 범위), 작성/수정/삭제, 좋아요, 댓글
│   ├── notifications.php  알림 생성/조회/읽음, 알림 문구
│   ├── uploads.php        업로드 검증(MIME)·저장·삭제
│   └── partials/          조각 템플릿
│       ├── header.php         <head>, Tailwind 테마, 상단 네비게이션, 토스트
│       ├── footer.php         라이트박스 틀, 스크립트 로드
│       ├── error.php          오류 화면 (404/403/419)
│       ├── post_card.php      게시물 카드
│       ├── comment.php        댓글 1개
│       ├── composer.php       게시물 작성 카드 + 작성 다이얼로그
│       ├── notification_item.php  알림 1개
│       └── friend_buttons.php 관계별 친구 버튼
│
├── assets/app.js          클라이언트 스크립트
├── uploads/               업로드 파일 (posts/, avatars/, covers/ 아래 년/월 폴더)
├── database/
│   ├── schema.sql         테이블 생성 (기존 테이블 DROP 후 재생성)
│   └── seed.sql           데모 데이터 (5명, 비밀번호 password)
│
├── config.php             기본 설정 (DB, 업로드 정책, 페이지 크기)
├── config.local.php       (setup 이 생성) 로컬 DB 설정 덮어쓰기
├── router.php             PHP 내장 서버용 라우터 (접근 차단)
├── .htaccess              Apache 용 접근 차단 / 업로드 용량
│
├── setup.bat / start.bat / stop.bat / reset-db.bat / heidisql.bat
├── scripts/               위 .bat 이 실행하는 PowerShell 스크립트
├── .vscode/               F5 실행, 작업(Task), 권장 확장
└── .runtime/              (setup 이 생성) 포터블 PHP·MySQL·HeidiSQL, DB 데이터, 로그
```

---

## 3. 화면별 기능

### 3.1 공통 레이아웃 (`includes/partials/header.php`, `footer.php`)

모든 로그인 화면 상단에 표시됩니다.

| 영역 | 기능 |
| --- | --- |
| 로고 `f` | 뉴스피드로 이동 |
| 검색창 | 이름/이메일로 사람 검색 → `search.php` |
| 가운데 아이콘 | 홈 · 친구(받은 요청 수 배지) · 사진 · 알림. 현재 화면은 파란 밑줄 |
| 알림(종) 드롭다운 | 최근 알림 8개, 읽지 않은 수 배지, "모두 읽음으로 표시", "모든 알림 보기" |
| 계정 드롭다운 | 내 프로필 / 친구 / 설정 / 다크 모드 전환 / 로그아웃 |
| 모바일 하단 탭 | 좁은 화면(768px 미만)에서 홈·친구·알림·프로필 탭바 |
| 토스트 | 처리 결과 메시지(성공/오류/안내)를 오른쪽 아래에 4초간 표시 |
| 라이트박스 | 사진/동영상 클릭 시 전체 화면 보기, ←/→ 키로 넘기기, Esc 닫기 |
| 다크 모드 | 선택이 브라우저에 저장되며, 처음엔 OS 설정을 따름 |

### 3.2 로그인 — `login.php`

- 왼쪽: 서비스 이름과 소개 문구, 오른쪽: 로그인 카드 (상단 네비게이션 없음)
- 이메일/비밀번호 검증 실패 시 빨간 경고 박스와 함께 입력한 이메일 유지
- "새 계정 만들기" → 회원가입, 하단에 데모 계정 안내
- 이미 로그인한 상태로 접근하면 뉴스피드로 이동

### 3.3 회원가입 — `register.php`

- 입력: 성, 이름, 이메일, 비밀번호(8자 이상) + 확인, 생년월일(년/월/일), 성별(여성/남성/기타)
- 필드별 오류 메시지와 빨간 테두리 표시, 입력값 유지 (비밀번호 제외)
- 검증: 이메일 형식·중복, 비밀번호 길이·일치, 실제 존재하는 날짜(2월 30일 불가)
- 가입 성공 → 자동 로그인 → 친구 화면 "알 수도 있는 사람" 탭으로 이동

### 3.4 뉴스피드 — `index.php`

| 영역 | 기능 |
| --- | --- |
| 왼쪽 메뉴 (1024px 이상) | 내 프로필, 친구, 알림, 사진 및 동영상, 사람 찾기, 설정 |
| 가운데 | 게시물 작성 카드 → 나와 친구들의 게시물 (최신순, 10개씩) → **게시물 더 보기** (AJAX 로 이어 붙임) |
| 오른쪽 (1280px 이상) | 받은 친구 요청 최대 3개(확인/삭제), 알 수도 있는 사람 3명(+ 버튼), 연락처(친구 목록) |
| 빈 상태 | 게시물이 없으면 "친구 찾기" 안내 카드 |

뉴스피드에는 **나 + 친구**의 게시물 중 내가 볼 수 있는 공개 범위의 글만 나옵니다. ([6.3 공개 범위](#63-게시물-공개-범위))

### 3.5 게시물 작성 — `includes/partials/composer.php`

뉴스피드와 내 프로필에 있는 작성 카드를 누르면 **"게시물 만들기" 다이얼로그**가 열립니다.

- 공개 범위 선택: 🌏 전체 공개 / 👥 친구만(기본) / 🔒 나만 보기
- 본문 입력 (최대 5,000자)
- **사진/동영상 첨부**: 최대 10개, 여러 개 선택 가능
  - 사진: JPG, PNG, GIF, WEBP (10MB 이하) / 동영상: MP4, WEBM, MOV, OGV (100MB 이하)
  - 선택 즉시 미리보기, 각 항목 X 버튼으로 개별 삭제, 전체 삭제 버튼
  - 다이얼로그 위로 파일을 **드래그 앤 드롭**해도 추가됨
- 본문이나 첨부가 하나도 없으면 [게시] 버튼 비활성화
- 카드의 [사진] / [동영상] 버튼은 다이얼로그를 열면서 파일 선택창을 바로 띄움
- Esc, 바깥 클릭, X 버튼으로 닫기

### 3.6 게시물 카드 — `includes/partials/post_card.php`

| 영역 | 기능 |
| --- | --- |
| 헤더 | 작성자 사진·이름(→ 프로필), 작성 시간(→ 상세, 마우스를 올리면 정확한 날짜), 공개 범위 아이콘 |
| "…" 메뉴 | 링크 복사 / (내 글) 게시물 수정, 삭제(확인 대화상자) |
| 본문 | URL 은 자동 링크, 줄바꿈 유지. 짧은 글만 있는 게시물은 큰 글씨 |
| 미디어 | 1개: 원본 비율 (동영상은 바로 재생 컨트롤) / 2개: 2칸 / 3개: 큰 1칸 + 2칸 / 4개 이상: 4칸 + "+N" |
| 반응 요약 | 좋아요 수, 댓글 수(→ 상세) |
| 버튼 | **좋아요**(새로고침 없이 토글, 누르면 파란색) · **댓글 달기**(입력칸으로 이동) · **공유하기**(링크 복사) |
| 댓글 | 최근 3개 + "이전 댓글 N개 더 보기", 입력 후 Enter 로 바로 등록(AJAX), 내 댓글/내 게시물의 댓글은 휴지통 버튼으로 삭제 |

### 3.7 게시물 상세 — `post.php?id=N`

- 게시물 카드 + **모든 댓글**
- 아래 "좋아요 N개" 카드에 좋아요 누른 사람 목록 (→ 프로필)
- 알림 클릭, 공유 링크, 시간 링크로 들어오는 화면
- 볼 권한이 없거나 삭제된 게시물은 404

### 3.8 게시물 수정 — `post_edit.php?id=N`

- 공개 범위와 본문 수정 (첨부된 사진/동영상은 미리보기만, 변경 불가)
- 작성자 본인만 접근 가능 (다른 사람은 404)

### 3.9 프로필 — `profile.php?id=N`

**프로필 헤더**

- 커버 사진(클릭 시 크게 보기), 프로필 사진(원형), 이름, 친구 수, 친구 얼굴 8명
- 내 프로필: 커버/프로필 사진의 📷 버튼으로 바로 변경, [프로필 편집] → 설정
- 다른 사람 프로필: 관계에 맞는 버튼 ([6.4 친구 관계](#64-친구-관계-상태)), 상대가 보낸 요청이 있으면 안내 배너
- 탭 메뉴: 게시물 · 정보 · 친구 · 사진/동영상

| 탭 | 내용 |
| --- | --- |
| 게시물 (기본) | 왼쪽: 소개 카드(소개글, 직장, 학교, 거주지, 생일, 성별, 가입일), 사진 9개, 친구 9명 / 오른쪽: (내 프로필이면) 작성 카드 + 게시물 목록 + 더 보기 |
| 정보 | 소개글과 상세 정보 목록 |
| 친구 | 친구 카드 목록 (함께 아는 친구 수, 거주지) |
| 사진/동영상 | 볼 수 있는 게시물의 사진·동영상 그리드 (클릭 시 라이트박스) |

친구가 아닌 사람의 프로필에서는 '친구만' / '나만 보기' 게시물과 그 사진이 보이지 않습니다.

### 3.10 친구 — `friends.php`

| 탭 | 표시 대상 | 카드 버튼 |
| --- | --- | --- |
| 친구 요청 (기본) | 나에게 요청을 보낸 사람 (요청 시간 표시) | [확인] [삭제] |
| 알 수도 있는 사람 | 관계가 없는 사람, **함께 아는 친구가 많은 순** | [친구 추가] |
| 모든 친구 | 내 친구 | [프로필 보기] |
| 보낸 요청 | 내가 요청을 보낸 사람 | [요청 취소] |

- 넓은 화면: 왼쪽 메뉴 + 카드 그리드 / 좁은 화면: 위쪽 가로 탭 버튼
- 받은 요청 수는 메뉴와 상단 네비게이션의 빨간 배지로 표시

### 3.11 알림 — `notifications.php`

| 알림 | 발생 시점 | 클릭 시 이동 |
| --- | --- | --- |
| 친구 요청 | 누군가 나에게 친구 요청 | 친구 요청 탭 |
| 친구 수락 | 내가 보낸 요청을 상대가 수락 | 상대 프로필 |
| 좋아요 | 내 게시물에 좋아요 (취소하면 알림도 사라짐) | 게시물 상세 |
| 댓글 | 내 게시물에 댓글 | 게시물 상세 |

- 필터: 전체 / 읽지 않음(개수 배지)
- 읽지 않은 알림은 진한 글씨 + 파란 점, 클릭하면 읽음 처리 후 해당 화면으로 이동
- "모두 읽음으로 표시" 버튼
- 게시물 알림에는 게시물 내용 앞 40자를 함께 표시
- 내가 한 행동(내 글에 내가 좋아요)은 알림이 생기지 않음

### 3.12 검색 — `search.php?q=검색어`

- 이름(성+이름 "김민준" / 이름 성 "민준 김") 또는 이메일 일부로 검색
- 결과: 프로필 사진, 이름, 관계(친구/나), 함께 아는 친구 수, 거주지, 친구 버튼

### 3.13 설정 — `settings.php`

| 카드 | 기능 |
| --- | --- |
| 프로필 사진 및 커버 | 업로드(파일 선택 즉시 저장), 삭제(확인 후) |
| 프로필 정보 | 성, 이름, 소개(255자), 직장, 학교, 거주지, 생년월일, 성별 |
| 비밀번호 변경 | 현재 비밀번호 확인 → 새 비밀번호(8자 이상) + 확인 |

### 3.14 오류 화면 — `includes/partials/error.php`

404(없음/권한 없음), 403(권한 없음), 405(잘못된 요청 방식), 419(세션 만료/CSRF) 상황에서 표시되며 [홈으로 가기] 버튼이 있습니다.

---

## 4. 처리기 (actions)

모두 **POST + CSRF 토큰 필수**, 로그인 필요.

| 파일 | 호출 위치 | 파라미터 | 응답 |
| --- | --- | --- | --- |
| `post_create.php` | 작성 다이얼로그 | `content`, `privacy`, `media[]` | 리다이렉트 + 토스트 |
| `post_delete.php` | 카드 "…" 메뉴 | `post_id` | 리다이렉트 (상세 화면에서 지우면 뉴스피드로) |
| `like.php` | 좋아요 버튼 | `post_id` | AJAX: `{liked, count}` / 일반: 리다이렉트 |
| `comment_create.php` | 댓글 입력칸 | `post_id`, `content` | AJAX: `{html, count}` / 일반: 리다이렉트 |
| `comment_delete.php` | 댓글 휴지통 | `comment_id` | AJAX: `{ok, count}` / 일반: 리다이렉트 |
| `friend.php` | 친구 버튼 | `op`(send/accept/decline/cancel/unfriend), `user_id` | 리다이렉트 + 토스트 |
| `notifications_read.php` | "모두 읽음으로 표시" | — | 리다이렉트 |
| `profile_photo.php` | 📷 버튼, 설정 업로드 | `type`(avatar/cover), `photo` | 리다이렉트 + 토스트 |

로그인(`login.php`), 회원가입(`register.php`), 게시물 수정(`post_edit.php`), 설정(`settings.php`)은 화면 파일이 POST 도 직접 처리합니다.

---

## 5. 데이터베이스

DB 이름 `fb_clone`, 문자셋 `utf8mb4` (한글·이모지 지원). 정의: [database/schema.sql](database/schema.sql)

```
users ─┬─< posts ─┬─< post_media
       │          ├─< post_likes  >── users
       │          └─< comments    >── users
       ├─< friendships (requester_id, addressee_id) >── users
       └─< notifications (user_id=받는 사람, actor_id=행동한 사람, post_id) 
```

| 테이블 | 주요 컬럼 | 설명 |
| --- | --- | --- |
| `users` | email(UNIQUE), password_hash, first_name, last_name, bio, location, workplace, education, birthday, gender, avatar, cover | 회원. 사진은 `uploads/...` 상대 경로 |
| `posts` | user_id, content, privacy(public/friends/only_me) | 게시물 |
| `post_media` | post_id, file_path, media_type(image/video), sort_order | 첨부 파일 |
| `post_likes` | **PK(post_id, user_id)** | 1인 1좋아요 |
| `comments` | post_id, user_id, content | 댓글 |
| `friendships` | requester_id, addressee_id, status(pending/accepted), UNIQUE(requester, addressee) | 두 사람 사이에 한 행 |
| `notifications` | user_id, actor_id, type, post_id, is_read | 알림 |

- 모든 외래키는 `ON DELETE CASCADE` → 게시물을 지우면 첨부·좋아요·댓글·관련 알림 행이 함께 삭제됩니다.
- 데모 데이터 관계는 [database/seed.sql](database/seed.sql) 상단 주석 참고 (김민준·이서연·박지호는 서로 친구, 최수아 → 김민준 요청 대기 등)

---

## 6. 핵심 로직 설명

### 6.1 인증과 세션 (`helpers.php`)

- 세션에는 `user_id` 만 저장하고, 사용자 정보는 요청마다 `current_user()` 가 1번 조회해 캐시
- 로그인/비밀번호 변경 시 `session_regenerate_id(true)` 로 세션 ID 재발급 (세션 고정 공격 방지)
- 세션 쿠키: `HttpOnly`, `SameSite=Lax`
- 화면 첫 줄 패턴: `$me = require_login();` (비로그인 → 로그인 화면), 로그인/가입 화면은 `require_guest();`

### 6.2 보안 처리

| 위협 | 대응 | 위치 |
| --- | --- | --- |
| SQL Injection | 모든 쿼리 Prepared Statement (`q($sql, $params)`), 동적 컬럼명은 화이트리스트 | `helpers.php`, `users.php update_user()` |
| XSS | 출력 시 `e()` 이스케이프, 본문은 `format_text()` 가 이스케이프 후 링크/줄바꿈만 변환, JS 토스트는 `textContent` | 모든 템플릿, `app.js` |
| CSRF | 세션 토큰을 모든 폼에 `csrf_field()`, 서버에서 `verify_csrf()` (`hash_equals`) | `helpers.php` |
| 업로드 악용 | 실제 MIME 검사(`finfo`), 허용 목록, 랜덤 파일명, 업로드 폴더 스크립트 실행 차단 | `uploads.php`, `uploads/.htaccess`, `router.php` |
| 권한 우회 | 수정/삭제 쿼리에 `user_id` 조건, 볼 수 없는 게시물에 좋아요/댓글 불가 | `posts.php` |
| Open Redirect | `redirect_back()` 은 같은 호스트의 Referer 만 사용 | `helpers.php` |
| 내부 파일 노출 | `includes/`, `.runtime/`, `config*.php` 등 403 | `router.php`, `.htaccess` |

### 6.3 게시물 공개 범위

`post_privacy_clause()` (SQL 조건) 와 `can_view_post()` (PHP 단건 확인) 가 같은 규칙을 구현합니다.

| 공개 범위 | 작성자 본인 | 친구 | 그 외 |
| --- | :---: | :---: | :---: |
| 전체 공개 (`public`) | O | O | O |
| 친구만 (`friends`) | O | O | X |
| 나만 보기 (`only_me`) | O | X | X |

뉴스피드는 여기에 "작성자가 나 또는 친구" 조건이 더해집니다. 프로필의 사진 탭(`user_media()`)도 같은 규칙을 적용합니다.

### 6.4 친구 관계 상태

`friendships` 에 행이 없으면 관계 없음, 있으면 `status` 와 방향으로 상태가 정해집니다.

```
                send (A→B)                    accept (B)
  [none] ─────────────────────▶ [pending A→B] ─────────────▶ [accepted]
     ▲                             │      │                       │
     │        cancel (A)           │      │  decline (B)          │ unfriend (A 또는 B)
     └─────────────────────────────┘      └───────────────────────┤
     └────────────────────────────────────────────────────────────┘  (행 삭제)
```

A 기준 `friend_status()` 값: `none` → `request_sent`(A가 보냄) / B 기준: `request_received` → 수락 후 둘 다 `friends`

### 6.5 알림 생성 규칙 (`notifications.php`)

- 자기 자신에게는 생성하지 않음
- 좋아요·친구 요청·친구 수락은 같은 사람의 같은 행동이면 **기존 알림을 지우고 새로** 만듦 (중복 방지)
- 좋아요 취소, 친구 요청 취소/처리 시 해당 알림을 회수(`delete_notification`)
- 댓글은 달 때마다 새 알림
- 게시물이 삭제되면 관련 알림은 DB CASCADE 로 함께 삭제

### 6.6 뉴스피드 조회 성능 (`posts.php`)

`fetch_posts()` 가 게시물 목록을 1번 조회하면서 **좋아요 수·댓글 수·내가 눌렀는지**를 서브쿼리로 함께 가져오고,
`hydrate_posts()` 가 미디어와 댓글을 **게시물 id 목록으로 한 번에(IN)** 조회해 붙입니다.
게시물 10개를 보여줄 때 게시물마다 쿼리를 날리지 않고(N+1 문제 회피) 총 3번의 쿼리로 끝납니다.

### 6.7 업로드 처리 (`uploads.php`)

1. `normalize_files()` — `media[]` 다중 업로드 배열을 파일별 배열로 변환
2. `store_upload()` — 업로드 오류 확인 → 실제 MIME 판별 → 허용 형식·크기 확인 → `uploads/종류/년/월/랜덤.확장자` 로 저장
3. `store_post_media()` — 여러 파일 중 하나라도 실패하면 이미 저장한 파일까지 삭제 (전부 성공 또는 전부 실패)
4. DB 저장 실패 시에도 호출한 쪽(`post_create.php`)에서 파일 삭제
5. `post_max_size` 를 넘는 요청은 `require_post()` 가 감지해 "용량 초과" 메시지 표시

---

## 7. 프론트엔드 (디자인 / JavaScript)

### 7.1 Tailwind CSS + shadcn/ui 테마 (`includes/partials/header.php`)

- Tailwind v4 Play CDN(`@tailwindcss/browser@4`)이 브라우저에서 클래스를 읽어 CSS 를 생성합니다. (빌드 과정 없음)
- shadcn/ui 의 디자인 토큰을 그대로 사용합니다.
  - `:root` / `.dark` 에 색상 변수 정의 (`--background`, `--foreground`, `--primary`(파란색), `--muted`, `--border` ...)
  - `@theme inline` 으로 변수를 Tailwind 색상에 연결 → `bg-primary`, `text-muted-foreground`, `border-border` 등으로 사용
- `@layer components` 에 shadcn 컴포넌트 모양을 클래스로 정의했습니다.

| 클래스 | shadcn 대응 | 변형 |
| --- | --- | --- |
| `.btn` | Button | `.btn-primary` `.btn-secondary` `.btn-outline` `.btn-ghost` `.btn-destructive` `.btn-link`, 크기 `.btn-sm` `.btn-lg` `.btn-icon` |
| `.input` `.textarea` `.select` `.label` | Input / Textarea / Select / Label | |
| `.card` `.card-header` `.card-title` `.card-description` `.card-content` | Card | |
| `.badge` | Badge | `.badge-default` `.badge-secondary` `.badge-destructive`, 숫자 배지 `.count-badge` |
| `.dropdown-menu` `.dropdown-item` `.dropdown-separator` | DropdownMenu | |
| `.tab` `.tab-active` | Tabs | |
| `.nav-item` `.side-link` | — | 상단 네비게이션 아이콘, 사이드 메뉴 링크 |

- 프로필 사진이 없으면 `avatar()` 함수가 이름 글자와 사용자별 고정 배경색으로 원형 아바타를 그립니다. (shadcn Avatar 의 Fallback)
- 아이콘은 `icon('house')` → `<i data-lucide="house">` 를 출력하고 Lucide 스크립트가 SVG 로 변환합니다.

### 7.2 assets/app.js

이벤트 위임 방식(document 에 리스너 1개 + `closest()`)이라 "더 보기"로 나중에 추가된 카드에도 그대로 동작합니다.

| 기능 | HTML 표시(data 속성) | 서버 |
| --- | --- | --- |
| 토스트 | `[data-toast]`, `#toaster` | 플래시 메시지 |
| 드롭다운 | `[data-dropdown]`, `[data-dropdown-trigger]`, `[data-dropdown-menu]` | — |
| 다크 모드 | `[data-theme-toggle]` | — (localStorage) |
| 삭제 확인 | `form[data-confirm]` | — |
| 파일 선택 즉시 전송 | `input[data-auto-submit]` | `profile_photo.php` |
| 링크 복사 | `[data-copy-link]` | — |
| 좋아요 | `form[data-like-form]` | `like.php` |
| 댓글 작성/삭제 | `form[data-comment-form]`, `form[data-comment-delete]` | `comment_create.php`, `comment_delete.php` |
| 댓글 입력칸 이동 | `[data-focus-comment]` | — |
| 게시물 더 보기 | `[data-load-more]` | `index.php` / `profile.php` `?partial=1` |
| 작성 다이얼로그 | `#composer-dialog`, `[data-open-composer]`, `[data-media-input]` | `post_create.php` |
| 라이트박스 | `[data-lightbox-item]`(같은 `data-group` 끼리 넘기기), `#lightbox` | — |

---

## 8. 로컬 개발 도구

| 도구 | 파일 | 설명 |
| --- | --- | --- |
| 설치 | `setup.bat` → `scripts/setup.ps1` | 포터블 PHP 8.3, MySQL 8.4, HeidiSQL 을 `.runtime/` 에 설치, DB 생성 |
| 실행 | `start.bat` → `scripts/start.ps1` | MySQL + PHP 내장 서버(`router.php`) 실행, Chrome 열기 |
| 종료 | `stop.bat` → `scripts/stop.ps1` | PHP 서버, 디버그용 Chrome, MySQL 종료 |
| DB 초기화 | `reset-db.bat` → `scripts/reset-db.ps1` | `schema.sql` + `seed.sql` 재실행, 업로드 파일 삭제 |
| DB 보기 | `heidisql.bat` → `scripts/heidisql.ps1` | HeidiSQL 을 `fb_clone (local)` 세션으로 열기 |
| 공통 함수 | `scripts/common.ps1` | 경로(한글 경로면 영문 정션 자동 생성), MySQL 시작/종료, SQL 파일 실행, 다운로드, Chrome 찾기, HeidiSQL 세션 설정 |
| VS Code | `.vscode/launch.json`, `tasks.json` | F5 = 서버 + Chrome(디버그 연결), Ctrl+Shift+B = 서버 시작, 각종 Task |

---

## 9. 기능 추가 가이드

### 새 화면 추가 (예: `groups.php`)

```php
<?php
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();

$items = q('SELECT ...', [...])->fetchAll();   // 또는 includes/ 에 함수 추가

$title = '그룹';
$active = 'groups';                              // header.php 메뉴 강조용
require __DIR__ . '/includes/partials/header.php';
?>
<div class="mx-auto max-w-2xl p-4">
    <div class="card p-4"> ... </div>
</div>
<?php require __DIR__ . '/includes/partials/footer.php'; ?>
```

### 새 POST 처리기 추가

1. `actions/xxx.php` 생성: `bootstrap` → `require_login()` → `require_post()` → 처리 → `flash()` + `redirect_back()` (AJAX 면 `json_response()`)
2. 폼에 `<?= csrf_field() ?>` 포함
3. AJAX 로 처리하려면 `app.js` 에 `form[data-xxx]` submit 리스너를 추가하고 `postForm(form)` 사용

### 새 알림 종류 추가

1. `database/schema.sql` 의 `notifications.type` ENUM 에 값 추가 (기존 DB 는 `ALTER TABLE` 필요)
2. 발생 지점에서 `notify($받는사람, $행동한사람, '새타입', $postId)`
3. `notification_meta()` 의 `match` 에 문구·아이콘·색상·링크 추가

### 새 테이블 추가

1. `database/schema.sql` 에 `CREATE TABLE` 추가 (상단 `DROP TABLE` 목록에도 추가)
2. `includes/` 에 도메인 함수 파일을 만들고 `bootstrap.php` 에서 `require`
3. `reset-db.bat` 으로 반영 (데이터가 초기화되므로 주의)
