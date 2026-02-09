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
    $iid = trim((string)($_POST["id"] ?? ""));
    $name = trim((string)($_POST["name"] ?? ""));
    if ($iid === "" || $name === "") {
      admin_layout("Items", "<section class='card'><h2>Error</h2><p class='muted'>id and name are required.</p></section>");
      exit;
    }
    $prereqRaw = (string)($_POST["prerequisites_json"] ?? "[]");
    $prereqs = json_decode($prereqRaw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      admin_layout("Items", "<section class='card'><h2>Error</h2><p class='muted'>Invalid prerequisites JSON.</p></section>");
      exit;
    }

    $stmt = $pdo->prepare(
      "INSERT INTO items(id,name,from_power,class_name,description,effects,cost,prerequisites_json)
       VALUES(?,?,?,?,?,?,?,?)
       ON DUPLICATE KEY UPDATE
         name=VALUES(name),from_power=VALUES(from_power),class_name=VALUES(class_name),
         description=VALUES(description),effects=VALUES(effects),cost=VALUES(cost),
         prerequisites_json=VALUES(prerequisites_json)"
    );
    $stmt->execute([
      $iid,
      $name,
      ($_POST["from_power"] ?? null) ?: null,
      ($_POST["class_name"] ?? null) ?: null,
      ($_POST["description"] ?? null) ?: null,
      ($_POST["effects"] ?? null) ?: null,
      ($_POST["cost"] ?? null) ?: null,
      json_encode($prereqs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    header("Location: items.php");
    exit;
  }

  if ($postAction === "delete") {
    $iid = (string)($_POST["id"] ?? "");
    $pdo->prepare("DELETE FROM items WHERE id = ?")->execute([$iid]);
    header("Location: items.php");
    exit;
  }
}

if ($action === "edit") {
  $row = [
    "id" => "",
    "name" => "",
    "from_power" => "",
    "class_name" => "",
    "description" => "",
    "effects" => "",
    "cost" => "",
    "prerequisites_json" => "[]",
  ];
  if ($id !== "") {
    $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if ($found) $row = $found;
  }
  $body = "<section class='card'><h2>" . ($id ? "Edit item" : "Add item") . "</h2>";
  $body .= "<form method='post' class='card' style='background:transparent;border:none;padding:0'>
    <input type='hidden' name='csrf' value='" . h(admin_csrf_token()) . "' />
    <input type='hidden' name='action' value='save' />
    <div class='row'>
      <label class='muted'>ID<input name='id' value='" . h((string)$row["id"]) . "' " . ($id ? "readonly" : "") . " /></label>
      <label class='muted'>Name<input name='name' value='" . h((string)$row["name"]) . "' /></label>
    </div>
    <div class='row'>
      <label class='muted'>From power<input name='from_power' value='" . h((string)($row["from_power"] ?? "")) . "' /></label>
      <label class='muted'>Class<input name='class_name' value='" . h((string)($row["class_name"] ?? "")) . "' /></label>
    </div>
    <label class='muted'>Description<textarea name='description'>" . h((string)($row["description"] ?? "")) . "</textarea></label>
    <label class='muted'>Effects<textarea name='effects'>" . h((string)($row["effects"] ?? "")) . "</textarea></label>
    <div class='row'>
      <label class='muted'>Cost<input name='cost' value='" . h((string)($row["cost"] ?? "")) . "' /></label>
      <label class='muted'>Prerequisites (JSON array)<textarea name='prerequisites_json'>" . h((string)($row["prerequisites_json"] ?? "[]")) . "</textarea></label>
    </div>
    <div style='display:flex;gap:.6rem;flex-wrap:wrap'>
      <button class='btn primary' type='submit'>Save</button>
      <a class='btn' href='items.php'>Cancel</a>
    </div>
  </form>";
  if ($id) {
    $body .= "<form method='post' onsubmit='return confirm(\"Delete this item?\")'>
      <input type='hidden' name='csrf' value='" . h(admin_csrf_token()) . "' />
      <input type='hidden' name='action' value='delete' />
      <input type='hidden' name='id' value='" . h($id) . "' />
      <button class='btn danger' type='submit'>Delete</button>
    </form>";
  }
  $body .= "</section>";
  admin_layout("Items", $body);
  exit;
}

$q = trim((string)($_GET["q"] ?? ""));
if ($q !== "") {
  $stmt = $pdo->prepare("SELECT id,name,class_name,from_power,cost FROM items WHERE id LIKE ? OR name LIKE ? ORDER BY name ASC LIMIT 500");
  $like = "%" . $q . "%";
  $stmt->execute([$like, $like]);
  $rows = $stmt->fetchAll();
} else {
  $rows = $pdo->query("SELECT id,name,class_name,from_power,cost FROM items ORDER BY class_name ASC, name ASC LIMIT 500")->fetchAll();
}

$body = "<section class='card'><div style='display:flex;justify-content:space-between;align-items:baseline;gap:.8rem;flex-wrap:wrap'>
  <h2>Items</h2>
  <a class='btn primary' href='items.php?action=edit'>Add item</a>
</div>
<form method='get' style='display:flex;gap:.6rem;flex-wrap:wrap;align-items:center'>
  <input name='q' placeholder='Search id/name...' value='" . h($q) . "' />
  <button class='btn' type='submit'>Search</button>
  <a class='btn' href='items.php'>Clear</a>
</form>
<div class='muted'>Showing up to 500 rows.</div>
<table><thead><tr><th>ID</th><th>Name</th><th>Class</th><th>From</th><th>Cost</th></tr></thead><tbody>";
foreach ($rows as $r) {
  $body .= "<tr>
    <td><a href='items.php?action=edit&id=" . rawurlencode((string)$r["id"]) . "'>" . h((string)$r["id"]) . "</a></td>
    <td>" . h((string)$r["name"]) . "</td>
    <td class='muted'>" . h((string)($r["class_name"] ?? "")) . "</td>
    <td class='muted'>" . h((string)($r["from_power"] ?? "")) . "</td>
    <td class='muted'>" . h((string)($r["cost"] ?? "")) . "</td>
  </tr>";
}
$body .= "</tbody></table></section>";

admin_layout("Items", $body);

