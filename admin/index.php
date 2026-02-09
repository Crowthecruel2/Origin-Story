<?php
declare(strict_types=1);

require_once __DIR__ . "/util.php";
admin_require_login();

$counts = [];
$err = null;
try {
  $pdo = brighton_pdo();
  foreach (["powers","items","rpg_factions","wargame_factions","wargame_units"] as $t) {
    $counts[$t] = (int)$pdo->query("SELECT COUNT(*) c FROM {$t}")->fetch()["c"];
  }
} catch (Throwable $e) {
  $err = $e->getMessage();
}

$body = "<section class='card'><h2>Overview</h2>";
if ($err) $body .= "<div class='muted' style='color:#fecaca'>DB error: " . h($err) . "</div>";
$body .= "<div class='row'>
  <div class='card'><strong>Powers</strong><div class='muted'>" . h((string)($counts["powers"] ?? "?")) . " rows</div><a class='btn' href='powers.php'>Manage</a></div>
  <div class='card'><strong>Items</strong><div class='muted'>" . h((string)($counts["items"] ?? "?")) . " rows</div><a class='btn' href='items.php'>Manage</a></div>
  <div class='card'><strong>RPG Factions</strong><div class='muted'>" . h((string)($counts["rpg_factions"] ?? "?")) . " rows</div><a class='btn' href='rpg-factions.php'>Manage</a></div>
  <div class='card'><strong>Wargame Factions</strong><div class='muted'>" . h((string)($counts["wargame_factions"] ?? "?")) . " rows</div><a class='btn' href='wargame-factions.php'>Manage</a></div>
  <div class='card'><strong>Wargame Units</strong><div class='muted'>" . h((string)($counts["wargame_units"] ?? "?")) . " rows</div><a class='btn' href='wargame-units.php'>Manage</a></div>
</div></section>";

admin_layout("Admin Home", $body);

