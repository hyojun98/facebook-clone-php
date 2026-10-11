-- ============================================================================
-- 데모 데이터 (모든 계정 비밀번호: password)
-- ============================================================================
--
-- 사용자 5명과 관계
--   1 김민준 ─ 친구 ─ 2 이서연 ─ 친구 ─ 3 박지호 ─ 친구 ─ 1 김민준   (세 사람이 서로 친구)
--   2 이서연 ─ 친구 ─ 4 최수아
--   4 최수아 → 1 김민준 : 친구 요청 대기 중 (민준으로 로그인하면 "친구 요청" 1건)
--   5 정도윤 → 2 이서연 : 친구 요청 대기 중
--
-- password_hash 값은 'password' 를 bcrypt 로 해시한 값이다.
-- 첨부 파일이 있는 게시물은 없다. (업로드 파일은 직접 올려 보면 uploads/ 에 생김)
USE fb_clone;

INSERT INTO users (id, first_name, last_name, email, password_hash, gender, bio, location, workplace, education, created_at) VALUES
(1, '민준', '김', 'minjun@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'male',   '커피와 코드를 좋아하는 개발자 ☕', '서울', '스타트업', '서울대학교', NOW() - INTERVAL 30 DAY),
(2, '서연', '이', 'seoyeon@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'female', '여행 / 사진 / 고양이 🐈', '부산', '디자인 스튜디오', '홍익대학교', NOW() - INTERVAL 28 DAY),
(3, '지호', '박', 'jiho@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'male',   '주말엔 등산 ⛰️', '대전', NULL, 'KAIST', NOW() - INTERVAL 20 DAY),
(4, '수아', '최', 'sua@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'female', '맛집 탐방러 🍜', '서울', '마케팅 에이전시', NULL, NOW() - INTERVAL 15 DAY),
(5, '도윤', '정', 'doyun@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'male',   NULL, '인천', NULL, NULL, NOW() - INTERVAL 5 DAY);

INSERT INTO friendships (requester_id, addressee_id, status) VALUES
(1, 2, 'accepted'),
(1, 3, 'accepted'),
(2, 3, 'accepted'),
(4, 1, 'pending'),
(2, 4, 'accepted'),
(5, 2, 'pending');

INSERT INTO posts (id, user_id, content, privacy, created_at) VALUES
(1, 2, '부산 바다 다녀왔어요! 날씨가 정말 좋았네요 🌊', 'public', NOW() - INTERVAL 3 HOUR),
(2, 3, '오늘 북한산 정상 찍고 왔습니다. 다음엔 같이 가요!', 'friends', NOW() - INTERVAL 1 DAY),
(3, 1, 'Core PHP로 페이스북 클론 만들기 시작! 🚀', 'public', NOW() - INTERVAL 2 DAY),
(4, 4, '을지로 숨은 맛집 발견 🍜 다음 주에 같이 갈 사람?', 'public', NOW() - INTERVAL 4 HOUR);

INSERT INTO post_likes (post_id, user_id) VALUES (1, 1), (1, 3), (2, 1), (3, 2), (3, 3);

INSERT INTO comments (post_id, user_id, content, created_at) VALUES
(1, 1, '와 사진 너무 예쁘다!', NOW() - INTERVAL 2 HOUR),
(1, 3, '부럽다 ㅠㅠ', NOW() - INTERVAL 1 HOUR),
(3, 2, '화이팅! 완성되면 보여줘 😆', NOW() - INTERVAL 1 DAY);

INSERT INTO notifications (user_id, actor_id, type, post_id, is_read, created_at) VALUES
(1, 4, 'friend_request', NULL, 0, NOW() - INTERVAL 5 HOUR),
(1, 2, 'like', 3, 0, NOW() - INTERVAL 1 DAY),
(1, 2, 'comment', 3, 1, NOW() - INTERVAL 1 DAY),
(2, 1, 'like', 1, 0, NOW() - INTERVAL 2 HOUR),
(2, 1, 'comment', 1, 0, NOW() - INTERVAL 2 HOUR);
