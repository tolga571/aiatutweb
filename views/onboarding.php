<?php $pageTitle = __('onboarding.title') . ' – AiTut'; ?>
<?php require __DIR__ . '/partials/head.php'; ?>

<div class="min-h-screen flex items-center justify-center p-4 relative overflow-hidden">
  <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-primary/10 blur-[100px] rounded-full pointer-events-none"></div>
  <div class="absolute bottom-1/3 right-1/4 w-80 h-80 bg-tertiary/10 blur-[100px] rounded-full pointer-events-none"></div>

  <div class="relative z-10 w-full max-w-lg">
    <div class="text-center mb-8">
      <a href="?page=home" class="inline-flex items-center gap-2 font-bold text-xl mb-4">
        <div class="flex flex-col items-start">
          <h1 class="font-headline-md text-[18px] font-extrabold text-primary leading-none tracking-tight">AiTut</h1>
          <p class="text-on-surface-variant text-[8px] uppercase tracking-[0.2em] font-bold">Elite Learning</p>
        </div>
      </a>
      <h1 class="font-headline-lg-mobile text-headline-lg-mobile text-on-surface mb-2"><?= __('onboarding.title') ?></h1>
      <p class="text-body-md text-on-surface-variant"><?= __('onboarding.subtitle') ?></p>
    </div>

    <div class="bg-surface-container border border-outline-variant/20 rounded-2xl p-8">
      <form method="POST" action="?page=onboarding" class="space-y-5">
        <?php
        // Native & target languages: all 8 languages
        $nativeLangOptions = [
          'ar'=>['Arabic','sa'],'en'=>['English','us'],
          'es'=>['Spanish','es'],'zh'=>['Chinese','cn'],'de'=>['German','de'],
          'fr'=>['French','fr'],'ja'=>['Japanese','jp'],'tr'=>['Turkish','tr'],
        ];
        // Target (teachable) languages: all 8
        $targetLangOptions = [
          'ar'=>['Arabic','sa'],'en'=>['English','us'],'es'=>['Spanish','es'],
          'zh'=>['Chinese','cn'],'de'=>['German','de'],'fr'=>['French','fr'],
          'ja'=>['Japanese','jp'],'tr'=>['Turkish','tr'],
        ];
        ?>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-body-md text-on-surface-variant mb-1.5"><?= __('onboarding.native_lang') ?></label>
            <div class="relative">
              <div id="native-display" class="flex items-center gap-2 w-full bg-surface-container-high border border-outline-variant/30 rounded-xl px-4 py-3 text-on-surface cursor-pointer hover:border-primary/50 transition" onclick="toggleDropdown('native')">
                <img id="native-flag" src="https://flagcdn.com/us.svg" class="w-5 h-3.5 rounded-[2px] object-cover" />
                <span id="native-label">English</span>
                <svg class="ml-auto w-4 h-4 text-outline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
              </div>
              <input type="hidden" name="native_lang" id="native-val" value="en" />
              <div id="native-dropdown" class="hidden absolute z-20 mt-1 w-full bg-surface-container border border-outline-variant/30 rounded-xl shadow-xl overflow-hidden">
                <?php foreach ($nativeLangOptions as $code => [$name, $country]): ?>
                  <div class="flex items-center gap-3 px-4 py-2.5 hover:bg-surface-variant cursor-pointer text-body-md text-on-surface transition"
                       onclick="selectLang('native','<?= $code ?>','<?= $country ?>','<?= $name ?>')">
                    <img src="https://flagcdn.com/<?= $country ?>.svg" class="w-5 h-3.5 rounded-[2px] object-cover shrink-0" />
                    <?= $name ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <div>
            <label class="block text-body-md text-on-surface-variant mb-1.5"><?= __('onboarding.target_lang') ?></label>
            <div class="relative">
              <div id="target-display" class="flex items-center gap-2 w-full bg-surface-container-high border border-outline-variant/30 rounded-xl px-4 py-3 text-on-surface cursor-pointer hover:border-primary/50 transition" onclick="toggleDropdown('target')">
                <img id="target-flag" src="https://flagcdn.com/us.svg" class="w-5 h-3.5 rounded-[2px] object-cover" />
                <span id="target-label">English</span>
                <svg class="ml-auto w-4 h-4 text-outline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
              </div>
              <input type="hidden" name="target_lang" id="target-val" value="en" />
              <div id="target-dropdown" class="hidden absolute z-20 mt-1 w-full bg-surface-container border border-outline-variant/30 rounded-xl shadow-xl overflow-hidden">
                <?php foreach ($targetLangOptions as $code => [$name, $country]): ?>
                  <div class="flex items-center gap-3 px-4 py-2.5 hover:bg-surface-variant cursor-pointer text-body-md text-on-surface transition"
                       onclick="selectLang('target','<?= $code ?>','<?= $country ?>','<?= $name ?>')">
                    <img src="https://flagcdn.com/<?= $country ?>.svg" class="w-5 h-3.5 rounded-[2px] object-cover shrink-0" />
                    <?= $name ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
        <script>
        function toggleDropdown(id) {
          const el = document.getElementById(id + '-dropdown');
          ['native','target'].forEach(n => {
            if (n !== id) document.getElementById(n + '-dropdown').classList.add('hidden');
          });
          el.classList.toggle('hidden');
        }
        function selectLang(id, code, country, name) {
          document.getElementById(id + '-flag').src = 'https://flagcdn.com/' + country + '.svg';
          document.getElementById(id + '-label').textContent = name;
          document.getElementById(id + '-val').value = code;
          document.getElementById(id + '-dropdown').classList.add('hidden');
        }
        document.addEventListener('click', function(e) {
          ['native','target'].forEach(id => {
            const disp = document.getElementById(id + '-display');
            const drop = document.getElementById(id + '-dropdown');
            if (!disp.contains(e.target) && !drop.contains(e.target)) drop.classList.add('hidden');
          });
        });
        </script>

        <div>
          <label class="block text-body-md text-on-surface-variant mb-2"><?= __('onboarding.cefr_level') ?></label>
          <div class="grid grid-cols-6 gap-2">
            <?php foreach (['A1','A2','B1','B2','C1','C2'] as $lvl): ?>
              <label class="relative cursor-pointer">
                <input type="radio" name="cefr_level" value="<?= $lvl ?>" <?= $lvl === 'A1' ? 'checked' : '' ?> class="peer sr-only" />
                <div class="text-center py-2 rounded-xl border border-outline-variant/30 text-body-md font-semibold text-on-surface-variant
                            peer-checked:border-primary peer-checked:bg-primary/10 peer-checked:text-primary transition">
                  <?= $lvl ?>
                </div>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div>
          <label class="block text-body-md text-on-surface-variant mb-2"><?= __('onboarding.learning_goal') ?></label>
          <div class="grid grid-cols-2 gap-2">
            <?php foreach ([
              ['conversation', __('onboarding.goal_conversation')],
              ['travel', __('onboarding.goal_travel')],
              ['work', __('onboarding.goal_work')],
              ['exam', __('onboarding.goal_exam')],
            ] as [$val, $label]): ?>
              <label class="relative cursor-pointer">
                <input type="radio" name="learning_goal" value="<?= $val ?>" <?= $val === 'conversation' ? 'checked' : '' ?> class="peer sr-only" />
                <div class="text-center py-2.5 rounded-xl border border-outline-variant/30 text-body-md text-on-surface-variant
                            peer-checked:border-primary peer-checked:bg-primary/10 peer-checked:text-primary transition">
                  <?= $label ?>
                </div>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div>
          <label class="block text-body-md text-on-surface-variant mb-2"><?= __('onboarding.interest') ?></label>
          <div class="flex flex-wrap gap-2">
            <?php foreach ([
              ['general', __('onboarding.interest_general')],
              ['tech', __('onboarding.interest_tech')],
              ['movies', __('onboarding.interest_movies')],
              ['sports', __('onboarding.interest_sports')],
              ['business', __('onboarding.interest_business')],
            ] as [$val, $label]): ?>
              <label class="relative cursor-pointer">
                <input type="radio" name="interest_area" value="<?= $val ?>" <?= $val === 'general' ? 'checked' : '' ?> class="peer sr-only" />
                <div class="px-4 py-2 rounded-full border border-outline-variant/30 text-body-md text-on-surface-variant
                            peer-checked:border-primary peer-checked:bg-primary/10 peer-checked:text-primary transition">
                  <?= $label ?>
                </div>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <button type="submit"
          class="w-full bg-primary text-on-primary font-semibold py-3 rounded-xl transition mt-2 hover:opacity-90">
          <?= __('onboarding.save_continue') ?>
        </button>
      </form>
    </div>
  </div>
</div>

</body>
</html>
