<?php
require_once __DIR__ . '/partials/flags.php';
$pageTitle = __('chat.page_title');
$currentUser = $currentUser ?? $auth->currentUser();
$isTrial = (($currentUser['plan_status'] ?? '') === 'trial');
$trialLimit = 5;
$trialMessagesSent = 0;
if ($isTrial) {
  $trialMessagesSent = $auth->getTrialMessagesSent((int) $currentUser['id']);
}
$isTrialExpired = ($isTrial && $trialMessagesSent >= $trialLimit);
$xp = (int) ($currentUser['xp'] ?? 0);
$level = max(1, (int) floor($xp / 100) + 1);
$xpInLevel = $xp % 100;
$targetFlag = flagImg($currentUser['target_lang'] ?? 'en', 'w-6 h-4');
$targetLang = strtolower($currentUser['target_lang'] ?? 'en');
$topics = $chat->getTopics($currentUser['interest_area'] ?? null);
$langNames = ['en' => 'English', 'de' => 'German', 'fr' => 'French', 'es' => 'Spanish', 'zh' => 'Chinese', 'ja' => 'Japanese', 'ar' => 'Arabic', 'tr' => 'Turkish'];
$targetLangName = $langNames[$targetLang] ?? strtoupper($targetLang);
$activeConvId = isset($_GET['conv_id']) ? (int) $_GET['conv_id'] : null;
$userInitial = strtoupper(substr($currentUser['name'] ?? $currentUser['email'] ?? 'U', 0, 1));

$topicDescriptions = [
  'cafe' => __('chat.topic_cafe'),
  'hotel' => __('chat.topic_hotel'),
  'interview' => __('chat.topic_interview'),
  'daily' => __('chat.topic_daily'),
  'smalltalk' => __('chat.topic_smalltalk'),
];
?>
<?php require __DIR__ . '/partials/head.php'; ?>

<!-- Refined Compact Top Navigation -->
<nav
  class="w-full bg-surface-container-low/80 backdrop-blur-md border-b border-outline-variant/10 px-xl h-14 flex items-center justify-between z-50 shrink-0">
  <a href="?page=home" class="flex items-center gap-sm group">
    <div
      class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-extrabold text-lg shadow-md group-hover:opacity-80 transition-opacity">
      A
    </div>
    <span
      class="font-bold text-base text-on-surface tracking-tight group-hover:text-primary transition-colors">AiTut</span>
  </a>
  <div class="hidden md:flex items-center gap-lg">
    <a href="?page=chat"
      class="text-xs font-semibold text-primary hover:text-primary-fixed transition-colors"><?= __('chat.go_to_chat') ?></a>
    <a href="?page=dashboard"
      class="text-xs font-semibold text-on-surface-variant hover:text-on-surface transition-colors"><?= __('chat.my_profile') ?></a>
  </div>
  <div class="flex items-center gap-md">
    <button
      class="text-on-surface-variant hover:text-on-surface transition-colors flex items-center justify-center p-2 rounded-full hover:bg-surface-container-high/50">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round"
          d="M12 3v2.25m0 13.5V21m8.944-11.25h-2.25M5.056 10.75H2.81m15.356-6.878l-1.591 1.591M6.823 17.177l-1.591 1.591m12.728 0l-1.591-1.591M6.823 4.823L5.232 6.414M12 8.25a3.75 3.75 0 100 7.5 3.75 3.75 0 000-7.5z" />
      </svg>
    </button>
    <div class="relative inline-block text-left group">
      <button
        class="flex items-center gap-2 hover:bg-surface-variant/50 p-1 pr-3 rounded-full transition-colors focus:outline-none border border-outline-variant/20 cursor-default">
        <div
          class="w-8 h-8 rounded-full bg-primary text-on-primary flex items-center justify-center text-sm font-bold shadow-sm">
          <?= $userInitial ?>
        </div>
        <span class="text-sm font-medium text-on-surface hidden sm:block">
          <?= htmlspecialchars($currentUser['name'] ?? explode('@', $currentUser['email'] ?? 'User')[0]) ?>
        </span>
        <span class="material-symbols-outlined text-[16px] text-on-surface-variant">expand_more</span>
      </button>
      <!-- Dropdown -->
      <div class="origin-top-right absolute right-0 pt-2 w-48 z-50 hidden group-hover:block">
        <div
          class="rounded-md shadow-lg border border-outline-variant/20 bg-surface-container-high ring-1 ring-black ring-opacity-5">
          <div class="py-1" role="menu" aria-orientation="vertical">
            <a href="?page=dashboard" class="block px-4 py-2 text-sm text-on-surface hover:bg-surface-variant"
              role="menuitem"><?= __('chat.profile') ?></a>
            <a href="?page=chat" class="block px-4 py-2 text-sm text-on-surface hover:bg-surface-variant"
              role="menuitem">Chat</a>
            <a href="?page=chat-tips" class="block px-4 py-2 text-sm text-on-surface hover:bg-surface-variant"
              role="menuitem"><?= __('chat.instructions_link') ?></a>
            <a href="?page=logout" class="block px-4 py-2 text-sm text-on-surface hover:bg-surface-variant text-error"
              role="menuitem"><?= __('chat.logout') ?></a>
          </div>
        </div>
      </div>
    </div>
  </div>
</nav>

