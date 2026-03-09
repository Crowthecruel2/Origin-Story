(function () {
  const STORAGE_KEY = "brighton-theme-mode";
  const MODES = {
    hero: "hero",
    villain: "villain",
  };

  function readModeFromQuery() {
    try {
      const mode = new URLSearchParams(window.location.search).get("mode");
      if (mode === MODES.villain) return MODES.villain;
      if (mode === MODES.hero) return MODES.hero;
    } catch {}
    return null;
  }

  function readSavedMode() {
    try {
      const value = localStorage.getItem(STORAGE_KEY);
      if (value === MODES.villain) return MODES.villain;
      if (value === MODES.hero) return MODES.hero;
    } catch {}
    return MODES.hero;
  }

  function persistMode(mode) {
    try {
      localStorage.setItem(STORAGE_KEY, mode);
    } catch {}
  }

  function applyMode(mode) {
    document.documentElement.setAttribute("data-theme-mode", mode);
    persistMode(mode);
    const btn = document.getElementById("themeModeToggle");
    if (btn) {
      const isVillain = mode === MODES.villain;
      btn.textContent = isVillain ? "Villain Mode: On" : "Villain Mode: Off";
      btn.setAttribute("aria-pressed", String(isVillain));
      btn.classList.toggle("is-villain", isVillain);
    }
  }

  function toggleMode() {
    const current = document.documentElement.getAttribute("data-theme-mode") || MODES.hero;
    applyMode(current === MODES.villain ? MODES.hero : MODES.villain);
  }

  function injectStyles() {
    if (document.getElementById("themeModeStyles")) return;
    const style = document.createElement("style");
    style.id = "themeModeStyles";
    style.textContent = `
      :root[data-theme-mode="villain"] {
        --bg: #230a35 !important;
        --panel: #2d0a42 !important;
        --card: #411056 !important;
        --border: #8a32e0 !important;
        --accent: #b6ff5c !important;
        --accent-2: #d4ff7f !important;
        --text: #efffcf !important;
        --muted: #c9e89c !important;
      }
      :root[data-theme-mode="villain"] body {
        background:
          radial-gradient(circle at 15% 20%, rgba(180, 255, 102, 0.18), transparent 35%),
          radial-gradient(circle at 80% 10%, rgba(146, 255, 87, 0.12), transparent 30%),
          linear-gradient(160deg, #2d0a42, #411056, #2b0f3b) !important;
      }
      :root[data-theme-mode="villain"] .brand .logo {
        background: linear-gradient(135deg, #b6ff5c, #d4ff7f) !important;
        box-shadow: 0 8px 25px rgba(182, 255, 92, 0.45) !important;
      }
      :root[data-theme-mode="villain"] .sheet-view {
        background: linear-gradient(160deg, rgba(45, 10, 66, 0.96), rgba(35, 10, 53, 0.98)) !important;
        border-color: rgba(182, 255, 92, 0.32) !important;
      }
      :root[data-theme-mode="villain"] .sheet-top {
        background: linear-gradient(120deg, rgba(182, 255, 92, 0.22), rgba(212, 255, 127, 0.14)) !important;
      }
      :root[data-theme-mode="villain"] .sheet-stats-banner {
        background:
          radial-gradient(circle at 15% 20%, rgba(182, 255, 92, 0.16), transparent 40%),
          radial-gradient(circle at 85% 25%, rgba(212, 255, 127, 0.12), transparent 36%),
          rgba(48, 13, 67, 0.94) !important;
        border-top-color: rgba(182, 255, 92, 0.28) !important;
        border-bottom-color: rgba(182, 255, 92, 0.28) !important;
      }
      :root[data-theme-mode="villain"] .sheet-set,
      :root[data-theme-mode="villain"] .sheet-block,
      :root[data-theme-mode="villain"] .power-rules,
      :root[data-theme-mode="villain"] .picker-group,
      :root[data-theme-mode="villain"] .picker-preview,
      :root[data-theme-mode="villain"] .rt-editor {
        background: rgba(43, 12, 60, 0.88) !important;
        border-color: rgba(182, 255, 92, 0.28) !important;
      }
      :root[data-theme-mode="villain"] .sheet-chip,
      :root[data-theme-mode="villain"] .sheet-chip.health {
        background: rgba(60, 14, 79, 0.88) !important;
        border-color: rgba(182, 255, 92, 0.35) !important;
      }
      :root[data-theme-mode="villain"] .sheet-stat-row input,
      :root[data-theme-mode="villain"] .sheet-power-select,
      :root[data-theme-mode="villain"] .sheet-lines-input,
      :root[data-theme-mode="villain"] .picker {
        background: rgba(34, 9, 49, 0.92) !important;
        border-color: rgba(182, 255, 92, 0.3) !important;
        color: #f0ffd8 !important;
      }
      :root[data-theme-mode="villain"] .power-rules .title,
      :root[data-theme-mode="villain"] .power-rules .subhead {
        color: #f4ffd9 !important;
      }
      :root[data-theme-mode="villain"] .rt-toolbar {
        background: rgba(43, 12, 60, 0.9) !important;
        border-color: rgba(182, 255, 92, 0.28) !important;
      }
      :root[data-theme-mode="villain"] .rt-toolbar .btn,
      :root[data-theme-mode="villain"] .rt-toolbar select {
        background: rgba(34, 9, 49, 0.92) !important;
        border-color: rgba(182, 255, 92, 0.3) !important;
        color: #f0ffd8 !important;
      }
      #themeModeToggle {
        position: fixed;
        top: calc(3.6rem + env(safe-area-inset-top));
        right: 0.8rem;
        z-index: 9999;
        border: 1px solid var(--border);
        background: rgba(10, 19, 33, 0.92);
        color: var(--text);
        border-radius: 999px;
        padding: 0.45rem 0.75rem;
        font-size: 0.82rem;
        font-weight: 700;
        cursor: pointer;
        backdrop-filter: blur(8px);
      }
      #themeModeToggle.is-villain {
        background: rgba(45, 10, 66, 0.92);
        border-color: #b6ff5c;
        color: #eaffbf;
      }
      @media (max-width: 700px) {
        #themeModeToggle {
          top: calc(3.15rem + env(safe-area-inset-top));
          right: 0.55rem;
          font-size: 0.75rem;
          padding: 0.35rem 0.62rem;
        }
      }
    `;
    document.head.appendChild(style);
  }

  function injectToggleButton() {
    if (!document.body || document.getElementById("themeModeToggle")) return;
    const button = document.createElement("button");
    button.type = "button";
    button.id = "themeModeToggle";
    button.setAttribute("aria-pressed", "false");
    button.textContent = "Villain Mode: Off";
    button.addEventListener("click", toggleMode);
    document.body.appendChild(button);
  }

  function init() {
    injectStyles();
    injectToggleButton();
    const mode = readModeFromQuery() || readSavedMode();
    applyMode(mode);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
  } else {
    init();
  }
})();
