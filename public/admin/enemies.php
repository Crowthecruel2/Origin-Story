<?php
declare(strict_types=1);

require_once __DIR__ . "/util.php";
admin_require_login();

$pdo = brighton_pdo();

/**
 * Accept JSON array input, or plain comma/newline-separated tags.
 *
 * @return array{ok:bool,tags:array<int,string>}
 */
function enemies_parse_tags_input(string $raw): array {
  $trimmed = trim($raw);
  if ($trimmed === "") return ["ok" => true, "tags" => []];

  $decoded = json_decode($trimmed, true);
  if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
    $tags = [];
    foreach ($decoded as $v) {
      if (!is_string($v)) continue;
      $t = trim($v);
      if ($t !== "") $tags[] = $t;
    }
    return ["ok" => true, "tags" => $tags];
  }

  // Fallback: allow plain text tags split by new lines and/or commas.
  $parts = preg_split('/[\r\n,]+/', $trimmed) ?: [];
  $tags = [];
  foreach ($parts as $part) {
    $t = trim((string)$part);
    if ($t !== "") $tags[] = $t;
  }
  if ($tags) return ["ok" => true, "tags" => $tags];

  return ["ok" => false, "tags" => []];
}

$action = (string)($_GET["action"] ?? "list");
$id = (string)($_GET["id"] ?? "");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  admin_check_csrf();
  $postAction = (string)($_POST["action"] ?? "");

  if ($postAction === "save") {
    $eid = trim((string)($_POST["id"] ?? ""));
    $name = trim((string)($_POST["name"] ?? ""));
    if ($eid === "" || $name === "") {
      admin_layout("Enemies", "<section class='card'><h2>Error</h2><p class='muted'>id and name are required.</p></section>");
      exit;
    }

    $tagsResult = enemies_parse_tags_input((string)($_POST["tags_json"] ?? "[]"));
    if (!$tagsResult["ok"]) {
      admin_layout("Enemies", "<section class='card'><h2>Error</h2><p class='muted'>Invalid tags JSON.</p></section>");
      exit;
    }
    $tags = $tagsResult["tags"];

    $stmt = $pdo->prepare(
      "INSERT INTO enemies(
        id,name,class_name,origin,level_num,health,energy,move_stat,armor,dexterity,strength,size,
        smarts,social,durability,powers_text,inventory_text,notes,tags_json,sort_order
      ) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE
        name=VALUES(name),class_name=VALUES(class_name),origin=VALUES(origin),level_num=VALUES(level_num),
        health=VALUES(health),energy=VALUES(energy),move_stat=VALUES(move_stat),armor=VALUES(armor),
        dexterity=VALUES(dexterity),strength=VALUES(strength),size=VALUES(size),smarts=VALUES(smarts),
        social=VALUES(social),durability=VALUES(durability),powers_text=VALUES(powers_text),
        inventory_text=VALUES(inventory_text),notes=VALUES(notes),tags_json=VALUES(tags_json),sort_order=VALUES(sort_order)"
    );
    $stmt->execute([
      $eid,
      $name,
      ($_POST["class_name"] ?? null) ?: null,
      ($_POST["origin"] ?? null) ?: null,
      ($_POST["level"] ?? null) !== "" ? (int)($_POST["level"]) : null,
      ($_POST["health"] ?? null) !== "" ? (int)($_POST["health"]) : null,
      ($_POST["energy"] ?? null) !== "" ? (int)($_POST["energy"]) : null,
      ($_POST["move_stat"] ?? null) !== "" ? (int)($_POST["move_stat"]) : null,
      ($_POST["armor"] ?? null) !== "" ? (int)($_POST["armor"]) : null,
      ($_POST["dexterity"] ?? null) !== "" ? (int)($_POST["dexterity"]) : null,
      ($_POST["strength"] ?? null) !== "" ? (int)($_POST["strength"]) : null,
      ($_POST["size"] ?? null) !== "" ? (int)($_POST["size"]) : null,
      ($_POST["smarts"] ?? null) !== "" ? (int)($_POST["smarts"]) : null,
      ($_POST["social"] ?? null) !== "" ? (int)($_POST["social"]) : null,
      ($_POST["durability"] ?? null) !== "" ? (int)($_POST["durability"]) : null,
      ($_POST["powers_text"] ?? null) ?: null,
      ($_POST["inventory_text"] ?? null) ?: null,
      ($_POST["notes"] ?? null) ?: null,
      json_encode($tags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      ($_POST["sort_order"] ?? null) !== "" ? (int)($_POST["sort_order"]) : 0,
    ]);
    header("Location: enemies.php");
    exit;
  }

  if ($postAction === "delete") {
    $eid = (string)($_POST["id"] ?? "");
    $pdo->prepare("DELETE FROM enemies WHERE id = ?")->execute([$eid]);
    header("Location: enemies.php");
    exit;
  }
}

