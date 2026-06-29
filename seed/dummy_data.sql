-- Test user: test@example.com / password123
INSERT OR IGNORE INTO users (email, password, name, native_lang, target_lang, cefr_level, learning_goal, interest_area, plan_status, has_paid, onboarding_completed, xp)
VALUES ('test@example.com', '$2y$10$ylQ3UqMNzvToY5EOmnBTyOwsyfVUFKXWzeJLuMOnTkgXOWRRdahaO', 'Test User', 'tr', 'en', 'B1', 'conversation', 'general', 'active', 1, 1, 120);

-- Sample blog posts
INSERT OR IGNORE INTO posts (title, slug, content, category, published) VALUES
('Welcome to AiTut', 'welcome-to-aitut', 'Welcome to AiTut – your AI-powered language tutor! This platform helps you practice real conversations, get instant grammar corrections, and build your vocabulary.', 'blog', 1),
('How to use CEFR levels', 'cefr-guide', 'CEFR levels range from A1 (beginner) to C2 (mastery). The AI adapts vocabulary and grammar complexity based on your selected level.', 'blog', 1),
('5 tips for faster vocabulary retention', 'vocabulary-retention-tips', '1. Use new words in sentences immediately. 2. Review words in context. 3. Space your repetition. 4. Connect words to images. 5. Teach the word to someone else.', 'blog', 1);

-- Token usage for test user
INSERT OR IGNORE INTO token_usage (user_id, used_today, last_reset) VALUES (1, 0, DATE('now'));
