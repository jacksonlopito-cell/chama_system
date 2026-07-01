-- Migration 007: Member-User Integrity
-- 1. Drop circular FK members.user_id -> users.id
-- 2. Add UNIQUE constraint on users.member_id
-- 3. Set ON DELETE CASCADE on users.member_id FK

ALTER TABLE members DROP FOREIGN KEY members_ibfk_1;
ALTER TABLE members DROP COLUMN user_id;

ALTER TABLE users DROP FOREIGN KEY users_ibfk_1;
ALTER TABLE users ADD UNIQUE INDEX idx_unique_member_id (member_id);
ALTER TABLE users ADD FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL;