<!-- Main Content Canvas -->
<main class="flex-1 flex flex-col relative h-[calc(100vh-56px)] bg-surface-dim overflow-hidden">
  <div class="flex flex-1 overflow-hidden h-full w-full">

    <!-- Left Sidebar -->
    <aside
      class="hidden md:flex w-64 bg-surface-container-low/30 flex-col border-r border-outline-variant/10 p-md gap-md overflow-y-auto chat-scrollbar shrink-0">
      <!-- User Email -->
      <div class="flex items-center gap-sm text-on-surface-variant text-[11px] mb-xs">
        <span class="truncate"><?= htmlspecialchars($currentUser['email'] ?? '') ?></span>
      </div>

      <!-- Your Progress Card -->
      <div
        class="bg-surface-container p-md rounded-xl border border-outline-variant/20 flex items-center justify-between gap-sm">
        <div class="flex flex-col">
          <span class="text-xs text-on-surface-variant font-medium"><?= __('chat.your_progress') ?></span>
          <span class="text-xl font-bold text-primary mt-1"><?= $xpInLevel ?>%</span>
        </div>
        <div class="relative w-12 h-12 flex items-center justify-center">
          <svg class="w-full h-full transform -rotate-90">
            <circle cx="24" cy="24" r="20" class="stroke-outline-variant/20 fill-transparent" stroke-width="4"></circle>
            <circle cx="24" cy="24" r="20" class="stroke-primary fill-transparent" stroke-width="4"
              stroke-dasharray="125.6" stroke-dashoffset="<?= 125.6 * (1 - $xpInLevel / 100) ?>"></circle>
          </svg>
          <span class="absolute text-[10px] font-bold text-on-surface"><?= $xpInLevel ?>%</span>
        </div>
      </div>

      <!-- New Chat Button -->
      <a href="?page=chat"
        class="flex items-center justify-center gap-2 bg-primary-container hover:bg-primary/20 text-on-primary-container border border-primary/20 rounded-xl py-3 px-md font-semibold text-sm transition-all shadow-md">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        <span><?= __('chat.new_chat') ?></span>
      </a>

      <!-- Search Box -->
      <div class="relative">
        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-outline shrink-0" fill="none"
          stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round"
            d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
        </svg>
        <input type="text" id="conv-search"
          class="w-full bg-surface-container-high border border-outline-variant/30 rounded-xl pl-9 pr-3 py-2 text-xs text-on-surface placeholder-outline focus:outline-none focus:border-primary/50 transition-colors"
          placeholder="<?= __('chat.search_conv') ?>">
      </div>

      <!-- Conversations List -->
      <div class="flex-1 flex flex-col gap-sm overflow-y-auto chat-scrollbar">
        <div class="text-[10px] font-bold text-outline uppercase tracking-wider mt-2 px-1">
          <?= __('chat.section_recent') ?></div>
        <div class="flex flex-col gap-xs" id="conversations-list">
          <?php foreach ($conversations as $conv): ?>
            <?php
            $isActive = ($activeConvId === (int) $conv['id']);
            $firstMsg = !empty($conv['first_message']) ? $conv['first_message'] : __('chat.new_conversation');
            $timeAgo = '';
            if (!empty($conv['updated_at'])) {
              $timeDiff = time() - strtotime($conv['updated_at']);
              if ($timeDiff < 60) {
                $timeAgo = __('chat.just_now');
              } elseif ($timeDiff < 3600) {
                $timeAgo = floor($timeDiff / 60) . ' ' . __('chat.mins_ago');
              } elseif ($timeDiff < 86400) {
                $timeAgo = floor($timeDiff / 3600) . ' ' . __('chat.hours_ago');
              } else {
                $timeAgo = floor($timeDiff / 86400) . ' ' . __('chat.days_ago');
              }
            }
            ?>
            <a href="?page=chat&conv_id=<?= $conv['id'] ?>" data-conv-id="<?= $conv['id'] ?>"
              class="conversation-link flex flex-col p-sm rounded-lg border <?= $isActive ? 'bg-secondary-container/40 border-primary/30 text-primary' : 'bg-transparent border-transparent hover:bg-surface-container-high/40 text-on-surface-variant hover:text-on-surface' ?> transition-colors">
              <span class="text-xs font-semibold truncate max-w-[200px]"><?= htmlspecialchars($firstMsg) ?></span>
              <span class="text-[9px] text-outline mt-0.5"><?= $timeAgo ?></span>
            </a>
          <?php endforeach; ?>
          <div id="conv-search-empty" class="hidden text-center py-6 text-on-surface-variant text-xs">
            <?= __('chat.search_no_results') ?>
          </div>
        </div>
      </div>

      <!-- Language selector at bottom left -->
      <div class="mt-auto pt-sm border-t border-outline-variant/10 relative" id="lang-selector">
        <div id="lang-selector-btn" class="flex items-center justify-between bg-primary/10 border border-primary/20 text-primary rounded-xl px-md py-2.5 font-semibold text-xs transition-colors cursor-pointer hover:bg-primary/20">
          <div class="flex items-center gap-2">
            <?= $targetFlag ?>
            <span><?= __("languages.{$targetLang}") ?></span>
          </div>
          <svg class="w-3.5 h-3.5 shrink-0 transition-transform" id="lang-chevron" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
          </svg>
        </div>
        <div id="lang-dropdown" class="hidden absolute bottom-full left-0 w-full mb-1 bg-surface-container border border-outline-variant/30 rounded-xl overflow-hidden shadow-lg z-50">
          <?php foreach(['en','de','fr','es','zh','ja','ar','tr'] as $l): if($l === $targetLang) continue; ?>
          <a href="?page=update_lang&lang=<?= $l ?>" class="flex items-center gap-2 px-3 py-2 text-xs text-on-surface hover:bg-surface-variant transition">
            <?= flagImg($l, 'w-4 h-3') ?>
            <?= __("languages.{$l}") ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <script>
        (function() {
          var btn = document.getElementById('lang-selector-btn');
          var drop = document.getElementById('lang-dropdown');
          var chevron = document.getElementById('lang-chevron');
          if (!btn || !drop) return;
          function closeLang() {
            drop.classList.add('hidden');
            if (chevron) chevron.classList.remove('rotate-90');
          }
          btn.addEventListener('click', function(e) {
            e.stopPropagation();
            drop.classList.toggle('hidden');
            if (chevron) chevron.classList.toggle('rotate-90');
          });
          document.addEventListener('click', function(e) {
            if (!document.getElementById('lang-selector').contains(e.target)) closeLang();
          });
        })();
      </script>
    </aside>

    <!-- Center: Chat Interface -->
    <section class="flex-1 flex flex-col relative bg-surface-dim h-full">
      <?php if ($isTrialExpired): ?>
      <div class="shrink-0 bg-error-container/90 backdrop-blur-md border-b border-error/30 px-xl py-3 flex items-center justify-between gap-md">
        <div class="flex items-center gap-2 text-on-error-container text-sm">
          <span class="material-symbols-outlined text-[18px]">info</span>
          <span><?= __('chat.trial_expired_banner') ?></span>
        </div>
        <a href="?page=pricing" class="bg-error text-on-error text-xs font-bold px-4 py-1.5 rounded-lg hover:opacity-90 transition shrink-0 whitespace-nowrap">
          <?= __('chat.view_plans') ?>
        </a>
      </div>
      <?php endif; ?>
      <?php if ($activeConvId): ?>
        <!-- Context Header Bar -->
        <div
          class="flex justify-between items-center h-14 px-xl bg-surface-dim/40 backdrop-blur-md z-40 border-b border-outline-variant/10 shrink-0">
          <div class="flex items-center gap-md">
            <span class="font-headline-sm text-headline-sm text-primary"><?= __('chat.professor_kai') ?></span>
            <span
              class="px-sm py-0.5 bg-tertiary-container/30 text-tertiary rounded-full text-label-md border border-tertiary/20"><?= htmlspecialchars($targetLangName) ?></span>
          </div>
          <div class="flex items-center gap-sm text-on-surface-variant text-label-md">
            <svg class="w-4 h-4 text-primary shrink-0" fill="none" stroke="currentColor" stroke-width="2"
              viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="M12 18a3.75 3.75 0 00.495-7.467 5.99 5.99 0 00-1.925-3.546 5.974 5.974 0 01-2.133-1A3.75 3.75 0 002.25 12c0 2.071 1.679 3.75 3.75 3.75a3.75 3.75 0 00.495-.033M12 18a3.75 3.75 0 01-.495-7.467 5.99 5.99 0 011.925-3.546 5.974 5.974 0 002.133-1A3.75 3.75 0 0121.75 12c0 2.071-1.679 3.75-3.75 3.75a3.75 3.75 0 01-.495-.033M12 18v-3" />
            </svg>
            <span class="ml-1"><?= sprintf(__('chat.level_xp'), $level, $xp) ?></span>
          </div>
        </div>
      <?php endif; ?>

      <!-- Message History -->
      <div id="chat-messages" class="flex-1 overflow-y-auto chat-scrollbar p-xl space-y-xl">
        <!-- Empty state wrapper (contains both center text and bottom cards) -->
        <div id="empty-state-wrapper"
          class="flex flex-col justify-between h-full w-full <?= $activeConvId ? 'hidden' : '' ?>">
          <!-- Center content -->
          <div class="flex-1 flex flex-col items-center justify-center text-center">
            <?php
            $map = ['en' => 'us', 'de' => 'de', 'fr' => 'fr', 'es' => 'es', 'zh' => 'cn', 'ja' => 'jp', 'ar' => 'sa', 'tr' => 'tr'];
            $country = $map[$targetLang] ?? 'us';
            ?>
            <div
              class="w-16 h-16 rounded-full bg-surface-container flex items-center justify-center border border-outline-variant/30 mb-4 overflow-hidden shadow-lg">
              <img src="https://flagcdn.com/<?= $country ?>.svg" class="w-full h-full object-cover" />
            </div>
            <h2 class="font-headline-md text-headline-sm text-primary mb-1"><?= __('chat.start_chatting') ?></h2>
            <p class="text-body-md text-on-surface-variant">
              <?= sprintf(__('chat.write_something'), htmlspecialchars($targetLang)) ?></p>
          </div>

          <!-- Topics suggestions grid -->
          <div id="topic-chips"
            class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-sm w-full pt-xl pb-md">
            <?php foreach ($topics as $id => $t): ?>
              <button
                class="topic-chip flex flex-col text-left bg-surface-container-high border border-outline-variant/20 hover:border-primary/50 text-on-surface-variant hover:text-on-surface p-md rounded-xl transition-all h-full"
                data-topic="<?= $id ?>">
                <div class="font-bold text-xs text-on-surface mb-1"><?= htmlspecialchars($t['en']) ?></div>
                <div class="text-[10px] text-outline leading-normal">
                  <?= htmlspecialchars($topicDescriptions[$id] ?? $t['en']) ?></div>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Input Area -->
      <div class="px-xl pb-lg pt-sm shrink-0">
        <div
          class="bg-surface-container border border-outline-variant/20 rounded-2xl flex items-center gap-md px-md py-sm <?= $isTrialExpired ? 'opacity-60' : '' ?>">
          <!-- Clear Input Button -->
          <button id="btn-clear-input" title="Clear input"
            class="text-on-surface-variant hover:text-primary transition-colors flex items-center justify-center shrink-0"
            <?= $isTrialExpired ? 'disabled' : '' ?>>
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>

          <!-- Textarea / Input -->
          <textarea id="chat-input"
            class="flex-1 bg-transparent border-none focus:ring-0 text-xs py-1 px-1 resize-none chat-scrollbar min-h-[24px] max-h-32 text-on-surface placeholder-outline focus:outline-none"
            placeholder="<?= $isTrialExpired ? __('chat.trial_expired_placeholder') : sprintf(__('chat.write_something'), htmlspecialchars($targetLang)) ?>"
            rows="1" <?= $isTrialExpired ? 'disabled' : '' ?>></textarea>

          <!-- Send Button on Right -->
          <button id="btn-send"
            class="bg-surface-container-high hover:bg-secondary-container text-outline hover:text-on-surface font-semibold text-xs px-lg py-2 rounded-xl transition-all shadow-md shrink-0 flex items-center justify-center"
            <?= $isTrialExpired ? 'disabled' : '' ?>>
            <?= __('chat.send') ?>
          </button>
        </div>

        <!-- Trial remaining count badge -->
        <div id="trial-remaining-badge"
          class="mt-2 text-center text-[11px] text-primary font-medium flex items-center justify-center gap-1 <?= $isTrial ? '' : 'hidden' ?>">
          <span class="material-symbols-outlined text-[14px]">info</span>
          <span><?= str_replace('%d', '<strong id="trial-remaining-count">' . max(0, $trialLimit - $trialMessagesSent) . '</strong>', __('chat.trial_remaining')) ?></span>
        </div>
      </div>
    </section>

    <!-- Right: Contextual Panel -->
    <aside
      class="hidden xl:flex w-[300px] bg-surface-container-low/30 flex-col border-l border-outline-variant/10 p-md gap-md overflow-y-auto chat-scrollbar shrink-0">
      <!-- Quick Start Prompts -->
      <div class="flex flex-col gap-sm">
        <?php foreach ([
          __('chat.quick_1'),
          __('chat.quick_2'),
          __('chat.quick_3'),
          __('chat.quick_4'),
        ] as $q): ?>
          <button
            class="quick-msg w-full text-left bg-surface-container hover:bg-surface-container-high px-md py-3 rounded-xl text-xs text-on-surface-variant hover:text-on-surface transition-all border border-outline-variant/20">
            <?= htmlspecialchars($q) ?>
          </button>
        <?php endforeach; ?>
      </div>

      <!-- Separator Line -->
      <hr class="border-outline-variant/10 my-xs">

      <!-- Navigation Links Menu -->
      <div class="flex flex-col gap-xs px-xs">
        <a href="?page=flashcards"
          class="text-xs text-on-surface-variant hover:text-primary transition-colors py-2 flex items-center justify-between">
          <span><?= __('chat.flashcards_link') ?></span>
          <span class="material-symbols-outlined text-[14px]">style</span>
        </a>
        <a href="?page=blog"
          class="text-xs text-on-surface-variant hover:text-primary transition-colors py-2 flex items-center justify-between">
          <span><?= __('chat.blogs_link') ?></span>
        </a>
        <a href="?page=blog"
          class="text-xs text-on-surface-variant hover:text-primary transition-colors py-2 flex items-center justify-between">
          <span><?= __('chat.documents_link') ?></span>
          <span class="material-symbols-outlined text-[14px]">description</span>
        </a>
        <a href="?page=chat-tips"
          class="text-xs text-on-surface-variant hover:text-primary transition-colors py-2 flex items-center justify-between">
          <span><?= __('chat.instructions_link') ?></span>
          <span class="material-symbols-outlined text-[14px]">menu_book</span>
        </a>
      </div>

      <!-- Hidden Vocab Panel wrapper to avoid JavaScript query errors -->
      <div id="vocab-panel" class="hidden"></div>
    </aside>
  </div>
