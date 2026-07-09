document.addEventListener('DOMContentLoaded', function () {
  const toggleMenu = (buttonId, menuId) => {
    const btn = document.getElementById(buttonId);
    const menu = document.getElementById(menuId);
    if (!btn || !menu) return;
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      menu.classList.toggle('hidden');
      const isOpen = !menu.classList.contains('hidden');
      btn.setAttribute('aria-expanded', isOpen);
    });
  };

  toggleMenu('pagesBtn', 'pagesMenu');

  const hamburgerBtn = document.getElementById('hamburgerBtn');
  const hamburgerCloseBtn = document.getElementById('hamburgerCloseBtn');
  const mobileMenu = document.getElementById('mobileMenu');
  const mobileBackdrop = document.getElementById('mobileMenuBackdrop');

  function openMobileMenu() {
    if (!mobileMenu) return;
    mobileMenu.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  }

  function closeMobileMenu() {
    if (!mobileMenu) return;
    mobileMenu.classList.add('hidden');
    document.body.style.overflow = '';
  }

  if (hamburgerBtn) hamburgerBtn.addEventListener('click', openMobileMenu);
  if (hamburgerCloseBtn) hamburgerCloseBtn.addEventListener('click', closeMobileMenu);
  if (mobileBackdrop) mobileBackdrop.addEventListener('click', closeMobileMenu);

  // Close menus on outside click
  document.addEventListener('click', function (e) {
    const ids = ['pagesMenu', 'nav-lang-dropdown'];
    ids.forEach(menuId => {
      const menu = document.getElementById(menuId);
      const button = document.getElementById(menuId.replace('Dropdown', 'Btn').replace('Menu', 'Btn').replace('nav-lang-', 'nav-lang-'));
      if (menu && !menu.classList.contains('hidden') && !menu.contains(e.target) && !e.target.closest('[id$=\"Btn\"], [id$=\"btn\"]')) {
        menu.classList.add('hidden');
        if (button) button.setAttribute('aria-expanded', 'false');
      }
    });
    // Close language switcher specifically
    const langSwitcher = document.getElementById('nav-lang-switcher');
    const langDrop = document.getElementById('nav-lang-dropdown');
    if (langDrop && !langDrop.classList.contains('hidden') && langSwitcher && !langSwitcher.contains(e.target)) {
      langDrop.classList.add('hidden');
    }
  });

  // Lang switcher toggle (click-based for mobile compatibility)
  const navLangSwitcher = document.getElementById('nav-lang-switcher');
  const navLangDrop = document.getElementById('nav-lang-dropdown');
  if (navLangSwitcher && navLangDrop) {
    navLangSwitcher.querySelector('button')?.addEventListener('click', function (e) {
      e.stopPropagation();
      navLangDrop.classList.toggle('hidden');
    });
  }
});