if ($action === "edit") {
  $row = [
    "id" => "",
    "name" => "",
    "class_name" => "",
    "origin" => "",
    "level_num" => "",
    "health" => "",
    "energy" => "",
    "move_stat" => "",
    "armor" => "",
    "dexterity" => "",
    "strength" => "",
    "smarts" => "",
    "social" => "",
    "durability" => "",
    "size" => "",
    "powers_text" => "",
    "inventory_text" => "",
    "notes" => "",
    "tags_json" => "[]",
    "sort_order" => 0,
  ];
  if ($id !== "") {
    $stmt = $pdo->prepare("SELECT * FROM enemies WHERE id = ?");
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if ($found) $row = $found;
  }

  $body = "<section class='card'><h2>" . ($id ? "Edit enemy" : "Add enemy") . "</h2>";
  $body .= "<form method='post' class='card' style='background:transparent;border:none;padding:0'>
    <input type='hidden' name='csrf' value='" . h(admin_csrf_token()) . "' />
    <input type='hidden' name='action' value='save' />
    <div class='row'>
      <label class='muted'>ID<input name='id' value='" . h((string)$row["id"]) . "' " . ($id ? "readonly" : "") . " /></label>
      <label class='muted'>Name<input name='name' value='" . h((string)$row["name"]) . "' /></label>
    </div>
    <div class='row'>
      <label class='muted'>Class<input name='class_name' value='" . h((string)($row["class_name"] ?? "")) . "' /></label>
      <label class='muted'>Origin<input name='origin' value='" . h((string)($row["origin"] ?? "")) . "' /></label>
    </div>
    <div class='row'>
      <label class='muted'>Sort order<input name='sort_order' value='" . h((string)($row["sort_order"] ?? 0)) . "' /></label>
      <label class='muted'>Level<input name='level' value='" . h((string)($row["level_num"] ?? "")) . "' /></label>
    </div>
    <div class='row'>
      <label class='muted'>Health<input name='health' value='" . h((string)($row["health"] ?? "")) . "' /></label>
      <label class='muted'>Energy<input name='energy' value='" . h((string)($row["energy"] ?? "")) . "' /></label>
    </div>
    <div class='row'>
      <label class='muted'>Move<input name='move_stat' value='" . h((string)($row["move_stat"] ?? "")) . "' /></label>
      <label class='muted'>Armor<input name='armor' value='" . h((string)($row["armor"] ?? "")) . "' /></label>
    </div>
    <div class='row'>
      <label class='muted'>Dexterity<input name='dexterity' value='" . h((string)($row["dexterity"] ?? "")) . "' /></label>
      <label class='muted'>Strength<input name='strength' value='" . h((string)($row["strength"] ?? "")) . "' /></label>
    </div>
    <div class='row'>
      <label class='muted'>Smarts<input name='smarts' value='" . h((string)($row["smarts"] ?? "")) . "' /></label>
      <label class='muted'>Social<input name='social' value='" . h((string)($row["social"] ?? "")) . "' /></label>
    </div>
    <label class='muted'>Durability<input name='durability' value='" . h((string)($row["durability"] ?? "")) . "' /></label>
    <label class='muted'>Size<input name='size' value='" . h((string)($row["size"] ?? "")) . "' /></label>
    <label class='muted'>Powers (text block)<textarea name='powers_text' style='min-height:220px'>" . h((string)($row["powers_text"] ?? "")) . "</textarea></label>
    <label class='muted'>Inventory / Gear<textarea name='inventory_text'>" . h((string)($row["inventory_text"] ?? "")) . "</textarea></label>
    <label class='muted'>Notes<textarea name='notes'>" . h((string)($row["notes"] ?? "")) . "</textarea></label>
    <label class='muted'>Tags (JSON array or comma/newline list)<textarea name='tags_json'>" . h((string)($row["tags_json"] ?? "[]")) . "</textarea></label>
    <div style='display:flex;gap:.6rem;flex-wrap:wrap'>
      <button class='btn primary' type='submit'>Save</button>
      <a class='btn' href='enemies.php'>Cancel</a>
    </div>
  </form>";
  if ($id) {
    $body .= "<form method='post' onsubmit='return confirm(\"Delete this enemy?\")'>
      <input type='hidden' name='csrf' value='" . h(admin_csrf_token()) . "' />
      <input type='hidden' name='action' value='delete' />
      <input type='hidden' name='id' value='" . h($id) . "' />
      <button class='btn danger' type='submit'>Delete</button>
    </form>";
  }
  $body .= "</section>";
  admin_layout("Enemies", $body);
  exit;
}

$q = trim((string)($_GET["q"] ?? ""));
if ($q !== "") {
  $stmt = $pdo->prepare("SELECT id,name,class_name,origin,level_num FROM enemies WHERE id LIKE ? OR name LIKE ? ORDER BY sort_order ASC, name ASC LIMIT 500");
  $like = "%" . $q . "%";
  $stmt->execute([$like, $like]);
  $rows = $stmt->fetchAll();
} else {
  $rows = $pdo->query("SELECT id,name,class_name,origin,level_num FROM enemies ORDER BY sort_order ASC, name ASC LIMIT 500")->fetchAll();
}

$body = "<section class='card'><div style='display:flex;justify-content:space-between;align-items:baseline;gap:.8rem;flex-wrap:wrap'>
  <h2>Enemies</h2>
  <a class='btn primary' href='enemies.php?action=edit'>Add enemy</a>
</div>
<form method='get' style='display:flex;gap:.6rem;flex-wrap:wrap;align-items:center'>
  <input name='q' placeholder='Search id/name...' value='" . h($q) . "' />
  <button class='btn' type='submit'>Search</button>
  <a class='btn' href='enemies.php'>Clear</a>
</form>
<div class='muted'>Showing up to 500 rows.</div>
<table><thead><tr><th>ID</th><th>Name</th><th>Class</th><th>Origin</th><th>Level</th></tr></thead><tbody>";
foreach ($rows as $r) {
  $body .= "<tr>
    <td><a href='enemies.php?action=edit&id=" . rawurlencode((string)$r["id"]) . "'>" . h((string)$r["id"]) . "</a></td>
    <td>" . h((string)$r["name"]) . "</td>
    <td class='muted'>" . h((string)($r["class_name"] ?? "")) . "</td>
    <td class='muted'>" . h((string)($r["origin"] ?? "")) . "</td>
    <td class='muted'>" . h((string)($r["level_num"] ?? "")) . "</td>
  </tr>";
}
$body .= "</tbody></table></section>";

admin_layout("Enemies", $body);
