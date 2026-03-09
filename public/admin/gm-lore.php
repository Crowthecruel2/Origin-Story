<?php
declare(strict_types=1);

require_once __DIR__ . "/util.php";
admin_require_login();

$pdo = brighton_pdo();

$action = (string)($_GET["action"] ?? "list");
$slug = (string)($_GET["slug"] ?? "");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  admin_check_csrf();
  $postAction = (string)($_POST["action"] ?? "");

  if ($postAction === "save") {
    $s = trim((string)($_POST["slug"] ?? ""));
    $title = trim((string)($_POST["title"] ?? ""));
    $content = (string)($_POST["content"] ?? "");
    if ($s === "" || $title === "" || trim($content) === "") {
      admin_layout("GM Lore", "<section class='card'><h2>Error</h2><p class='muted'>slug, title, and content are required.</p></section>");
      exit;
    }
    $eventDate = trim((string)($_POST["event_date"] ?? ""));
    if ($eventDate !== "" && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
      admin_layout("GM Lore", "<section class='card'><h2>Error</h2><p class='muted'>event_date must be YYYY-MM-DD.</p></section>");
      exit;
    }
    $isEra = isset($_POST["is_era"]) ? 1 : 0;
    $eraBg = trim((string)($_POST["era_bg_color"] ?? ""));
    $eraText = trim((string)($_POST["era_text_color"] ?? ""));
    if ($eraBg !== "" && !preg_match('/^#[0-9A-Fa-f]{6}$/', $eraBg)) {
      admin_layout("GM Lore", "<section class='card'><h2>Error</h2><p class='muted'>Era background color must be a hex value like #112233.</p></section>");
      exit;
    }
    if ($eraText !== "" && !preg_match('/^#[0-9A-Fa-f]{6}$/', $eraText)) {
      admin_layout("GM Lore", "<section class='card'><h2>Error</h2><p class='muted'>Era text color must be a hex value like #EAEAEA.</p></section>");
      exit;
    }
    $stmt = $pdo->prepare(
      "INSERT INTO gm_lore(slug,title,event_date,content,is_era,era_bg_color,era_text_color,sort_order,is_published) VALUES(?,?,?,?,?,?,?,?,?)
       ON DUPLICATE KEY UPDATE title=VALUES(title), event_date=VALUES(event_date), content=VALUES(content), is_era=VALUES(is_era), era_bg_color=VALUES(era_bg_color), era_text_color=VALUES(era_text_color), sort_order=VALUES(sort_order), is_published=VALUES(is_published)"
    );
    $stmt->execute([
      $s,
      $title,
      $eventDate !== "" ? $eventDate : null,
      $content,
      $isEra,
      $eraBg !== "" ? $eraBg : null,
      $eraText !== "" ? $eraText : null,
      ($_POST["sort_order"] ?? null) !== "" ? (int)($_POST["sort_order"]) : 0,
      isset($_POST["is_published"]) ? 1 : 0,
    ]);
    header("Location: gm-lore.php");
    exit;
  }

  if ($postAction === "delete") {
    $s = (string)($_POST["slug"] ?? "");
    $pdo->prepare("DELETE FROM gm_lore WHERE slug = ?")->execute([$s]);
    header("Location: gm-lore.php");
    exit;
  }
}

