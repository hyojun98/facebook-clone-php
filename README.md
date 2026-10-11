# Facebook Clone (Core PHP + Tailwind CSS + shadcn 스타일)

프레임워크 없이 **순수 PHP(PDO)** 로 만든 페이스북 클론입니다.
UI는 **Tailwind CSS v4 (Play CDN)** 와 **shadcn/ui 테마 토큰**(`--background`, `--primary`, `--muted`, `--border`, `--radius` …)을 그대로 옮겨 구성했습니다.

> 화면별 기능과 소스 구조의 자세한 설명은 [CONTENT.md](CONTENT.md)를 참고하세요.

## 주요 기능

| 기능 | 내용 |
| --- | --- |
| 회원가입 / 로그인 | `password_hash` 비밀번호 해시, 세션 고정 방지, CSRF 토큰, 입력값 검증 |
| 뉴스피드 | 나와 친구의 게시물, 공개 범위(전체/친구/나만), "더 보기" 무한 로딩(AJAX) |
| 게시물 작성 | 텍스트 + **사진/동영상 다중 첨부**(최대 10개), 미리보기, 개별 삭제, 드래그&드롭 |
| 게시물 | 수정·삭제, 좋아요(AJAX), 댓글 작성·삭제(AJAX), 링크 복사, 라이트박스 갤러리 |
| 프로필 | 커버/프로필 사진 업로드, 소개, 탭(게시물·정보·친구·사진/동영상) |
| 친구 | 친구 요청 보내기/취소/수락/거절, 친구 끊기, 알 수도 있는 사람(함께 아는 친구 수 기준) |
| 알림 | 친구 요청·수락, 좋아요, 댓글 알림 / 읽지 않은 수 배지 / 드롭다운 / 모두 읽음 |
| 기타 | 사용자 검색, 설정(프로필·비밀번호), 다크 모드, 모바일 하단 탭 |

## 빠른 시작 (Windows 로컬 테스트 환경)

PHP·MySQL이 설치되어 있지 않아도 됩니다. 프로젝트 안의 `.runtime/` 폴더에 **포터블 PHP 8.3 + MySQL 8.4 LTS**를 받아서 실행합니다.
관리자 권한이 필요 없고, 시스템에 아무것도 설치하지 않습니다. 지우려면 `.runtime/` 폴더와 `config.local.php` 만 삭제하면 됩니다.

| 명령 | 설명 |
| --- | --- |
| `setup.bat` | 최초 1회: PHP·MySQL 다운로드(약 300MB) → `php.ini`/`my.ini` 생성 → DB 초기화 → 스키마·데모 데이터 입력 → `config.local.php` 생성 |
| `start.bat` | MySQL + PHP 내장 서버 실행 후 브라우저로 http://localhost:8000 열기 (Ctrl+C 로 둘 다 종료) |
| `stop.bat` | 백그라운드에 남은 PHP 서버 / MySQL 종료 |
| `reset-db.bat` | DB를 처음 상태(데모 데이터)로 되돌리고 업로드 파일 삭제 |
| `heidisql.bat` | HeidiSQL(포터블)로 DB 열기 — 접속 세션 `fb_clone (local)` 이 미리 설정되어 바로 테이블이 보임. MySQL 이 꺼져 있으면 자동 시작 |

```powershell
setup.bat                      # 옵션: -MySqlPort 3307  -NoSeed  -Force(재설치)
start.bat                      # 옵션: -Port 8080  -NoBrowser  -KeepDb(MySQL 유지)
```

- 데모 계정: `minjun@example.com` / `password` (그 외 `seoyeon@`, `jiho@`, `sua@`, `doyun@example.com`)
- MySQL 접속: `127.0.0.1:3306`, 사용자 `root`, 비밀번호 없음 (127.0.0.1 에서만 접속 가능, 로컬 개발 전용)
- 로그: `.runtime/logs/` (`mysql.err`, `php-error.log`)
- 업로드 한도: 파일당 100MB, 요청당 120MB, 최대 20개 (`.runtime/php/php.ini`)
- 프로젝트 경로에 **한글 등 영문이 아닌 문자**가 있어도 됩니다. MySQL 이 이런 경로에서 시작하지 못하기 때문에,
  스크립트가 `C:\ProgramData\fb-clone\rt-<해시>` 에 `.runtime` 을 가리키는 정션(폴더 바로가기)을 자동으로 만들어 그 경로로 실행합니다.
  파일은 그대로 프로젝트의 `.runtime/` 에 있습니다. 프로젝트를 지울 때는 이 정션 폴더도 함께 지우세요.

### VS Code 에서 실행

| 방법 | 동작 |
| --- | --- |
| **F5** ("Chrome에서 실행") | 서버 시작 → 실제 Chrome 창(최대화)으로 앱 열기, VS Code 디버거는 그 창에 연결. Shift+F5 로 멈추면 서버·MySQL·Chrome 창 종료 |
| **Ctrl+Shift+B** | "서버 시작" 작업 실행 후 Chrome 으로 열기 (Chrome 이 없으면 기본 브라우저) |
| **Ctrl+Shift+P → Tasks: Run Task** | 서버 종료 / DB 초기화 / 로컬 환경 설치 / PHP 문법 검사(현재 파일) |

