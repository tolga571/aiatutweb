<?php
namespace App\Src;

class Chat {
    private Database $db;
    private TokenManager $tokenManager;

    private const CEFR_GUIDE = [
        'A1' => 'very simple words, 3-5 word sentences, ultra-basic vocabulary, present tense only',
        'A2' => 'simple sentences, basic vocabulary, present and past tense, common expressions',
        'B1' => 'everyday language, varied vocabulary, multiple tenses, can introduce idioms',
        'B2' => 'clear complex language, wide vocabulary, natural expressions, phrasal verbs welcome',
        'C1' => 'sophisticated language, advanced idioms, nuanced grammar, academic or professional style',
        'C2' => 'near-native mastery, any register, subtle nuance, complex structures',
    ];

    private const TOPICS = [
        'cafe'       => ['en' => 'Order coffee at a cafe',         'label' => 'Cafe order'],
        'hotel'      => ['en' => 'Check-in at a hotel',            'label' => 'Hotel check-in'],
        'interview'  => ['en' => 'Introduce yourself in a job interview','label' => 'Job interview'],
        'daily'      => ['en' => 'Chat about daily life and plans', 'label' => 'Daily chat'],
        'smalltalk'  => ['en' => 'Talk about weather and hobbies',  'label' => 'Small talk'],
    ];

    public function __construct(Database $db, array $config) {
        $this->db = $db;
        $this->tokenManager = new TokenManager($db);
    }

    public function getTopics(?string $interest = null): array {
        $topics = self::TOPICS;
        if ($interest === 'sports') {
            $topics['sports_match'] = ['en' => 'Discuss a recent sports match', 'label' => 'Sports Match'];
            $topics['sports_gym'] = ['en' => 'Talk about going to the gym', 'label' => 'Gym Workout'];
        } elseif ($interest === 'tech') {
            $topics['tech_gadget'] = ['en' => 'Talk about the latest tech gadgets', 'label' => 'Tech Gadgets'];
            $topics['tech_code'] = ['en' => 'Discuss coding and software development', 'label' => 'Software Dev'];
        } elseif ($interest === 'movies') {
            $topics['movie_review'] = ['en' => 'Review a movie you recently watched', 'label' => 'Movie Review'];
            $topics['movie_cinema'] = ['en' => 'Buy tickets at the cinema', 'label' => 'Cinema Tickets'];
        } elseif ($interest === 'business') {
            $topics['biz_meeting'] = ['en' => 'Participate in a business meeting', 'label' => 'Business Meeting'];
            $topics['biz_negotiation'] = ['en' => 'Negotiate a contract', 'label' => 'Negotiation'];
        }
        return $topics;
    }

