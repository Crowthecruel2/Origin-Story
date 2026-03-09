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
    $fid = trim((string)($_POST["id"] ?? ""));
    $name = trim((string)($_POST["name"] ?? ""));
    if ($fid === "" || $name === "") {
      admin_layout("Wargame Factions", "<section class='card'><h2>Error</h2><p class='muted'>id and name are required.</p></section>");
      exit;
    }

    $overview = json_decode((string)($_POST["overview_json"] ?? "[]"), true);
    $commands = json_decode((string)($_POST["command_abilities_json"] ?? "[]"), true);
    $pages = json_decode((string)($_POST["source_pages_json"] ?? "[]"), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      admin_layout("Wargame Factions", "<section class='card'><h2>Error</h2><p class='muted'>Invalid JSON in one of the fields.</p></section>");
      exit;
    }

    $stmt = $pdo->prepare(
      "INSERT INTO wargame_factions(id,name,starting_name,starting_amount,overview_json,command_abilities_json,source_pages_json)
       VALUES(?,?,?,?,?,?,?)
       ON DUPLICATE KEY UPDATE
         name=VALUES(name),starting_name=VALUES(starting_name),starting_amount=VALUES(starting_amount),
         overview_json=VALUES(overview_json),command_abilities_json=VALUES(command_abilities_json),source_pages_json=VALUES(source_pages_json)"
    );
    $stmt->execute([
      $fid,
      $name,
      ($_POST["starting_name"] ?? null) ?: null,
      ($_POST["starting_amount"] ?? null) !== "" ? (int)($_POST["starting_amount"]) : null,
      json_encode($overview, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      json_encode($commands, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      json_encode($pages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    header("Location: wargame-factions.php");
    exit;
  }

  if ($postAction === "delete") {
    $fid = (string)($_POST["id"] ?? "");
    $pdo->prepare("DELETE FROM wargame_factions WHERE id = ?")->execute([$fid]);
    header("Location: wargame-factions.php");
    exit;
  }
}

if ($action === "edit") {
  $row = [
    "id" => "",
    "name" => "",
    "starting_name" => "",
    "starting_amount" => "",
    "overview_json" => "[]",
    "command_abilities_json" => "[]",
    "source_pages_json" => "[]",
  ];
  if ($id !== "") {
    $stmt = $pdo->prepare("SELECT * FROM wargame_factions WHERE id = ?");
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if ($found) $row = $found;
  }

  $body = "<section class='card'><h2>" . ($id ? "Edit wargame faction" : "Add wargame faction") . "</h2>";
  $body .= "<form method='post' class='card' style='background:transparent;border:none;padding:0'>
    <input type='hidden' name='csrf' value='" . h(admin_csrf_token()) . "' />
    <input type='hidden' name='action' value='save' />
    <div class='row'>
      <label class='muted'>ID<input name='id' value='" . h((string)$row["id"]) . "' " . ($id ? "readonly" : "") . " /></label>
      <label class='muted'>Name<input name='name' value='" . h((string)$row["name"]) . "' /></label>
    </div>
    <div class='row'>
      <label class='muted'>Starting name<input name='starting_name' value='" . h((string)($row["starting_name"] ?? "")) . "' /></label>
      <label class='muted'>Starting amount<input name='starting_amount' value='" . h((string)($row["starting_amount"] ?? "")) . "' /></label>
    </div>
    <label class='muted'>Overview (JSON array)<textarea name='overview_json'>" . h((string)($row["overview_json"] ?? "[]")) . "</textarea></label>
    <label class='muted'>Command abilities (JSON array)<textarea name='command_abilities_json'>" . h((string)($row["command_abilities_json"] ?? "[]")) . "</textarea></label>
    <label class='muted'>Source pages (JSON array)<textarea name='source_pages_json'>" . h((string)($row["source_pages_json"] ?? "[]")) . "</textarea></label>
    <div style='display:flex;gap:.6rem;flex-wrap:wrap'>
      <button class='btn primary' type='submit'>Save</button>
      <a class='btn' href='wargame-factions.php'>Cancel</a>
    </div>
  </form>";
  if ($id) {
    $body .= "<form method='post' onsubmit='return confirm(\"Delete this faction? This will also delete its units.\")'>
      <input type='hidden' name='csrf' value='" . h(admin_csrf_token()) . "' />
      <input type='hidden' name='action' value='delete' />
      <input type='hidden' name='id' value='" . h($id) . "' />
      <button class='btn danger' type='submit'>Delete</button>
    </form>";
  }
  $body .= "</section>";
  admin_layout("Wargame Factions", $body);
  exit;
}

$rows = $pdo->query("SELECT id,name,starting_name,starting_amount FROM wargame_factions ORDER BY name ASC")->fetchAll();
$body = "<section class='card'><div style='display:flex;justify-content:space-between;align-items:baseline;gap:.8rem;flex-wrap:wrap'>
  <h2>Wargame Factions</h2>
  <a class='btn primary' href='wargame-factions.php?action=edit'>Add faction</a>
</div>
<table><thead><tr><th>ID</th><th>Name</th><th>Starting</th></tr></thead><tbody>";
foreach ($rows as $r) {
  $start = trim((string)($r["starting_name"] ?? "")) !== "" ? ($r["starting_name"] . ": " . (string)($r["starting_amount"] ?? "")) : "";
  $body .= "<tr>
    <td><a href='wargame-factions.php?action=edit&id=" . rawurlencode((string)$r["id"]) . "'>" . h((string)$r["id"]) . "</a></td>
    <td>" . h((string)$r["name"]) . "</td>
    <td class='muted'>" . h($start) . "</td>
  </tr>";
}
$body .= "</tbody></table></section>";

admin_layout("Wargame Factions", $body);

