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

  document.addEventListener('click', function (e) {
    const menu = document.getElementById('pagesMenu');
    const button = document.getElementById('pagesBtn');
    if (menu && button && !menu.classList.contains('hidden') && !menu.contains(e.target) && !button.contains(e.target)) {
      menu.classList.add('hidden');
      button.setAttribute('aria-expanded', 'false');
    }
  });
});