    private function buildSystemPrompt(string $targetLang, string $nativeLang, string $cefrLevel, ?string $topicId, array $knownWords, array $recentMistakes, string $goal = 'conversation', string $interest = 'general'): string {
        $cefrHint = self::CEFR_GUIDE[$cefrLevel] ?? self::CEFR_GUIDE['A1'];

        $topicBlock = '';
        if ($topicId && isset(self::TOPICS[$topicId])) {
            $t = self::TOPICS[$topicId];
            $instruction = $t['en'];
            $topicBlock = "\n\nCONVERSATION CONTEXT:\n- {$instruction}\n- Keep the conversation naturally within this scenario.";
        }

        $memoryBlock = '';
        if (!empty($knownWords)) {
            $words = implode(', ', array_slice($knownWords, 0, 15));
            $memoryBlock .= "\n\nUSER MEMORY:\n- Recently learned words: {$words}";
        }
        if (!empty($recentMistakes)) {
            $mistakes = implode('; ', array_slice($recentMistakes, 0, 5));
            $memoryBlock .= "\n- Recent grammar mistakes to watch: {$mistakes}";
        }

        return "You are an enthusiastic, encouraging language tutor helping a student learn {$targetLang}.
Their native language is {$nativeLang} and their current proficiency is CEFR level {$cefrLevel} ({$cefrHint}).{$topicBlock}{$memoryBlock}
User's learning goal: {$goal}. Their interests: {$interest}. Tailor your vocabulary and examples accordingly.

PERSONALITY:
- Be warm, friendly, and motivating
- Ask one engaging follow-up question when doing open conversation practice
- Celebrate effort and small wins

RESPONSE FORMAT:
You MUST return ONLY a valid JSON object. No markdown fences, no extra text:
{
  \"content\": \"<your full reply entirely in {$targetLang}, 2-4 sentences at CEFR level>\",
  \"phonetic\": \"<romanization or IPA of content; required for non-Latin scripts (ar, zh, ja), optional IPA for Latin scripts>\",
  \"literal_translation\": \"<word-by-word or phrase-by-phrase translation in {$nativeLang}, showing structure>\",
  \"translation\": \"<natural, fluent {$nativeLang} translation of content>\",
  \"grammar_spotlight\": \"<2-4 sentences in {$nativeLang} explaining ONE key grammar point. Every {$targetLang} word/phrase MUST appear as **word (pronunciation)**>\",
  \"pro_tip\": \"<2-3 sentences in {$nativeLang} about cultural context. Every {$targetLang} word/phrase MUST appear as **word (pronunciation)**>\",
  \"correction\": \"<plain-text summary of corrections, or empty string if no mistake>\",
  \"corrections\": [
    { \"original\": \"<exact mistake>\", \"corrected\": \"<corrected form in {$targetLang}>\", \"pronunciation\": \"<how to pronounce corrected form>\", \"rule\": \"<short rule in {$nativeLang}; any {$targetLang} terms as word (pronunciation)>\" }
  ],
  \"words\": [
    { \"word\": \"<key {$targetLang} word>\", \"pronunciation\": \"<romanization or IPA — REQUIRED>\", \"definition\": \"<brief definition in {$nativeLang}>\" }
  ]
}

SCOPE: Language learning only. Refuse off-topic requests warmly in JSON format.

CONTENT RULES:
- Write a substantive reply in {$targetLang} at CEFR {$cefrLevel} level (never one-liners)
- End with one follow-up question during open conversation
- phonetic: full-sentence romanization/IPA of content (REQUIRED for ar, zh, ja; recommended IPA for en, de, fr, es)
- literal_translation: show how the sentence is built; when quoting {$targetLang} fragments, add (pronunciation) after each fragment
- grammar_spotlight: pick the most teachable grammar from YOUR reply and explain clearly
- pro_tip: give a practical cultural or usage tip related to the scenario

PRONUNCIATION RULES (MANDATORY):
- NEVER show a {$targetLang} word or phrase without its pronunciation in parentheses immediately after it
- Use standard romanization: pinyin (tone marks) for zh, Hepburn romaji for ja, DIN/standard transliteration for ar, IPA or simple phonetic spelling for en/de/fr/es
- In grammar_spotlight and pro_tip: format every {$targetLang} term as **word (pronunciation)** inside the ** markers
- In words array: pronunciation field is REQUIRED for every entry — never leave it empty
- In corrections: pronunciation field is REQUIRED whenever corrected contains {$targetLang} text

CORRECTION RULES:
- Only correct clear grammar or spelling mistakes in the user's message
- If no mistake, set \"correction\" to \"\" and \"corrections\" to []

WORDS RULES:
- Extract 2-4 useful vocabulary words from your reply
- Each word: single word or short phrase, definition 3-8 words in {$nativeLang}
- pronunciation is REQUIRED — never omit it";
    }