- F5 로 열리는 Chrome 은 별도 프로필(`.runtime/chrome-debug-profile`)이라 JS 중단점(`assets/app.js`)을 걸 수 있습니다.
- F5 가 다른 동작을 하면 실행 및 디버그(Ctrl+Shift+D) 상단 목록에서 **"Chrome에서 실행"** 이 선택되어 있는지 확인하세요.
- "브라우저에 연결할 수 없음" 이 나면 9222 포트를 다른 프로그램이 쓰고 있는지 확인하세요.
- "서버 시작" 작업을 터미널 휴지통 아이콘으로 강제 종료하면 MySQL 이 남을 수 있으니 **"서버 종료" 작업**으로 정리하세요.
- 권장 확장: PHP Intelephense (자동 완성·정의 이동). `.vscode/extensions.json` 에 등록되어 있습니다.

### HeidiSQL 로 DB 보기

`heidisql.bat` 실행 (VS Code: Ctrl+Shift+P → Tasks: Run Task → **HeidiSQL 열기**)

- HeidiSQL 포터블은 `.runtime/heidisql/` 에 설치되며, 설정은 같은 폴더의 `portable_settings.txt` 에 저장됩니다.
- 왼쪽 트리에서 `fb_clone` → 테이블(`users`, `posts`, `post_media`, `post_likes`, `comments`, `friendships`, `notifications`) 클릭 → **데이터** 탭
- 앱에서 글을 쓴 뒤에는 HeidiSQL 에서 **F5** 를 눌러 새로고침하세요.
- 다 쓰고 나면 `stop.bat` 으로 MySQL 을 종료합니다. (HeidiSQL 을 먼저 닫는 것을 권장)

> DBeaver, MySQL Workbench 등 다른 GUI 도구도 위 접속 정보(127.0.0.1:3306 / root / 비밀번호 없음)로 접속할 수 있습니다.

## 직접 설치한 PHP/MySQL 사용 시

- PHP **8.1 이상** (확장: `pdo_mysql`, `mbstring`, `fileinfo` 권장)
- MySQL 5.7+ / MariaDB 10.3+

```bash
# 1) DB 생성 + 테이블 생성
mysql -u root -p < database/schema.sql

# 2) (선택) 데모 데이터 — 모든 계정 비밀번호: password
mysql -u root -p < database/seed.sql
```

3) `config.php` 에서 DB 접속 정보를 수정합니다. (또는 같은 형식의 `config.local.php` 를 만들어 필요한 값만 덮어쓰기)

4) 실행

```bash
# PHP 내장 서버 (업로드 용량 옵션 포함)
php -d upload_max_filesize=100M -d post_max_size=120M -d max_file_uploads=20 -S localhost:8000 router.php
```

브라우저에서 http://localhost:8000 접속 → 데모 계정 `minjun@example.com` / `password`

> XAMPP / Apache 사용 시 프로젝트 폴더를 `htdocs` 아래에 두면 됩니다. 하위 폴더 경로(`/fb-clone`)는 자동 감지되며,
> 필요하면 `config.php`의 `base_url` 로 직접 지정할 수 있습니다. 동영상 업로드를 위해 `php.ini` 의
> `upload_max_filesize`, `post_max_size` 를 늘려주세요.

## 폴더 구조

```
├── index.php            # 뉴스피드
├── login.php / register.php / logout.php
├── profile.php          # 프로필 (?id=, &tab=posts|about|friends|photos)
├── friends.php          # 친구 (?tab=requests|suggestions|all|sent)
├── notifications.php    # 알림 목록 (?open= 클릭 시 읽음 처리 후 이동)
├── post.php / post_edit.php
├── search.php / settings.php
├── actions/             # POST 처리 (게시물, 좋아요, 댓글, 친구, 알림, 프로필 사진)
├── includes/
│   ├── bootstrap.php    # 설정·세션·모듈 로드
│   ├── helpers.php      # DB, URL, CSRF, 인증, 플래시, 표시용 헬퍼
│   ├── users.php / friends.php / posts.php / notifications.php / uploads.php
│   └── partials/        # header(테마·네비), footer, post_card, composer, comment …
├── assets/app.js        # 드롭다운, 좋아요/댓글 AJAX, 업로드 미리보기, 라이트박스, 다크모드
├── setup.bat / start.bat / stop.bat / reset-db.bat
├── scripts/             # 위 .bat 이 실행하는 PowerShell 스크립트
├── .runtime/            # (setup 후 생성) 포터블 PHP·MySQL, DB 데이터, 로그
├── config.local.php     # (setup 후 생성) 로컬 DB 설정 — config.php 를 덮어씀
├── uploads/             # 업로드 파일 (스크립트 실행 차단 .htaccess)
└── database/schema.sql, seed.sql
```

## 보안 메모

- 모든 쿼리는 Prepared Statement 사용, 출력은 `e()` (htmlspecialchars) 로 이스케이프
- 모든 POST 요청에 CSRF 토큰 검증
- 업로드 파일은 **확장자가 아닌 실제 MIME**(`finfo`)으로 검사, 무작위 파일명으로 저장
- 게시물 조회 시 공개 범위(전체/친구/나만 보기) 권한 검사

> Tailwind Play CDN 은 개발용입니다. 운영 배포 시에는 Tailwind CLI 로 CSS 를 빌드해 사용하세요.
