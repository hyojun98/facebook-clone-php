-- ============================================================================
-- Facebook Clone 스키마 (MySQL 5.7+ / MariaDB 10.3+)
-- ============================================================================
--
-- 테이블 관계
--   users ─┬─< posts ─┬─< post_media     (게시물 첨부 사진/동영상)
--          │          ├─< post_likes     (좋아요: 게시물 x 사용자, 1인 1회)
--          │          └─< comments       (댓글)
--          ├─< friendships               (친구 관계: requester → addressee, pending/accepted)
--          └─< notifications             (알림: actor 가 user 에게 type 행동)
--
-- 모든 외래키는 ON DELETE CASCADE:
--   사용자를 지우면 그 사람의 게시물/댓글/좋아요/친구관계/알림이,
--   게시물을 지우면 첨부/좋아요/댓글/관련 알림이 함께 삭제된다.
--
-- 문자셋 utf8mb4: 한글과 이모지(😀)까지 저장 가능
-- 주의: 이 스크립트는 기존 테이블을 DROP 하고 다시 만든다. (reset-db.bat 이 사용)
CREATE DATABASE IF NOT EXISTS fb_clone CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fb_clone;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS notifications, comments, post_likes, post_media, posts, friendships, users;
SET FOREIGN_KEY_CHECKS = 1;

-- 회원. 비밀번호는 password_hash() 의 bcrypt 해시로만 저장. avatar/cover 는 uploads/ 상대 경로.
CREATE TABLE users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name    VARCHAR(50)  NOT NULL,
    last_name     VARCHAR(50)  NOT NULL,
    email         VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    gender        ENUM('female','male','other') NULL,
    birthday      DATE NULL,
    bio           VARCHAR(255) NULL,
    location      VARCHAR(100) NULL,
    workplace     VARCHAR(100) NULL,
    education     VARCHAR(100) NULL,
    avatar        VARCHAR(255) NULL,
    cover         VARCHAR(255) NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 친구 관계. 두 사람 사이에 한 행만 존재 (방향: requester 가 요청을 보낸 사람).
-- 요청 = pending 행 INSERT, 수락 = accepted 로 UPDATE, 거절/취소/친구 끊기 = DELETE
CREATE TABLE friendships (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requester_id INT UNSIGNED NOT NULL,
    addressee_id INT UNSIGNED NOT NULL,
    status       ENUM('pending','accepted') NOT NULL DEFAULT 'pending',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pair (requester_id, addressee_id),
    KEY idx_addressee (addressee_id, status),
    CONSTRAINT fk_fr_requester FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_fr_addressee FOREIGN KEY (addressee_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 게시물. content 는 사진만 올린 경우 NULL 일 수 있다.
-- privacy: public(전체 공개) / friends(친구만) / only_me(나만 보기)
CREATE TABLE posts (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    content    TEXT NULL,
    privacy    ENUM('public','friends','only_me') NOT NULL DEFAULT 'friends',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_user_created (user_id, created_at),
    CONSTRAINT fk_posts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 게시물 첨부 파일. sort_order 는 사용자가 고른 순서 (0부터)
CREATE TABLE post_media (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id    INT UNSIGNED NOT NULL,
    file_path  VARCHAR(255) NOT NULL,
    media_type ENUM('image','video') NOT NULL,
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
    KEY idx_post (post_id),
    CONSTRAINT fk_media_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 좋아요. (post_id, user_id) 복합 기본키 → 같은 사람이 같은 게시물에 두 번 누를 수 없음
CREATE TABLE post_likes (
    post_id    INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (post_id, user_id),
    CONSTRAINT fk_likes_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_likes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 댓글
CREATE TABLE comments (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id    INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    content    TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_post_created (post_id, created_at),
    CONSTRAINT fk_comments_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 알림. type 별 의미는 includes/notifications.php 상단 표 참고. ('post' 는 예비 값으로 현재 미사용)
CREATE TABLE notifications (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,           -- 알림 받는 사람
    actor_id   INT UNSIGNED NOT NULL,           -- 알림을 발생시킨 사람
    type       ENUM('friend_request','friend_accept','like','comment','post') NOT NULL,
    post_id    INT UNSIGNED NULL,
    is_read    TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user_read (user_id, is_read, created_at),
    CONSTRAINT fk_noti_user  FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_noti_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_noti_post  FOREIGN KEY (post_id)  REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