    public function handleMessage(int $userId, string $message, GeminiClient $gemini, ?int $conversationId = null, ?string $topicId = null): array {
        $remaining = $this->tokenManager->getRemaining($userId);
        if ($this->tokenManager->getRemaining($userId) <= 0) {
            return ['error' => 'Monthly message limit reached. Check your plan limits!'];
        }

        $user = $this->db->fetchOne('SELECT * FROM users WHERE id = ?', [$userId]);
        if (!$user) {
            return ['error' => 'User not found.'];
        }

        $targetLang  = $user['target_lang']  ?? 'en';
        $nativeLang  = $user['native_lang']  ?? 'en';
        $cefrLevel   = $user['cefr_level']   ?? 'A1';
        $goal        = $user['learning_goal'] ?? 'conversation';
        $interest    = $user['interest_area'] ?? 'general';

        // Load conversation history (last 20 messages)
        $history = [];
        if ($conversationId) {
            $conv = $this->db->fetchOne('SELECT id FROM conversations WHERE id = ? AND user_id = ?', [$conversationId, $userId]);
            if ($conv) {
                $history = $this->db->fetchAll(
                    'SELECT role, content, translation FROM messages WHERE conversation_id = ? ORDER BY created_at ASC LIMIT 20',
                    [$conversationId]
                );
            }
        }

        // Known words context
        $knownWordRows = $this->db->fetchAll(
            'SELECT word FROM vocabulary_words WHERE user_id = ? AND language = ? ORDER BY created_at DESC LIMIT 20',
            [$userId, $targetLang]
        );
        $knownWords = array_column($knownWordRows, 'word');

        // Recent mistakes context
        $mistakeRows = $this->db->fetchAll(
              "SELECT correction FROM messages
              WHERE conversation_id IN (SELECT id FROM conversations WHERE user_id = ?)
              AND role = 'ai' AND correction != '' AND correction IS NOT NULL
              ORDER BY created_at DESC LIMIT 8",
            [$userId]
        );
        $recentMistakes = array_filter(array_column($mistakeRows, 'correction'));

        $systemPrompt = $this->buildSystemPrompt($targetLang, $nativeLang, $cefrLevel, $topicId, $knownWords, array_values($recentMistakes), $goal, $interest);

        // Persist conversation + user message BEFORE AI call so conversations always save
        if (!$conversationId) {
            $this->db->execute(
                'INSERT INTO conversations (user_id, topic_id) VALUES (?, ?)',
                [$userId, $topicId]
            );
            $conversationId = $this->db->lastInsertId('conversations');
        } else {
            $this->db->execute('UPDATE conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$conversationId]);
        }

        $this->db->execute(
            "INSERT INTO messages (conversation_id, role, content) VALUES (?, 'user', ?)",
            [$conversationId, $message]
        );

        $content = '';
        $translation = '';
        $correction = '';
        $corrections = [];
        $words = [];
        $phonetic = '';
        $literalTranslation = '';
        $grammarSpotlight = '';
        $proTip = '';

        try {
            $aiRaw = $gemini->chatWithHistory($message, $history, $systemPrompt, $targetLang);
            $parsed = json_decode($aiRaw, true);
            if ($parsed) {
                $content             = $parsed['content']             ?? $aiRaw;
                $phonetic            = $parsed['phonetic']            ?? '';
                $literalTranslation  = $parsed['literal_translation'] ?? '';
                $translation         = $parsed['translation']         ?? '';
                $grammarSpotlight    = $parsed['grammar_spotlight']   ?? '';
                $proTip              = $parsed['pro_tip']             ?? '';
                $correction          = $parsed['correction']          ?? '';
                $corrections         = is_array($parsed['corrections'] ?? null) ? $parsed['corrections'] : [];
                $words               = is_array($parsed['words'] ?? null) ? $parsed['words'] : [];
            } else {
                $content = $aiRaw;
            }
            $aiSaved = true;
        } catch (\Throwable $e) {
            $content = __('chat.error_ai_unavailable');
            $aiSaved = false;
        }

        $metadata = json_encode([
            'phonetic'            => $phonetic,
            'literal_translation' => $literalTranslation,
            'grammar_spotlight'   => $grammarSpotlight,
            'pro_tip'             => $proTip,
            'corrections'         => $corrections,
            'words'               => $words,
        ], JSON_UNESCAPED_UNICODE);

        $this->db->execute(
            "INSERT INTO messages (conversation_id, role, content, translation, correction, metadata) VALUES (?, 'ai', ?, ?, ?, ?)",
            [$conversationId, $content, $translation, $correction, $metadata]
        );

        // Save new vocabulary words and create flashcards
        foreach ($words as $w) {
            if (!empty($w['word']) && !empty($w['definition'])) {
                $exists = $this->db->fetchOne(
                    'SELECT id FROM vocabulary_words WHERE user_id = ? AND word = ? AND language = ?',
                    [$userId, $w['word'], $targetLang]
                );
                if (!$exists) {
                    $this->db->execute(
                        'INSERT INTO vocabulary_words (user_id, word, translation, pronunciation, example, category, language, source) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                        [$userId, $w['word'], $w['definition'], $w['pronunciation'] ?? '', $content, 'chat', $targetLang, 'chat']
                    );
                    $vocabId = $this->db->lastInsertId('vocabulary_words');
                    
                    $this->db->insertIgnore('user_flashcards', ['user_id', 'vocab_id'], [$userId, $vocabId]);
                }
            }
        }

        // XP + message usage
        $this->db->execute('UPDATE users SET xp = xp + 10 WHERE id = ?', [$userId]);
        $this->tokenManager->addUsage($userId, 1);

        // Compute remaining quota after usage
        $quotaRemaining = $this->tokenManager->getRemaining($userId);
        $planStatus = $user['plan_status'] ?? 'inactive';
        $quotaTotal = $this->tokenManager->getBaseLimit($planStatus);
        // Add bonus to total for display
        $usage = $this->db->fetchOne('SELECT bonus_limit FROM token_usage WHERE user_id = ?', [$userId]);
        $quotaTotal += ($usage ? (int)$usage['bonus_limit'] : 0);

        return [
            'content'             => $content,
            'phonetic'            => $phonetic,
            'literal_translation' => $literalTranslation,
            'translation'         => $translation,
            'grammar_spotlight'   => $grammarSpotlight,
            'pro_tip'             => $proTip,
            'correction'          => $correction,
            'corrections'         => $corrections,
            'words'               => $words,
            'conversationId'      => $conversationId,
            'xpAwarded'           => 10,
            'quotaRemaining'      => $quotaRemaining,
            'quotaTotal'          => $quotaTotal,
        ];
    }

    private function enrichMessage(array $msg): array {
        if (($msg['role'] ?? '') !== 'ai' || empty($msg['metadata'])) {
            return $msg;
        }
        $meta = json_decode($msg['metadata'], true);
        if (!is_array($meta)) {
            return $msg;
        }
        foreach (['phonetic', 'literal_translation', 'grammar_spotlight', 'pro_tip'] as $key) {
            if (!empty($meta[$key])) {
                $msg[$key] = $meta[$key];
            }
        }
        if (!empty($meta['corrections']) && is_array($meta['corrections'])) {
            $msg['corrections'] = $meta['corrections'];
        }
        if (!empty($meta['words']) && is_array($meta['words'])) {
            $msg['words'] = $meta['words'];
        }
        return $msg;
    }

    public function getConversations(int $userId, int $limit = 30): array {
        return $this->db->fetchAll(
            'SELECT c.id, c.topic_id, c.updated_at,
                    (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY created_at ASC LIMIT 1) AS first_message
             FROM conversations c WHERE c.user_id = ? ORDER BY c.updated_at DESC LIMIT ?',
            [$userId, $limit]
        );
    }

    public function getMessages(int $userId, int $conversationId): array {
        $conv = $this->db->fetchOne('SELECT id FROM conversations WHERE id = ? AND user_id = ?', [$conversationId, $userId]);
        if (!$conv) return [];
        $rows = $this->db->fetchAll(
            'SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at ASC',
            [$conversationId]
        );
        return array_map(fn($row) => $this->enrichMessage($row), $rows);
    }
}
?>
