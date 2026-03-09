(function () {
  const RICH_NAMES = new Set([
    "description",
    "content",
    "effects",
    "blurb",
    "notes",
    "powers_text",
    "inventory_text",
  ]);
  let editorSeq = 0;

  function shouldEnhance(textarea) {
    if (!textarea || textarea.dataset.richtext === "off") return false;
    if (textarea.dataset.richtextApplied === "1") return false;
    if (textarea.closest(".rt-wrap")) return false;
    if (textarea.dataset.richtext === "on") return true;
    const name = String(textarea.getAttribute("name") || "").trim().toLowerCase();
    if (!name) return false;
    return RICH_NAMES.has(name);
  }

  function makeButton(label, onClick) {
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "btn";
    btn.textContent = label;
    btn.addEventListener("click", onClick);
    return btn;
  }

  function syncHidden(editor, textarea) {
    textarea.value = editor.innerHTML.trim();
  }

  function applyFontSize(editor, px) {
    editor.focus();
    document.execCommand("fontSize", false, "7");
    editor.querySelectorAll('font[size="7"]').forEach((node) => {
      const span = document.createElement("span");
      span.style.fontSize = String(px) + "px";
      span.innerHTML = node.innerHTML;
      node.replaceWith(span);
    });
  }

  function escapeHtml(text) {
    return String(text || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function rangeToPlainHtml(range) {
    const frag = range.cloneContents();
    const holder = document.createElement("div");
    holder.appendChild(frag);
    const text = (holder.innerText || holder.textContent || "")
      .replace(/\r\n/g, "\n")
      .replace(/\r/g, "\n");
    return escapeHtml(text).replace(/\n/g, "<br>");
  }

  function selectionInside(editor) {
    const sel = window.getSelection();
    if (!sel || !sel.rangeCount) return false;
    return editor.contains(sel.getRangeAt(0).commonAncestorContainer);
  }

  function closestListFromSelection(editor) {
    const sel = window.getSelection();
    if (!sel || !sel.rangeCount) return null;
    const range = sel.getRangeAt(0);
    if (!editor.contains(range.commonAncestorContainer)) return null;
    let node = range.commonAncestorContainer;
    if (node.nodeType === Node.TEXT_NODE) node = node.parentNode;
    return node && node.closest ? node.closest("ul, ol") : null;
  }

  function setBlock(editor, tagName) {
    editor.focus();
    document.execCommand("formatBlock", false, tagName);
  }

  function insertHtml(editor, html) {
    editor.focus();
    document.execCommand("insertHTML", false, html);
  }

  function clearFormattingSelection(editor) {
    editor.focus();
    const sel = window.getSelection();
    if (!sel || !sel.rangeCount) return;
    let range = sel.getRangeAt(0);

    if (!editor.contains(range.commonAncestorContainer)) {
      const all = document.createRange();
      all.selectNodeContents(editor);
      range = all;
      sel.removeAllRanges();
      sel.addRange(range);
    } else if (range.collapsed) {
      let node = range.commonAncestorContainer;
      if (node.nodeType === Node.TEXT_NODE) node = node.parentNode;
      const block = node && node.closest ? node.closest("p, h1, h2, h3, h4, h5, h6, li, blockquote, div") : null;
      const scoped = document.createRange();
      if (block && editor.contains(block)) {
        scoped.selectNodeContents(block);
      } else {
        scoped.selectNodeContents(editor);
      }
      range = scoped;
      sel.removeAllRanges();
      sel.addRange(range);
    }

    const plainHtml = rangeToPlainHtml(range);
    range.deleteContents();
    if (plainHtml) {
      document.execCommand("insertHTML", false, plainHtml);
    }
  }

  function enhance(textarea) {
    const wrap = document.createElement("div");
    wrap.className = "rt-wrap";

    const toolbar = document.createElement("div");
    toolbar.className = "rt-toolbar";

    const editor = document.createElement("div");
    editor.className = "rt-editor";
    editor.contentEditable = "true";
    editor.innerHTML = textarea.value || "";
    editor.id = "rt-editor-" + String(++editorSeq);
    textarea.dataset.richEditorId = editor.id;

    const doCmd = (cmd) => {
      editor.focus();
      document.execCommand(cmd, false, null);
      syncHidden(editor, textarea);
    };

    toolbar.appendChild(makeButton("Bold", () => doCmd("bold")));
    toolbar.appendChild(makeButton("Underline", () => doCmd("underline")));
    toolbar.appendChild(makeButton("Italic", () => doCmd("italic")));
    toolbar.appendChild(makeButton("Subheading", () => {
      setBlock(editor, "h3");
      syncHidden(editor, textarea);
    }));
    toolbar.appendChild(makeButton("Bullet list", () => doCmd("insertUnorderedList")));
    toolbar.appendChild(makeButton("Numbered list", () => doCmd("insertOrderedList")));
    toolbar.appendChild(makeButton("Toggle 2-column list", () => {
      const list = closestListFromSelection(editor);
      if (!list) return;
      list.classList.toggle("two-col");
      syncHidden(editor, textarea);
    }));
    toolbar.appendChild(makeButton("Insert callout", () => {
      insertHtml(editor, '<p class="rule-callout">Important rule...</p>');
      syncHidden(editor, textarea);
    }));
    toolbar.appendChild(makeButton("Insert statement", () => {
      insertHtml(editor, '<blockquote class="manifesto">Power does not exist in a vacuum.</blockquote>');
      syncHidden(editor, textarea);
    }));
    toolbar.appendChild(makeButton("Insert key term", () => {
      insertHtml(editor, '<span class="key-term">Key term</span>');
      syncHidden(editor, textarea);
    }));
    toolbar.appendChild(makeButton("Clear format", () => {
      clearFormattingSelection(editor);
      syncHidden(editor, textarea);
    }));

    const size = document.createElement("select");
    [
      ["12", "Small"],
      ["16", "Normal"],
      ["20", "Large"],
      ["26", "XL"],
    ].forEach(([value, label]) => {
      const opt = document.createElement("option");
      opt.value = value;
      opt.textContent = label;
      if (value === "16") opt.selected = true;
      size.appendChild(opt);
    });
    toolbar.appendChild(size);
    toolbar.appendChild(
      makeButton("Apply size", () => {
        applyFontSize(editor, Number(size.value || 16));
        syncHidden(editor, textarea);
      })
    );

    textarea.style.display = "none";
    textarea.setAttribute("data-richtext-applied", "1");

    textarea.parentNode.insertBefore(wrap, textarea.nextSibling);
    wrap.appendChild(toolbar);
    wrap.appendChild(editor);

    editor.addEventListener("input", () => syncHidden(editor, textarea));
    editor.addEventListener("paste", () => {
      setTimeout(() => syncHidden(editor, textarea), 0);
    });
    syncHidden(editor, textarea);

    const form = textarea.closest("form");
    if (form && !form.dataset.richtextSyncBound) {
      form.dataset.richtextSyncBound = "1";
      form.addEventListener("submit", () => {
        form.querySelectorAll("textarea[data-richtext-applied='1']").forEach((ta) => {
          const id = ta.dataset.richEditorId;
          const rt = id ? document.getElementById(id) : null;
          if (rt) ta.value = rt.innerHTML.trim();
        });
      });
    }
  }

  function enhanceAll(root) {
    const scope = root && root.querySelectorAll ? root : document;
    scope.querySelectorAll("textarea").forEach((textarea) => {
      if (shouldEnhance(textarea)) {
        enhance(textarea);
      }
    });
  }

  enhanceAll(document);

  const observer = new MutationObserver((mutations) => {
    mutations.forEach((m) => {
      m.addedNodes.forEach((node) => {
        if (!(node instanceof Element)) return;
        if (node.matches && node.matches("textarea")) {
          if (shouldEnhance(node)) enhance(node);
          return;
        }
        enhanceAll(node);
      });
    });
  });
  if (document.body) {
    observer.observe(document.body, { childList: true, subtree: true });
  }

  window.BrightonRichText = {
    enhance,
    enhanceAll,
  };
})();