if ($action === "edit") {
  $row = ["slug" => "", "title" => "", "event_date" => "", "content" => "", "is_era" => 0, "era_bg_color" => "#224466", "era_text_color" => "#f0f6ff", "sort_order" => 0, "is_published" => 1];
  if ($slug !== "") {
    $stmt = $pdo->prepare("SELECT * FROM gm_lore WHERE slug = ?");
    $stmt->execute([$slug]);
    $found = $stmt->fetch();
    if ($found) $row = $found;
  }

  $published = ((int)($row["is_published"] ?? 0)) === 1 ? "checked" : "";
  $isEraChecked = ((int)($row["is_era"] ?? 0)) === 1 ? "checked" : "";
  $bgColor = (string)($row["era_bg_color"] ?? "");
  $textColor = (string)($row["era_text_color"] ?? "");
  if ($bgColor === "" || !preg_match('/^#[0-9A-Fa-f]{6}$/', $bgColor)) $bgColor = "#224466";
  if ($textColor === "" || !preg_match('/^#[0-9A-Fa-f]{6}$/', $textColor)) $textColor = "#f0f6ff";
  $body = "<section class='card'><h2>" . ($slug ? "Edit lore section" : "Add lore section") . "</h2>";
  $body .= "<form method='post' class='card' style='background:transparent;border:none;padding:0'>
    <input type='hidden' name='csrf' value='" . h(admin_csrf_token()) . "' />
    <input type='hidden' name='action' value='save' />
    <div class='row'>
      <label class='muted'>Slug<input name='slug' value='" . h((string)$row["slug"]) . "' " . ($slug ? "readonly" : "") . " /></label>
      <label class='muted'>Title<input name='title' value='" . h((string)$row["title"]) . "' /></label>
    </div>
    <div class='row'>
      <label class='muted'>Date<input type='date' name='event_date' value='" . h((string)($row["event_date"] ?? "")) . "' /></label>
      <label class='muted'>Sort order<input name='sort_order' value='" . h((string)($row["sort_order"] ?? 0)) . "' /></label>
      <label class='muted' style='display:flex;gap:.55rem;align-items:center'>
        <span>Published</span>
        <input type='checkbox' name='is_published' value='1' {$published} style='width:auto' />
      </label>
    </div>
    <div class='row'>
      <label class='muted' style='display:flex;gap:.55rem;align-items:center'>
        <span>Mark as Era</span>
        <input type='checkbox' name='is_era' value='1' {$isEraChecked} style='width:auto' />
      </label>
      <label class='muted'>Era background color<input type='color' name='era_bg_color' value='" . h($bgColor) . "' /></label>
      <label class='muted'>Era text color<input type='color' name='era_text_color' value='" . h($textColor) . "' /></label>
    </div>
    <label class='muted'>Content<textarea name='content' style='min-height:340px'>" . h((string)$row["content"]) . "</textarea></label>
    <div style='display:flex;gap:.6rem;flex-wrap:wrap'>
      <button class='btn primary' type='submit'>Save</button>
      <a class='btn' href='gm-lore.php'>Cancel</a>
    </div>
  </form>";
  if ($slug) {
    $body .= "<form method='post' onsubmit='return confirm(\"Delete this lore section?\")'>
      <input type='hidden' name='csrf' value='" . h(admin_csrf_token()) . "' />
      <input type='hidden' name='action' value='delete' />
      <input type='hidden' name='slug' value='" . h($slug) . "' />
      <button class='btn danger' type='submit'>Delete</button>
    </form>";
  }
  $body .= "</section>";
  admin_layout("GM Lore", $body);
  exit;
}

$rows = $pdo->query("SELECT slug,title,event_date,is_era,sort_order,is_published FROM gm_lore ORDER BY (event_date IS NULL) ASC, event_date ASC, sort_order ASC, title ASC")->fetchAll();
$body = "<section class='card'><div style='display:flex;justify-content:space-between;align-items:baseline;gap:.8rem;flex-wrap:wrap'>
  <h2>GM Lore</h2>
  <a class='btn primary' href='gm-lore.php?action=edit'>Add lore section</a>
</div>
<table><thead><tr><th>Slug</th><th>Title</th><th>Date</th><th>Era</th><th>Order</th><th>Published</th></tr></thead><tbody>";
foreach ($rows as $r) {
  $body .= "<tr>
    <td><a href='gm-lore.php?action=edit&slug=" . rawurlencode((string)$r["slug"]) . "'>" . h((string)$r["slug"]) . "</a></td>
    <td>" . h((string)$r["title"]) . "</td>
    <td class='muted'>" . h((string)($r["event_date"] ?? "")) . "</td>
    <td class='muted'>" . (((int)($r["is_era"] ?? 0)) === 1 ? "yes" : "no") . "</td>
    <td class='muted'>" . h((string)($r["sort_order"] ?? 0)) . "</td>
    <td class='muted'>" . (((int)($r["is_published"] ?? 0)) === 1 ? "yes" : "no") . "</td>
  </tr>";
}
$body .= "</tbody></table></section>";

admin_layout("GM Lore", $body);
