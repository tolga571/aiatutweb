<?php $pageTitle = __('tips.page_title'); ?>
<?php require __DIR__ . '/../partials/head.php'; ?>
<?php require __DIR__ . '/../partials/navbar.php'; ?>

<main class="flex-1 overflow-y-auto flex flex-col justify-between bg-radial-gradient">
  <div class="py-12 px-6 max-w-3xl mx-auto w-full">
    <div class="glass-panel rounded-2xl p-8 md:p-10 border border-outline-variant/20 shadow-2xl backdrop-blur-md">

      <!-- Header -->
      <div class="flex items-center gap-4 mb-8">
        <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center border border-primary/20">
          <span class="material-symbols-outlined text-primary text-3xl">school</span>
        </div>
        <div>
          <h1 class="font-headline-lg text-headline-lg text-on-surface"><?= __('tips.heading') ?></h1>
          <p class="text-body-md text-on-surface-variant"><?= __('tips.subtitle') ?></p>
        </div>
      </div>

      <div class="space-y-6">

        <!-- 1. Who is Kai -->
        <section class="bg-surface-container-low/40 border border-outline-variant/10 rounded-xl p-5">
          <div class="flex items-start gap-3">
            <span class="material-symbols-outlined text-primary text-2xl shrink-0 mt-0.5">psychology</span>
            <div>
              <h2 class="font-headline-md text-headline-sm text-primary mb-2"><?= __('tips.section_1_title') ?></h2>
              <p class="text-body-md text-on-surface-variant leading-relaxed">
                <?= __('tips.section_1_body') ?>
              </p>
            </div>
          </div>
        </section>

        <!-- 2. How to write messages -->
        <section class="bg-surface-container-low/40 border border-outline-variant/10 rounded-xl p-5">
          <div class="flex items-start gap-3">
            <span class="material-symbols-outlined text-primary text-2xl shrink-0 mt-0.5">edit_note</span>
            <div>
              <h2 class="font-headline-md text-headline-sm text-primary mb-2"><?= __('tips.section_2_title') ?></h2>
              <ul class="text-body-md text-on-surface-variant leading-relaxed space-y-2 list-disc list-inside">
                <li><?= __('tips.section_2_tip_1') ?></li>
                <li><?= __('tips.section_2_tip_2') ?></li>
                <li><?= __('tips.section_2_tip_3') ?></li>
                <li><?= __('tips.section_2_tip_4') ?></li>
                <li><?= __('tips.section_2_tip_5') ?></li>
              </ul>
            </div>
          </div>
        </section>

        <!-- 3. Conversation topics -->
        <section class="bg-surface-container-low/40 border border-outline-variant/10 rounded-xl p-5">
          <div class="flex items-start gap-3">
            <span class="material-symbols-outlined text-primary text-2xl shrink-0 mt-0.5">forum</span>
            <div>
              <h2 class="font-headline-md text-headline-sm text-primary mb-2"><?= __('tips.section_3_title') ?></h2>
              <p class="text-body-md text-on-surface-variant mb-3">
                <?= __('tips.section_3_body') ?>
              </p>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="bg-surface-container-high/40 border border-outline-variant/20 rounded-lg p-3">
                  <span class="font-bold text-xs text-on-surface block mb-1"><?= __('tips.topic_cafe') ?></span>
                  <span class="text-[11px] text-on-surface-variant"><?= __('tips.topic_cafe_desc') ?></span>
                </div>
                <div class="bg-surface-container-high/40 border border-outline-variant/20 rounded-lg p-3">
                  <span class="font-bold text-xs text-on-surface block mb-1"><?= __('tips.topic_hotel') ?></span>
                  <span class="text-[11px] text-on-surface-variant"><?= __('tips.topic_hotel_desc') ?></span>
                </div>
                <div class="bg-surface-container-high/40 border border-outline-variant/20 rounded-lg p-3">
                  <span class="font-bold text-xs text-on-surface block mb-1"><?= __('tips.topic_interview') ?></span>
                  <span class="text-[11px] text-on-surface-variant"><?= __('tips.topic_interview_desc') ?></span>
                </div>
                <div class="bg-surface-container-high/40 border border-outline-variant/20 rounded-lg p-3">
                  <span class="font-bold text-xs text-on-surface block mb-1"><?= __('tips.topic_daily') ?></span>
                  <span class="text-[11px] text-on-surface-variant"><?= __('tips.topic_daily_desc') ?></span>
                </div>
                <div class="bg-surface-container-high/40 border border-outline-variant/20 rounded-lg p-3">
                  <span class="font-bold text-xs text-on-surface block mb-1"><?= __('tips.topic_smalltalk') ?></span>
                  <span class="text-[11px] text-on-surface-variant"><?= __('tips.topic_smalltalk_desc') ?></span>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- 4. What you get in every reply -->
        <section class="bg-surface-container-low/40 border border-outline-variant/10 rounded-xl p-5">
          <div class="flex items-start gap-3">
            <span class="material-symbols-outlined text-primary text-2xl shrink-0 mt-0.5">overview</span>
            <div>
              <h2 class="font-headline-md text-headline-sm text-primary mb-2"><?= __('tips.section_4_title', '4. What you get in every reply') ?></h2>
              <div class="space-y-3">
                <div class="flex items-start gap-3 bg-surface-container-high/30 rounded-lg p-3 border border-outline-variant/10">
                  <span class="material-symbols-outlined text-[18px] text-primary shrink-0 mt-0.5">textsms</span>
                  <div>
                    <span class="text-xs font-bold text-on-surface block"><?= __('tips.reply_main_text', 'Main text') ?></span>
                    <span class="text-[11px] text-on-surface-variant"><?= __('tips.reply_main_text_desc', "Kai's reply in your target language, ending with a follow-up question.") ?></span>
                  </div>
                </div>
                <div class="flex items-start gap-3 bg-surface-container-high/30 rounded-lg p-3 border border-outline-variant/10">
                  <span class="material-symbols-outlined text-[18px] text-primary shrink-0 mt-0.5">translate</span>
                  <div>
                    <span class="text-xs font-bold text-on-surface block"><?= __('tips.reply_translation', 'Translation') ?></span>
                    <span class="text-[11px] text-on-surface-variant"><?= __('tips.reply_translation_desc', 'Full translation in your native language, shown below the main text.') ?></span>
                  </div>
                </div>
                <div class="flex items-start gap-3 bg-surface-container-high/30 rounded-lg p-3 border border-outline-variant/10">
                  <span class="material-symbols-outlined text-[18px] text-primary shrink-0 mt-0.5">spellcheck</span>
                  <div>
                    <span class="text-xs font-bold text-on-surface block"><?= __('tips.reply_corrections', 'Corrections') ?></span>
                    <span class="text-[11px] text-on-surface-variant"><?= __('tips.reply_corrections_desc', 'If you made a mistake, Kai shows the correction with a grammar rule.') ?></span>
                  </div>
                </div>
                <div class="flex items-start gap-3 bg-surface-container-high/30 rounded-lg p-3 border border-outline-variant/10">
                  <span class="material-symbols-outlined text-[18px] text-primary shrink-0 mt-0.5">dictionary</span>
                  <div>
                    <span class="text-xs font-bold text-on-surface block"><?= __('tips.reply_vocabulary', 'Vocabulary') ?></span>
                    <span class="text-[11px] text-on-surface-variant"><?= __('tips.reply_vocabulary_desc', '2-4 useful words from the reply with definitions, saved to your vocabulary list.') ?></span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- 5. Tips for better learning -->
        <section class="bg-surface-container-low/40 border border-outline-variant/10 rounded-xl p-5">
          <div class="flex items-start gap-3">
            <span class="material-symbols-outlined text-primary text-2xl shrink-0 mt-0.5">lightbulb</span>
            <div>
              <h2 class="font-headline-md text-headline-sm text-primary mb-2"><?= __('tips.section_5_title', '5. Tips for better learning') ?></h2>
              <ul class="text-body-md text-on-surface-variant leading-relaxed space-y-2 list-disc list-inside">
                <li><?= __('tips.tip_replay_topic', 'Replay the same topic — you\'ll see improvement each time.') ?></li>
                <li><?= __('tips.tip_answer_followup', 'Always answer Kai\'s follow-up questions to keep the conversation flowing.') ?></li>
                <li><?= __('tips.tip_try_new_words', 'Try words you don\'t know yet; Kai will explain them.') ?></li>
                <li><?= __('tips.tip_review_vocab', 'Review your saved vocabulary in the right panel.') ?></li>
                <li><?= __('tips.tip_change_level', 'Change your CEFR level from the Dashboard when you feel ready.') ?></li>
              </ul>
            </div>
          </div>
        </section>

        <!-- 6. What Kai won't do -->
        <section class="bg-surface-container-low/40 border border-outline-variant/10 rounded-xl p-5 border-l-4 border-l-tertiary">
          <div class="flex items-start gap-3">
            <span class="material-symbols-outlined text-tertiary text-2xl shrink-0 mt-0.5">info</span>
            <div>
              <h2 class="font-headline-md text-headline-sm text-tertiary mb-2"><?= __('tips.section_6_title', "6. What Kai won't do") ?></h2>
              <ul class="text-body-md text-on-surface-variant leading-relaxed space-y-2 list-disc list-inside">
                <li><?= __('tips.limit_tutor_only', 'Kai is a <strong class="text-on-surface">language tutor</strong>, not a general assistant. Off-topic questions will be politely refused.') ?></li>
                <li><?= __('tips.limit_daily_limit', 'There is a <strong class="text-on-surface">daily message limit</strong> (shown in your account). It resets every day.') ?></li>
                <li><?= __('tips.limit_no_code', 'Kai won\'t write essays or code for you — the focus is always language practice.') ?></li>
              </ul>
            </div>
          </div>
        </section>

      </div>

      <div class="mt-8 text-center">
        <a href="?page=chat" class="inline-flex items-center gap-2 bg-primary hover:bg-primary/90 text-on-primary font-semibold px-6 py-3 rounded-xl transition-all shadow-md">
          <span class="material-symbols-outlined text-[18px]">forum</span>
          <?= __('tips.start_practicing', 'Start Practicing') ?>
        </a>
      </div>

    </div>
  </div>
  <?php require __DIR__ . '/../partials/footer.php'; ?>
</main>
</body>
</html>
