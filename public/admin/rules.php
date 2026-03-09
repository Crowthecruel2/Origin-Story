<?php
declare(strict_types=1);

require_once __DIR__ . "/util.php";
admin_require_login();

$pdo = brighton_pdo();

function rules_sanitize_html(string $html): string {
  // Remove script/style blocks and obvious event-handler vectors.
  $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html) ?? "";
  $html = preg_replace('/\son[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $html) ?? "";
  $html = preg_replace('/\s(href|src)\s*=\s*("|\')\s*javascript:.*?\2/i', '', $html) ?? "";

  // Keep a narrow formatting allow-list that covers rules editing needs.
  $allowed = "<p><br><strong><b><em><i><u><span><ul><ol><li><h2><h3><h4><blockquote><code><div><hr>";
  $html = strip_tags($html, $allowed);

  // Keep only known presentation classes used by rules pages.
  $allowedClasses = ["key-term", "rule-callout", "manifesto", "two-col"];
  $html = preg_replace_callback('/\sclass\s*=\s*("|\')(.*?)\1/i', function (array $m) use ($allowedClasses): string {
    $raw = trim((string)($m[2] ?? ""));
    if ($raw === "") return "";
    $tokens = preg_split('/\s+/', $raw) ?: [];
    $keep = [];
    foreach ($tokens as $token) {
      if (in_array($token, $allowedClasses, true)) $keep[] = $token;
    }
    if (!$keep) return "";
    return ' class="' . implode(" ", array_values(array_unique($keep))) . '"';
  }, $html) ?? "";

  return trim((string)$html);
}

function rules_cmp_section_code(string $a, string $b): int {
  $pa = array_values(array_filter(explode(".", trim($a)), fn($v) => $v !== ""));
  $pb = array_values(array_filter(explode(".", trim($b)), fn($v) => $v !== ""));
  $max = max(count($pa), count($pb));
  for ($i = 0; $i < $max; $i++) {
    $sa = $pa[$i] ?? null;
    $sb = $pb[$i] ?? null;
    if ($sa === null) return -1;
    if ($sb === null) return 1;
    $na = ctype_digit($sa) ? (int)$sa : null;
    $nb = ctype_digit($sb) ? (int)$sb : null;
    if ($na !== null && $nb !== null) {
      if ($na < $nb) return -1;
      if ($na > $nb) return 1;
      continue;
    }
    $cmp = strcmp($sa, $sb);
    if ($cmp !== 0) return $cmp;
  }
  return 0;
}

$action = (string)($_GET["action"] ?? "list");
$id = (int)($_GET["id"] ?? 0);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  admin_check_csrf();
  $postAction = (string)($_POST["action"] ?? "");

  if ($postAction === "save") {
    $rid = (int)($_POST["id"] ?? 0);
    $sectionCode = trim((string)($_POST["section_code"] ?? ""));
    if (!preg_match('/^\d+(?:\.\d+)*$/', $sectionCode)) {
      admin_layout("Rules", "<section class='card'><h2>Error</h2><p class='muted'>Section must look like 1, 1.1, or 2.3.4.</p></section>");
      exit;
    }
    $sectionNumber = (int)explode(".", $sectionCode)[0];
    $title = trim((string)($_POST["title"] ?? ""));
    $rulesHtml = rules_sanitize_html((string)($_POST["rules_html"] ?? ""));

    if ($title === "" || $rulesHtml === "") {
      admin_layout("Rules", "<section class='card'><h2>Error</h2><p class='muted'>section, title, and rules content are required.</p></section>");
      exit;
    }

    if ($rid > 0) {
      $stmt = $pdo->prepare("UPDATE rules_sections SET section_code = ?, section_number = ?, title = ?, rules_html = ? WHERE id = ?");
      $stmt->execute([$sectionCode, $sectionNumber, $title, $rulesHtml, $rid]);
    } else {
      $stmt = $pdo->prepare("INSERT INTO rules_sections(section_code,section_number,title,rules_html) VALUES(?,?,?,?)");
      $stmt->execute([$sectionCode, $sectionNumber, $title, $rulesHtml]);
    }
    header("Location: rules.php");
    exit;
  }

  if ($postAction === "delete") {
    $rid = (int)($_POST["id"] ?? 0);
    if ($rid > 0) {
      $pdo->prepare("DELETE FROM rules_sections WHERE id = ?")->execute([$rid]);
    }
    header("Location: rules.php");
    exit;
  }
}

