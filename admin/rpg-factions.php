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
    $name = trim((string)($_POST["name"] ?? ""));
    if ($s === "" || $name === "") {
      admin_layout("RPG Factions", "<section class='card'><h2>Error</h2><p class='muted'>slug and name are required.</p></section>");
      exit;
    }
    $stmt = $pdo->prepare(
      "INSERT INTO rpg_factions(slug,name,blurb,page) VALUES(?,?,?,?)
       ON DUPLICATE KEY UPDATE name=VALUES(name), blurb=VALUES(blurb), page=VALUES(page)"
    );
    $stmt->execute([
      $s,
      $name,
      ($_POST['blurb'] ?? null) ?: null,
      ($_POST['page'] ?? null) ?: null,
    ]);
    header("Location: rpg-factions.php");
    exit;
  }

  if ($postAction === "delete") {
    $s = (string)($_POST["slug"] ?? "");
    $pdo->prepare("DELETE FROM rpg_factions WHERE slug = ?")->execute([$s]);
    header("Location: rpg-factions.php");
    exit;
  }
}

if ($action === "edit") {
  $row = ["slug" => "", "name" => "", "blurb" => "", "page" => ""];
  if ($slug !== "") {
    $stmt = $pdo->prepare("SELECT * FROM rpg_factions WHERE slug = ?");
    $stmt->execute([$slug]);
    $found = $stmt->fetch();
    if ($found) $row = $found;
  }

  $body = "<section class='card'><h2>" . ($slug ? "Edit faction" : "Add faction") . "</h2>";
  $body .= "<form method='post' class='card' style='background:transparent;border:none;padding:0'>
    <input type='hidden' name='csrf' value='" . h(admin_csrf_token()) . "' />
    <input type='hidden' name='action' value='save' />
    <div class='row'>
      <label class='muted'>Slug<input name='slug' value='" . h((string)$row["slug"]) . "' " . ($slug ? "readonly" : "") . " /></label>
      <label class='muted'>Name<input name='name' value='" . h((string)$row["name"]) . "' /></label>
    </div>
    <label class='muted'>Blurb<textarea name='blurb'>" . h((string)($row["blurb"] ?? "")) . "</textarea></label>
    <label class='muted'>Page path<input name='page' value='" . h((string)($row["page"] ?? "")) . "' /></label>
    <div style='display:flex;gap:.6rem;flex-wrap:wrap'>
      <button class='btn primary' type='submit'>Save</button>
      <a class='btn' href='rpg-factions.php'>Cancel</a>
    </div>
  </form>";
  if ($slug) {
    $body .= "<form method='post' onsubmit='return confirm(\"Delete this faction?\")'>
      <input type='hidden' name='csrf' value='" . h(admin_csrf_token()) . "' />
      <input type='hidden' name='action' value='delete' />
      <input type='hidden' name='slug' value='" . h($slug) . "' />
      <button class='btn danger' type='submit'>Delete</button>
    </form>";
  }
  $body .= "</section>";
  admin_layout("RPG Factions", $body);
  exit;
}

$rows = $pdo->query("SELECT slug,name,page FROM rpg_factions ORDER BY name ASC")->fetchAll();
$body = "<section class='card'><div style='display:flex;justify-content:space-between;align-items:baseline;gap:.8rem;flex-wrap:wrap'>
  <h2>RPG Factions</h2>
  <a class='btn primary' href='rpg-factions.php?action=edit'>Add faction</a>
</div>
<table><thead><tr><th>Slug</th><th>Name</th><th>Page</th></tr></thead><tbody>";
foreach ($rows as $r) {
  $body .= "<tr>
    <td><a href='rpg-factions.php?action=edit&slug=" . rawurlencode((string)$r["slug"]) . "'>" . h((string)$r["slug"]) . "</a></td>
    <td>" . h((string)$r["name"]) . "</td>
    <td class='muted'>" . h((string)($r["page"] ?? "")) . "</td>
  </tr>";
}
$body .= "</tbody></table></section>";

admin_layout("RPG Factions", $body);

