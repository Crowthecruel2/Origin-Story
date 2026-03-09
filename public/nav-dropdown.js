(function () {
  const closeAll = () => {
    document.querySelectorAll('details.nav-group[open]').forEach((node) => {
      node.open = false;
    });
  };

  // Ensure dropdowns never persist open across navigations and bfcache restores.
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", closeAll, { once: true });
  } else {
    closeAll();
  }
  window.addEventListener("pageshow", closeAll);

  document.addEventListener('click', (event) => {
    const inGroup = event.target.closest('details.nav-group');
    const picked = event.target.closest('.nav-menu a, .nav-menu button');

    if (picked) {
      closeAll();
      return;
    }

    if (!inGroup) {
      closeAll();
    }
  });
})();