if ($action === "edit") {
  $row = [
    "id" => 0,
    "section_code" => "1",
    "section_number" => 1,
    "title" => "",
    "rules_html" => "<p></p>",
  ];

  if ($id > 0) {
    $stmt = $pdo->prepare("SELECT id,section_code,section_number,title,rules_html FROM rules_sections WHERE id = ?");
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if ($found) $row = $found;
    if (empty($row["section_code"])) $row["section_code"] = (string)($row["section_number"] ?? "1");
  }

  $body = "<style>
    .rules-toolbar-sticky{
      position:sticky;
      top:5.1rem;
      z-index:25;
      display:flex;
      gap:.4rem;
      flex-wrap:wrap;
      align-items:center;
      padding:.45rem .5rem;
      border:1px solid #233449;
      border-radius:10px;
      background:rgba(13,22,36,.96);
      box-shadow:0 8px 24px rgba(0,0,0,.28);
    }
  </style>
  <section class='card'><h2>" . ($id > 0 ? "Edit rule section" : "Add rule section") . "</h2>";
  $body .= "<form method='post' id='ruleForm' class='card' style='background:transparent;border:none;padding:0'>
    <input type='hidden' name='csrf' value='" . h(admin_csrf_token()) . "' />
    <input type='hidden' name='action' value='save' />
    <input type='hidden' name='id' value='" . h((string)$row["id"]) . "' />
    <div class='row'>
      <label class='muted'>Section (e.g. 1 or 1.1)<input name='section_code' value='" . h((string)$row["section_code"]) . "' /></label>
      <label class='muted'>Title<input name='title' value='" . h((string)$row["title"]) . "' /></label>
    </div>

    <input type='hidden' name='rules_html' id='rules_html_input' />

    <div class='muted'>Rules block (rich text)</div>
    <div class='rules-toolbar-sticky'>
      <button type='button' class='btn' data-cmd='bold'>Bold</button>
      <button type='button' class='btn' data-cmd='underline'>Underline</button>
      <button type='button' class='btn' data-cmd='italic'>Italic</button>
      <button type='button' class='btn' id='makeSubheading'>Subheading</button>
      <button type='button' class='btn' data-cmd='insertUnorderedList'>Bullet list</button>
      <button type='button' class='btn' data-cmd='insertOrderedList'>Numbered list</button>
      <button type='button' class='btn' id='toggleTwoCol'>Toggle 2-column list</button>
      <button type='button' class='btn' id='insertCallout'>Insert callout</button>
      <button type='button' class='btn' id='insertManifesto'>Insert statement</button>
      <button type='button' class='btn' id='insertKeyTerm'>Insert key term</button>
      <button type='button' class='btn' id='clearFormat'>Clear format</button>
      <label class='muted' style='width:auto'>Font size
        <select id='fontSizePicker' style='width:auto;min-width:120px'>
          <option value='12'>Small</option>
          <option value='16' selected>Normal</option>
          <option value='20'>Large</option>
          <option value='26'>XL</option>
        </select>
      </label>
      <button type='button' class='btn' id='applySize'>Apply size</button>
    </div>
    <div id='rules_editor' contenteditable='true' style='min-height:320px;padding:.7rem;border:1px solid #233449;border-radius:10px;background:#0f1c2d;line-height:1.5'>" . (string)$row["rules_html"] . "</div>
    <div class='muted'>Allowed formatting includes bold/underline/italic, headings, lists, font sizes, and classes: key-term, rule-callout, manifesto, two-col.</div>
    <div style='display:flex;gap:.6rem;flex-wrap:wrap'>
      <button class='btn primary' type='submit'>Save</button>
      <a class='btn' href='rules.php'>Cancel</a>
    </div>
  </form>";

  if ($id > 0) {
    $body .= "<form method='post' onsubmit='return confirm(\"Delete this rules section?\")'>
      <input type='hidden' name='csrf' value='" . h(admin_csrf_token()) . "' />
      <input type='hidden' name='action' value='delete' />
      <input type='hidden' name='id' value='" . h((string)$id) . "' />
      <button class='btn danger' type='submit'>Delete</button>
    </form>";
  }

  $body .= "</section>
<script>
  (function () {
    const editor = document.getElementById('rules_editor');
    const input = document.getElementById('rules_html_input');
    const form = document.getElementById('ruleForm');
    const sizePicker = document.getElementById('fontSizePicker');
    const applySize = document.getElementById('applySize');
    const makeSubheading = document.getElementById('makeSubheading');
    const toggleTwoCol = document.getElementById('toggleTwoCol');
    const insertCallout = document.getElementById('insertCallout');
    const insertManifesto = document.getElementById('insertManifesto');
    const insertKeyTerm = document.getElementById('insertKeyTerm');
    const clearFormat = document.getElementById('clearFormat');

    function sync() {
      input.value = editor.innerHTML.trim();
    }

    function cmd(name) {
      editor.focus();
      document.execCommand(name, false, null);
      sync();
    }

    function escapeHtml(text) {
      return String(text || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function rangeToPlainHtml(range) {
      const frag = range.cloneContents();
      const holder = document.createElement('div');
      holder.appendChild(frag);
      const text = (holder.innerText || holder.textContent || '')
        .replace(/\\r\\n/g, '\\n')
        .replace(/\\r/g, '\\n');
      return escapeHtml(text).replace(/\\n/g, '<br>');
    }

    function clearFormattingSelection() {
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
        const block = node && node.closest ? node.closest('p, h1, h2, h3, h4, h5, h6, li, blockquote, div') : null;
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
        document.execCommand('insertHTML', false, plainHtml);
      }
      sync();
    }

    function applyFontSize(px) {
      editor.focus();
      document.execCommand('fontSize', false, '7');
      editor.querySelectorAll('font[size=\"7\"]').forEach((node) => {
        const span = document.createElement('span');
        span.style.fontSize = String(px) + 'px';
        span.innerHTML = node.innerHTML;
        node.replaceWith(span);
      });
      sync();
    }

    function insertHtml(html) {
      editor.focus();
      document.execCommand('insertHTML', false, html);
      sync();
    }

    function setBlock(tagName) {
      editor.focus();
      document.execCommand('formatBlock', false, tagName);
      sync();
    }

    function closestListFromSelection() {
      const sel = window.getSelection();
      if (!sel || !sel.rangeCount) return null;
      let node = sel.getRangeAt(0).commonAncestorContainer;
      if (node.nodeType === Node.TEXT_NODE) node = node.parentNode;
      return node && node.closest ? node.closest('ul, ol') : null;
    }

    document.querySelectorAll('[data-cmd]').forEach((btn) => {
      btn.addEventListener('click', () => cmd(btn.getAttribute('data-cmd')));
    });

    applySize.addEventListener('click', () => {
      const px = Number(sizePicker.value || 16);
      applyFontSize(px);
    });

    makeSubheading.addEventListener('click', () => setBlock('h3'));

    toggleTwoCol.addEventListener('click', () => {
      const list = closestListFromSelection();
      if (!list) return;
      list.classList.toggle('two-col');
      sync();
    });

    insertCallout.addEventListener('click', () => {
      insertHtml('<p class=\"rule-callout\">Important rule...</p>');
    });

    insertManifesto.addEventListener('click', () => {
      insertHtml('<blockquote class=\"manifesto\">Power does not exist in a vacuum.</blockquote>');
    });

    insertKeyTerm.addEventListener('click', () => {
      insertHtml('<span class=\"key-term\">Key term</span>');
    });

    clearFormat.addEventListener('click', clearFormattingSelection);

    form.addEventListener('submit', sync);
    sync();
  })();
</script>";

  admin_layout("Rules", $body);
  exit;
}

$q = trim((string)($_GET["q"] ?? ""));
if ($q !== "") {
  $stmt = $pdo->prepare(
    "SELECT id,section_code,section_number,title,rules_html
     FROM rules_sections
     WHERE title LIKE ? OR rules_html LIKE ?
     LIMIT 1000"
  );
  $like = "%" . $q . "%";
  $stmt->execute([$like, $like]);
  $rows = $stmt->fetchAll();
} else {
  $rows = $pdo->query(
    "SELECT id,section_code,section_number,title,rules_html
     FROM rules_sections
     LIMIT 1000"
  )->fetchAll();
}
usort($rows, function (array $a, array $b): int {
  $sa = (string)($a["section_code"] ?? $a["section_number"] ?? "");
  $sb = (string)($b["section_code"] ?? $b["section_number"] ?? "");
  $cmp = rules_cmp_section_code($sa, $sb);
  if ($cmp !== 0) return $cmp;
  return (int)$a["id"] <=> (int)$b["id"];
});

$body = "<section class='card'><div style='display:flex;justify-content:space-between;align-items:baseline;gap:.8rem;flex-wrap:wrap'>
  <h2>Rules Sections</h2>
  <a class='btn primary' href='rules.php?action=edit'>Add rules section</a>
</div>
<form method='get' style='display:flex;gap:.6rem;flex-wrap:wrap;align-items:center'>
  <input name='q' placeholder='Search title/content...' value='" . h($q) . "' />
  <button class='btn' type='submit'>Search</button>
  <a class='btn' href='rules.php'>Clear</a>
</form>
<div class='muted'>Showing up to 1000 rows.</div>
<table><thead><tr><th>#</th><th>Title</th><th>Preview</th></tr></thead><tbody>";

foreach ($rows as $r) {
  $plain = trim(preg_replace('/\s+/', ' ', strip_tags((string)$r["rules_html"])) ?? "");
  if (strlen($plain) > 140) $plain = substr($plain, 0, 140) . "...";
  $sectionLabel = (string)($r["section_code"] ?? $r["section_number"] ?? "");
  $body .= "<tr>
    <td><a href='rules.php?action=edit&id=" . rawurlencode((string)$r["id"]) . "'>" . h($sectionLabel) . "</a></td>
    <td>" . h((string)$r["title"]) . "</td>
    <td class='muted'>" . h($plain) . "</td>
  </tr>";
}

$body .= "</tbody></table></section>";
admin_layout("Rules", $body);
