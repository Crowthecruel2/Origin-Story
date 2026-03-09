<?php
declare(strict_types=1);

require_once __DIR__ . "/util.php";
admin_require_login();

$pdo = brighton_pdo();

$action = (string)($_GET["action"] ?? "list");
$id = (string)($_GET["id"] ?? "");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  admin_check_csrf();
  $postAction = (string)($_POST["action"] ?? "");

  if ($postAction === "save") {
    $pid = trim((string)($_POST["id"] ?? ""));
    $name = trim((string)($_POST["name"] ?? ""));
    $className = trim((string)($_POST["class_name"] ?? ""));
    if ($pid === "" || $name === "" || $className === "") {
      admin_layout("Powers", "<section class='card'><h2>Error</h2><p class='muted'>id, name, and class_name are required.</p></section>");
      exit;
    }

    $prereqs = json_decode((string)($_POST["prerequisites_json"] ?? "[]"), true);
    $tags = json_decode((string)($_POST["tags_json"] ?? "[]"), true);
    $sub = json_decode((string)($_POST["sub_powers_json"] ?? "[]"), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      admin_layout("Powers", "<section class='card'><h2>Error</h2><p class='muted'>Invalid JSON in prerequisites/tags/sub powers.</p></section>");
      exit;
    }
    if (!is_array($sub)) $sub = [];

    $pdo->prepare("INSERT IGNORE INTO power_classes(name) VALUES(?)")->execute([$className]);

    $stmt = $pdo->prepare(
      "INSERT INTO powers(id,name,class_name,path,description,content,min_level,prerequisites_json,tags_json)
       VALUES(?,?,?,?,?,?,?,?,?)
       ON DUPLICATE KEY UPDATE
         name=VALUES(name),class_name=VALUES(class_name),path=VALUES(path),description=VALUES(description),
         content=VALUES(content),min_level=VALUES(min_level),prerequisites_json=VALUES(prerequisites_json),tags_json=VALUES(tags_json)"
    );
    $stmt->execute([
      $pid,
      $name,
      $className,
      ($_POST["path"] ?? null) ?: null,
      ($_POST["description"] ?? null) ?: null,
      ($_POST["content"] ?? null) ?: null,
      ($_POST["min_level"] ?? null) !== "" ? (int)($_POST["min_level"]) : null,
      json_encode($prereqs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      json_encode($tags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $pdo->prepare("DELETE FROM power_levels WHERE power_id = ?")->execute([$pid]);
    $insertLvl = $pdo->prepare("INSERT INTO power_levels(power_id,idx,level,cost,text) VALUES(?,?,?,?,?)");
    $idx = 0;
    foreach ($sub as $row) {
      if (!is_array($row)) continue;
      $insertLvl->execute([
        $pid,
        $idx++,
        array_key_exists("level", $row) && $row["level"] !== null ? (int)$row["level"] : null,
        array_key_exists("cost", $row) && $row["cost"] !== null ? (int)$row["cost"] : null,
        isset($row["text"]) ? (string)$row["text"] : null,
      ]);
    }

    header("Location: powers.php");
    exit;
  }

  if ($postAction === "delete") {
    $pid = (string)($_POST["id"] ?? "");
    $pdo->prepare("DELETE FROM powers WHERE id = ?")->execute([$pid]);
    header("Location: powers.php");
    exit;
  }
}

if ($action === "edit") {
  $row = [
    "id" => "",
    "name" => "",
    "class_name" => "",
    "path" => "",
    "description" => "",
    "content" => "",
    "min_level" => "",
    "prerequisites_json" => "[]",
    "tags_json" => "[]",
  ];
  $subData = [];

  if ($id !== "") {
    $stmt = $pdo->prepare("SELECT * FROM powers WHERE id = ?");
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if ($found) $row = $found;

    $lvls = $pdo->prepare("SELECT level,cost,text FROM power_levels WHERE power_id = ? ORDER BY idx ASC");
    $lvls->execute([$id]);
    foreach ($lvls->fetchAll() as $l) {
      $subData[] = [
        "level" => $l["level"] !== null ? (int)$l["level"] : null,
        "cost" => $l["cost"] !== null ? (int)$l["cost"] : null,
        "text" => $l["text"],
      ];
    }
  }
  $subJson = json_encode($subData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  if ($subJson === false) $subJson = "[]";

  $classes = $pdo->query("SELECT name FROM power_classes ORDER BY name ASC")->fetchAll();
  $opts = "<option value=''>Select...</option>";
  foreach ($classes as $c) {
    $n = (string)$c["name"];
    $sel = ((string)$row["class_name"] === $n) ? "selected" : "";
    $opts .= "<option {$sel} value='" . h($n) . "'>" . h($n) . "</option>";
  }

  $body = "<section class='card'><h2>" . ($id ? "Edit power" : "Add power") . "</h2>";
  $body .= "<form method='post' class='card' style='background:transparent;border:none;padding:0'>
    <input type='hidden' name='csrf' value='" . h(admin_csrf_token()) . "' />
    <input type='hidden' name='action' value='save' />
    <div class='row'>
      <label class='muted'>ID<input name='id' value='" . h((string)$row["id"]) . "' " . ($id ? "readonly" : "") . " /></label>
      <label class='muted'>Name<input name='name' value='" . h((string)$row["name"]) . "' /></label>
    </div>
    <div class='row'>
      <label class='muted'>Class<select name='class_name'>{$opts}</select></label>
      <label class='muted'>Min level<input name='min_level' value='" . h((string)($row["min_level"] ?? "")) . "' /></label>
    </div>
    <label class='muted'>Path<input name='path' value='" . h((string)($row["path"] ?? "")) . "' /></label>
    <label class='muted'>Description<textarea name='description'>" . h((string)($row["description"] ?? "")) . "</textarea></label>
    <label class='muted'>Content<textarea name='content'>" . h((string)($row["content"] ?? "")) . "</textarea></label>
    <div class='row'>
      <label class='muted'>Prerequisites (JSON array)<textarea name='prerequisites_json'>" . h((string)($row["prerequisites_json"] ?? "[]")) . "</textarea></label>
      <label class='muted'>Tags (JSON array)<textarea name='tags_json'>" . h((string)($row["tags_json"] ?? "[]")) . "</textarea></label>
    </div>
    <input type='hidden' name='sub_powers_json' id='sub_powers_json' value='" . h($subJson) . "' />
    <div class='muted'>Sub powers / levels</div>
    <div id='subPowersRows' style='display:grid;gap:.55rem'></div>
    <div style='display:flex;gap:.5rem;flex-wrap:wrap'>
      <button class='btn' type='button' id='addSubPowerRow'>Add sub power row</button>
    </div>
    <div class='muted'>Each row saves as <code>{\"level\": number|null, \"cost\": number|null, \"text\": \"...\"}</code>.</div>
    <div style='display:flex;gap:.6rem;flex-wrap:wrap'>
      <button class='btn primary' type='submit'>Save</button>
      <a class='btn' href='powers.php'>Cancel</a>
    </div>
  </form>";
  $body .= "<script>
    (function () {
      const rowsEl = document.getElementById('subPowersRows');
      const hidden = document.getElementById('sub_powers_json');
      const addBtn = document.getElementById('addSubPowerRow');
      if (!rowsEl || !hidden || !addBtn) return;

      let initial = [];
      try {
        initial = JSON.parse(hidden.value || '[]');
        if (!Array.isArray(initial)) initial = [];
      } catch {
        initial = [];
      }

      function parseNum(v) {
        const raw = String(v ?? '').trim();
        if (raw === '') return null;
        const n = Number(raw);
        return Number.isFinite(n) ? n : null;
      }

      function readText(ta) {
        const editor = ta.parentNode ? ta.parentNode.querySelector('.rt-wrap .rt-editor') : null;
        if (editor) return String(editor.innerHTML || '').trim();
        return String(ta.value || '').trim();
      }

      function sync() {
        const out = [];
        rowsEl.querySelectorAll('.sub-power-row').forEach((row) => {
          const levelEl = row.querySelector('[data-k=\"level\"]');
          const costEl = row.querySelector('[data-k=\"cost\"]');
          const textEl = row.querySelector('[data-k=\"text\"]');
          if (!levelEl || !costEl || !textEl) return;

          const level = parseNum(levelEl.value);
          const cost = parseNum(costEl.value);
          const text = readText(textEl);
          if (level === null && cost === null && text === '') return;
          out.push({ level, cost, text });
        });
        hidden.value = JSON.stringify(out);
      }

      function addRow(data) {
        const d = data && typeof data === 'object' ? data : {};
        const safeLevel = d.level == null ? '' : String(d.level);
        const safeCost = d.cost == null ? '' : String(d.cost);
        const safeText = String(d.text ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;');
        const row = document.createElement('div');
        row.className = 'sub-power-row';
        row.style.border = '1px solid #233449';
        row.style.borderRadius = '10px';
        row.style.padding = '.6rem';
        row.style.background = '#0f1c2d';
        row.style.display = 'grid';
        row.style.gap = '.55rem';

        row.innerHTML =
          \"<div class='row'>\" +
            \"<label class='muted'>Level<input type='number' step='1' data-k='level' value='\" + safeLevel + \"' /></label>\" +
            \"<label class='muted'>Cost<input type='number' step='1' data-k='cost' value='\" + safeCost + \"' /></label>\" +
          \"</div>\" +
          \"<label class='muted'>Text\" +
            \"<textarea data-k='text' data-richtext='on'>\" + safeText + \"</textarea>\" +
          \"</label>\" +
          \"<div style='display:flex;gap:.5rem;flex-wrap:wrap'>\" +
            \"<button type='button' class='btn' data-act='remove'>Remove row</button>\" +
          \"</div>\";

        row.querySelector('[data-act=\"remove\"]').addEventListener('click', () => {
          row.remove();
          sync();
        });

        rowsEl.appendChild(row);
      }

      addBtn.addEventListener('click', () => {
        addRow({ level: null, cost: null, text: '' });
        sync();
      });

      rowsEl.addEventListener('input', sync);
      rowsEl.addEventListener('change', sync);
      const form = rowsEl.closest('form');
      if (form) form.addEventListener('submit', sync);

      if (initial.length > 0) {
        initial.forEach((item) => addRow(item));
      } else {
        addRow({ level: null, cost: null, text: '' });
      }
      sync();
    })();
  </script>";
  if ($id) {
    $body .= "<form method='post' onsubmit='return confirm(\"Delete this power?\")'>
      <input type='hidden' name='csrf' value='" . h(admin_csrf_token()) . "' />
      <input type='hidden' name='action' value='delete' />
      <input type='hidden' name='id' value='" . h($id) . "' />
      <button class='btn danger' type='submit'>Delete</button>
    </form>";
  }
  $body .= "</section>";

  admin_layout("Powers", $body);
  exit;
}

$q = trim((string)($_GET["q"] ?? ""));
if ($q !== "") {
  $stmt = $pdo->prepare("SELECT id,name,class_name,path,min_level FROM powers WHERE id LIKE ? OR name LIKE ? ORDER BY class_name ASC, name ASC LIMIT 500");
  $like = "%" . $q . "%";
  $stmt->execute([$like, $like]);
  $rows = $stmt->fetchAll();
} else {
  $rows = $pdo->query("SELECT id,name,class_name,path,min_level FROM powers ORDER BY class_name ASC, name ASC LIMIT 500")->fetchAll();
}

$body = "<section class='card'><div style='display:flex;justify-content:space-between;align-items:baseline;gap:.8rem;flex-wrap:wrap'>
  <h2>Powers</h2>
  <a class='btn primary' href='powers.php?action=edit'>Add power</a>
</div>
<form method='get' style='display:flex;gap:.6rem;flex-wrap:wrap;align-items:center'>
  <input name='q' placeholder='Search id/name...' value='" . h($q) . "' />
  <button class='btn' type='submit'>Search</button>
  <a class='btn' href='powers.php'>Clear</a>
</form>
<div class='muted'>Showing up to 500 rows.</div>
<table><thead><tr><th>ID</th><th>Name</th><th>Class</th><th>Min lvl</th><th>Path</th></tr></thead><tbody>";
foreach ($rows as $r) {
  $body .= "<tr>
    <td><a href='powers.php?action=edit&id=" . rawurlencode((string)$r["id"]) . "'>" . h((string)$r["id"]) . "</a></td>
    <td>" . h((string)$r["name"]) . "</td>
    <td class='muted'>" . h((string)$r["class_name"]) . "</td>
    <td class='muted'>" . h((string)($r["min_level"] ?? "")) . "</td>
    <td class='muted'>" . h((string)($r["path"] ?? "")) . "</td>
  </tr>";
}
$body .= "</tbody></table></section>";

admin_layout("Powers", $body);