</main>

<!-- Floating Toast Container -->
<div id="toast-container" class="fixed bottom-lg right-lg flex flex-col gap-sm z-50 pointer-events-none"></div>

<!-- Trial Expired Modal -->
<div id="trial-expired-modal"
  class="fixed inset-0 bg-surface-container-lowest/80 backdrop-blur-md flex items-center justify-center z-[100] <?= $isTrialExpired ? '' : 'hidden' ?>">
  <div
    class="glass-panel max-w-md w-full p-8 rounded-2xl border border-primary/20 text-center shadow-2xl mx-4 relative overflow-hidden">
    <!-- Glow effect -->
    <div class="absolute -right-16 -top-16 w-36 h-36 bg-primary/20 rounded-full blur-2xl pointer-events-none"></div>

    <div class="flex flex-col items-center justify-center gap-4">
      <div
        class="w-16 h-16 rounded-2xl bg-primary/10 border border-primary/20 flex items-center justify-center text-primary shrink-0 animate-bounce">
        <span class="material-symbols-outlined text-[36px]">workspace_premium</span>
      </div>
      <h3 class="font-headline-sm text-[22px] font-semibold text-on-surface"><?= __('chat.trial_expired_title') ?></h3>
      <p class="text-body-md text-on-surface-variant max-w-sm mx-auto mb-4">
        <?= __('chat.trial_expired_body') ?>
      </p>

      <div class="flex flex-col sm:flex-row gap-3 w-full justify-center">
        <button onclick="document.getElementById('trial-expired-modal').classList.add('hidden')"
          class="w-full sm:w-auto bg-surface-container-high hover:bg-outline/10 text-on-surface font-semibold text-xs px-xl py-3 rounded-xl transition-all border border-outline-variant/30">
          <?= __('chat.review_chat') ?>
        </button>
        <a href="?page=pricing"
          class="w-full sm:w-auto bg-primary text-on-primary hover:opacity-90 font-semibold text-xs px-xl py-3 rounded-xl transition-all shadow-md flex items-center justify-center gap-2 glow-hover">
          <?= __('chat.view_plans') ?>
          <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
        </a>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    let isTrial = <?= $isTrial ? 'true' : 'false' ?>;
    let trialRemaining = <?= $isTrial ? max(0, $trialLimit - $trialMessagesSent) : 'null' ?>;

    function updateTrialRemaining(remaining) {
      trialRemaining = remaining;
      const countEl = document.getElementById('trial-remaining-count');
      if (countEl) {
        countEl.textContent = remaining;
      }
      if (remaining <= 0) {
        const inputEl = document.getElementById('chat-input');
        const sendBtn = document.getElementById('btn-send');
        if (inputEl) {
          inputEl.disabled = true;
          inputEl.placeholder = '<?= __('chat.trial_expired_placeholder') ?>';
          inputEl.parentElement.classList.add('opacity-60');
        }
        if (sendBtn) {
          sendBtn.disabled = true;
        }
        setTimeout(showTrialExpiredModal, 1500);
      }
    }

    function showTrialExpiredModal() {
      const modal = document.getElementById('trial-expired-modal');
      if (modal) {
        modal.classList.remove('hidden');
      }
    }
    let conversationId = <?= $activeConvId ? $activeConvId : 'null' ?>;
    let activeTopic = null;
    let isLoading = false;
    const TARGET_LANG = '<?= $targetLang ?>';
    const RTL_LANGS = ['ar', 'he', 'fa', 'ur'];
    const SPEECH_LANG_MAP = { en: 'en-US', de: 'de-DE', fr: 'fr-FR', es: 'es-ES', zh: 'zh-CN', ja: 'ja-JP', ar: 'ar-SA', tr: 'tr-TR' };

    const messagesEl = document.getElementById('chat-messages');
    const inputEl = document.getElementById('chat-input');
    const sendBtn = document.getElementById('btn-send');
    const emptyStateWrapper = document.getElementById('empty-state-wrapper');
    const vocabPanel = document.getElementById('vocab-panel');

    inputEl.addEventListener('input', function () {
      this.style.height = 'auto';
      this.style.height = Math.min(this.scrollHeight, 128) + 'px';
    });

    inputEl.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    });
    sendBtn.addEventListener('click', sendMessage);

    document.getElementById('btn-clear-input')?.addEventListener('click', function () {
      inputEl.value = '';
      inputEl.style.height = 'auto';
      inputEl.focus();
    });

    document.querySelectorAll('.topic-chip').forEach(btn => {
      btn.addEventListener('click', function () {
        if (isTrial && trialRemaining <= 0) {
          showTrialExpiredModal();
          return;
        }
        activeTopic = this.dataset.topic;
        appendDateSeparator();
        const title = this.querySelector('div:first-child').textContent.trim();
        appendMessage('user', '📍 Topic: ' + title);
        hideEmptyState();
        sendToAI('Let\'s practice: ' + title, activeTopic);
      });
    });

    document.querySelectorAll('.quick-msg').forEach(btn => {
      btn.addEventListener('click', function () {
        if (isTrial && trialRemaining <= 0) {
          showTrialExpiredModal();
          return;
        }
        inputEl.value = this.textContent.trim();
        inputEl.dispatchEvent(new Event('input'));
        inputEl.focus();
      });
    });

    // Simple live conversation search
    const searchInput = document.getElementById('conv-search');
    if (searchInput) {
      searchInput.addEventListener('input', function () {
        const q = this.value.toLowerCase();
        var visibleCount = 0;
        document.querySelectorAll('#conversations-list a').forEach(link => {
          const text = link.querySelector('span').textContent.toLowerCase();
          if (text.includes(q)) {
            link.classList.remove('hidden');
            visibleCount++;
          } else {
            link.classList.add('hidden');
          }
        });
        var emptyEl = document.getElementById('conv-search-empty');
        if (emptyEl) emptyEl.classList.toggle('hidden', visibleCount > 0 || q === '');
      });
    }

    <?php if ($activeConvId): ?>
      loadConversation(<?= $activeConvId ?>);
    <?php endif; ?>

    function loadConversation(convId) {
      messagesEl.querySelectorAll('.message-row').forEach(el => el.remove());
      var loadId = appendLoadingMessage();
      fetch('?page=chat&conv_id=' + convId, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(r => r.json())
        .then(data => {
          removeLoadingMessage(loadId);
          (data.messages || []).forEach(msg => {
            if (msg.role === 'user') {
              appendMessage('user', msg.content);
            } else {
              appendAiMessage(msg);
            }
          });
          hideEmptyState();
          scrollBottom();
        })
        .catch(function () {
          removeLoadingMessage(loadId);
          showToast('<?= __('chat.http_error') ?>', 'error');
        });
    }

    function sendMessage() {
      if (isTrial && trialRemaining <= 0) {
        showTrialExpiredModal();
        return;
      }
      const msg = inputEl.value.trim();
      if (!msg || isLoading) return;
      inputEl.value = '';
      inputEl.style.height = 'auto';
      appendMessage('user', msg);
      hideEmptyState();
      sendToAI(msg, activeTopic);
    }

    function sendToAI(msg, topicId) {
      isLoading = true;
      sendBtn.disabled = true;
      const loadingId = appendLoadingMessage();

      fetch('?page=chat', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: msg, conversationId: conversationId, topicId: topicId })
      })
        .then(r => r.json())
        .then(data => {
          removeLoadingMessage(loadingId);
          if (data.error) {
            showToast(data.error, 'error');
            if (data.error.includes('<?= __('error.trial_expired') ?>')) {
              showTrialExpiredModal();
            }
          } else {
            var wasNew = !conversationId;
            conversationId = data.conversationId;
            activeTopic = null;
            appendAiMessage(data);
            if (data.isTrial) {
              updateTrialRemaining(data.trialRemaining);
            }
            if (wasNew && conversationId) {
              addConversationLink(conversationId, msg);
            }
          }
        })
        .catch(() => {
          removeLoadingMessage(loadingId);
          showToast('<?= __('chat.http_error') ?>', 'error');
        })
        .finally(() => {
          isLoading = false;
          sendBtn.disabled = false;
          scrollBottom();
        });
    }

    function appendDateSeparator() {
      const div = document.createElement('div');
      div.className = 'message-row flex justify-center';
      const now = new Date();
      const dateStr = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
      div.innerHTML = `<span class="text-label-md text-on-surface-variant bg-surface-container px-lg py-xs rounded-full"><?= __('chat.today') ?>, ${dateStr}</span>`;
      messagesEl.appendChild(div);
    }

    function appendMessage(role, content) {
      const row = document.createElement('div');
      row.className = 'message-row w-full flex ' + (role === 'user' ? 'justify-end' : 'justify-start');

      const avatar = role === 'user'
        ? `<div class="flex-shrink-0 w-10 h-10 rounded-full bg-gradient-to-br from-primary to-indigo-600 flex items-center justify-center shadow-md shadow-primary/30"><span class="material-symbols-outlined text-white">person</span></div>`
        : `<div class="flex-shrink-0 w-10 h-10 rounded-full bg-surface-container-high flex items-center justify-center border border-outline-variant/30 shadow-sm"><span class="material-symbols-outlined text-primary">psychology</span></div>`;

      const align = role === 'user' ? 'text-right' : '';
      const bubbleBg = role === 'user'
        ? 'bg-gradient-to-br from-primary to-indigo-600 p-lg rounded-2xl rounded-tr-none shadow-lg shadow-primary/20 text-white'
        : 'glass-panel p-lg rounded-2xl rounded-tl-none border border-outline-variant/20';
      const direction = role === 'user' ? 'flex-row-reverse' : '';
      const textColor = role === 'user' ? 'text-white' : 'text-on-surface';

      row.innerHTML = `
      <div class="flex ${direction} gap-lg max-w-[75%] group">
        ${avatar}
        <div class="space-y-sm ${align}">
          <div class="flex items-center gap-sm ${role === 'user' ? 'justify-end' : ''} opacity-70 group-hover:opacity-100 transition-opacity">
            <span class="text-label-md text-outline">${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
            <span class="font-bold text-on-surface">${role === 'user' ? '<?= __('chat.you') ?>' : '<?= __('chat.kai') ?>'}</span>
          </div>
          <div class="${bubbleBg} transform transition-all duration-300 hover:scale-[1.01]">
            <p class="text-body-lg ${textColor}">${escHtml(content)}</p>
          </div>
        </div>
      </div>`;
      messagesEl.appendChild(row);
      scrollBottom();
    }

    function appendAiMessage(data) {
      const content = data.content || '';
      const translation = data.translation || '';
      const correction = data.correction || '';
      const corrections = data.corrections || [];
      const words = data.words || [];
      const phonetic = data.phonetic || '';
      const literalTranslation = data.literal_translation || '';
      const grammarSpotlight = data.grammar_spotlight || '';
      const proTip = data.pro_tip || '';
      const isRtl = RTL_LANGS.includes(TARGET_LANG);
      const textDir = isRtl ? 'rtl' : 'ltr';
      const textAlign = isRtl ? 'text-right' : 'text-left';
      const cardId = 'ai-card-' + Date.now() + '-' + Math.random().toString(36).slice(2, 7);

      let correctionsHtml = '';
      if (corrections.length) {
        const chips = corrections.map(c => {
          const pron = c.pronunciation ? `<span class="text-gray-500 ml-1 italic">(${escHtml(c.pronunciation)})</span>` : '';
          return `<div class="bg-gray-800/50 rounded-lg px-3 py-2 text-xs mb-1 border border-gray-700/30">
          <span class="line-through text-red-400">${escHtml(c.original)}</span>
          <span class="text-gray-500 mx-1">→</span>
          <span class="text-green-400">${escHtml(c.corrected)}</span>${pron}
          ${c.rule ? `<div class="text-gray-400 mt-0.5">${formatRichText(c.rule, 'text-teal-400 font-bold')}</div>` : ''}
        </div>`;
        }).join('');
        correctionsHtml = `
        <div class="px-6 py-4 border-t border-gray-700/50 bg-red-500/5">
          <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mb-2"><?= __('chat.your_corrections') ?></p>
          <div class="space-y-1">${chips}</div>
        </div>`;
      } else if (correction) {
        correctionsHtml = `
        <div class="px-6 py-4 border-t border-gray-700/50 bg-red-500/5">
          <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mb-2"><?= __('chat.your_corrections') ?></p>
          <div class="bg-gray-800/50 rounded-lg px-3 py-2 text-xs text-amber-200/80 border border-gray-700/30">${escHtml(correction)}</div>
        </div>`;
      }

      let wordsHtml = '';
      if (words.length) {
        const chips = words.map(w => {
          const pron = w.pronunciation ? `<span class="text-gray-400 italic ml-1">(${escHtml(w.pronunciation)})</span>` : '';
          return `<span class="bg-gray-800/50 rounded-lg px-2.5 py-1.5 text-xs text-teal-300 inline-block mr-1 mb-1 border border-gray-700/30">
          <span class="font-semibold">${escHtml(w.word)}</span>${pron}
          <span class="block text-gray-400 text-[11px] mt-0.5">${escHtml(w.definition)}</span>
        </span>`;
        }).join('');
        wordsHtml = `
        <div class="px-6 py-4 border-t border-gray-700/50">
          <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mb-2"><?= __('chat.key_vocabulary') ?></p>
          <div class="flex flex-wrap">${chips}</div>
        </div>`;
      }

      const phoneticBlock = phonetic ? `
      <div>
        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mb-1"><?= __('chat.phonetic') ?></p>
        <p class="text-sm italic text-gray-300 font-serif">${escHtml(phonetic)}</p>
      </div>` : '';

      const literalBlock = literalTranslation ? `
      <div>
        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mb-1"><?= __('chat.literal_translation') ?></p>
        <p class="text-sm text-gray-300">${escHtml(literalTranslation)}</p>
      </div>` : '';

      const metaGrid = (phonetic || literalTranslation) ? `
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        ${phoneticBlock}
        ${literalBlock}
      </div>` : '';

      const naturalTranslationBlock = translation ? `
      <div class="px-6 py-4 bg-indigo-500/5 border-b border-gray-700/50">
        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mb-1"><?= __('chat.natural_translation') ?></p>
        <p class="text-lg text-gray-100 font-medium italic">"${escHtml(translation)}"</p>
      </div>` : '';

      const grammarBlock = grammarSpotlight ? `
      <div class="space-y-3">
        <div class="flex items-center gap-2">
          <div class="w-1.5 h-4 bg-blue-500 rounded-full"></div>
          <h4 class="text-xs font-bold text-gray-300 uppercase tracking-wider"><?= __('chat.grammar_spotlight') ?></h4>
        </div>
        <div class="bg-gray-800/50 p-4 rounded-xl border border-gray-700/30">
          <p class="text-sm text-gray-400 leading-relaxed">${formatRichText(grammarSpotlight, 'text-blue-400 font-bold')}</p>
        </div>
      </div>` : '';

      const proTipBlock = proTip ? `
      <div class="space-y-3">
        <div class="flex items-center gap-2">
          <div class="w-1.5 h-4 bg-amber-500 rounded-full"></div>
          <h4 class="text-xs font-bold text-gray-300 uppercase tracking-wider"><?= __('chat.pro_tip_culture') ?></h4>
        </div>
        <div class="bg-amber-900/10 p-4 rounded-xl border border-amber-900/20">
          <p class="text-sm text-amber-100/80 leading-relaxed">${formatRichText(proTip, 'text-amber-400 font-bold')}</p>
        </div>
      </div>` : '';

      const learningGrid = (grammarSpotlight || proTip) ? `
      <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
        ${grammarBlock}
        ${proTipBlock}
      </div>` : '';

      const row = document.createElement('div');
      row.className = 'message-row w-full flex justify-start';
      row.innerHTML = `
      <div class="flex gap-lg w-full max-w-2xl">
        <div class="flex-shrink-0 w-10 h-10 rounded-full bg-primary-container flex items-center justify-center border border-primary/20 mt-1">
          <span class="material-symbols-outlined text-on-primary-container">psychology</span>
        </div>
        <div class="space-y-sm flex-1 min-w-0">
          <div class="flex items-center gap-sm">
            <span class="font-bold text-on-surface"><?= __('chat.kai') ?></span>
            <span class="text-label-md text-outline">${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
          </div>
          <div id="${cardId}" class="bg-[#1e293b]/60 border border-gray-700/50 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-sm">
            <div class="p-6 border-b border-gray-700/50 bg-[#1e293b]/40">
              <div class="flex justify-between items-start mb-4">
                <span class="text-[10px] font-bold text-teal-400 uppercase tracking-widest bg-teal-900/20 px-2 py-0.5 rounded"><?= __('chat.target_language') ?></span>
                <button type="button" class="ai-speak-btn text-gray-500 hover:text-white transition-colors" data-text="${escAttr(content)}" aria-label="Listen">
                  <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
                  </svg>
                </button>
              </div>
              <p class="text-2xl sm:text-3xl ${textAlign} font-medium leading-relaxed mb-4" dir="${textDir}">${escHtml(content)}</p>
              ${metaGrid}
            </div>
            ${naturalTranslationBlock}
            ${learningGrid}
            ${correctionsHtml}
            ${wordsHtml}
          </div>
        </div>
      </div>`;
      messagesEl.appendChild(row);

      row.querySelector('.ai-speak-btn')?.addEventListener('click', function () {
        speakText(this.dataset.text);
      });

      scrollBottom();
    }

    let loadingCounter = 0;
    function appendLoadingMessage() {
      const id = 'loading-' + (++loadingCounter);
      const row = document.createElement('div');
      row.id = id;
      row.className = 'message-row w-full flex justify-start';
      row.innerHTML = `
      <div class="flex gap-lg max-w-[75%]">
        <div class="flex-shrink-0 w-10 h-10 rounded-full bg-primary-container flex items-center justify-center border border-primary/20">
          <span class="material-symbols-outlined text-on-primary-container">psychology</span>
        </div>
        <div class="glass-panel p-lg rounded-2xl rounded-tl-none">
          <div class="flex gap-1 items-center h-5">
            <div class="w-2 h-2 bg-on-surface-variant rounded-full animate-bounce" style="animation-delay:0ms"></div>
            <div class="w-2 h-2 bg-on-surface-variant rounded-full animate-bounce" style="animation-delay:150ms"></div>
            <div class="w-2 h-2 bg-on-surface-variant rounded-full animate-bounce" style="animation-delay:300ms"></div>
          </div>
        </div>
      </div>`;
      messagesEl.appendChild(row);
      scrollBottom();
      return id;
    }

    function removeLoadingMessage(id) {
      document.getElementById(id)?.remove();
    }

    function showToast(message, type = 'error') {
      const container = document.getElementById('toast-container');
      if (!container) return;

      const toast = document.createElement('div');
      toast.className = 'pointer-events-auto flex items-center gap-sm bg-surface-container-high border border-outline-variant/30 px-lg py-md rounded-2xl shadow-2xl transition-all duration-300 transform translate-y-4 opacity-0';

      const icon = type === 'error'
        ? `<span class="material-symbols-outlined text-red-500 text-[18px]">cancel</span>`
        : `<span class="material-symbols-outlined text-green-500 text-[18px]">check_circle</span>`;

      toast.innerHTML = `
      ${icon}
      <span class="text-xs text-on-surface font-medium">${escHtml(message)}</span>
    `;

      container.appendChild(toast);

      // Animate in
      setTimeout(() => {
        toast.classList.remove('translate-y-4', 'opacity-0');
      }, 10);

      // Auto remove
      setTimeout(() => {
        toast.classList.add('translate-y-4', 'opacity-0');
        setTimeout(() => {
          toast.remove();
        }, 300);
      }, 4000);
    }

    function hideEmptyState() {
      if (emptyStateWrapper) emptyStateWrapper.classList.add('hidden');
    }

    function scrollBottom() {
      messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function escHtml(str) {
      if (!str) return '';
      return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function escAttr(str) {
      return escHtml(str).replace(/\n/g, ' ');
    }

    function formatRichText(str, highlightClass) {
      if (!str) return '';
      const parts = String(str).split(/\*\*(.+?)\*\*/);
      return parts.map((part, i) => {
        const safe = escHtml(part).replace(/\n\n/g, '<br /><br />').replace(/\n/g, '<br />');
        return i % 2 === 1 ? `<span class="${highlightClass}">${safe}</span>` : safe;
      }).join('');
    }

    function speakText(text) {
      if (!text || !window.speechSynthesis) return;
      window.speechSynthesis.cancel();
      const utterance = new SpeechSynthesisUtterance(text);
      utterance.lang = SPEECH_LANG_MAP[TARGET_LANG] || TARGET_LANG;
      window.speechSynthesis.speak(utterance);
    }

    function addConversationLink(id, firstMsg) {
      var list = document.getElementById('conversations-list');
      if (!list) return;
      var existing = list.querySelector('[data-conv-id="' + id + '"]');
      if (existing) return;
      var a = document.createElement('a');
      a.href = '?page=chat&conv_id=' + id;
      a.setAttribute('data-conv-id', id);
      a.className = 'conversation-link flex flex-col p-sm rounded-lg border bg-secondary-container/40 border-primary/30 text-primary transition-colors';
      a.innerHTML =
        '<span class="text-xs font-semibold truncate max-w-[200px]">' + escHtml(firstMsg) + '</span>' +
        '<span class="text-[9px] text-outline mt-0.5"><?= __('chat.just_now') ?></span>';
      list.insertBefore(a, list.firstChild);
      updateUrl(id);
    }

    function updateUrl(convId) {
      if (history.pushState) {
        var url = new URL(window.location);
        url.searchParams.set('page', 'chat');
        url.searchParams.set('conv_id', convId);
        window.history.pushState({}, '', url);
      }
    }

    if (isTrial) {
      document.addEventListener('visibilitychange', function () {
        if (!document.hidden) window.location.reload();
      });
    }
  })();
</script>

</body>

</html>