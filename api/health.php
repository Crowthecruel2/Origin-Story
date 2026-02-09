<?php
declare(strict_types=1);

require_once __DIR__ . "/util.php";
brighton_apply_cors();

try {
  $pdo = brighton_pdo();
  $counts = [];
  foreach (["powers","items","rpg_factions","wargame_factions","wargame_units"] as $t) {
    $counts[$t] = (int)$pdo->query("SELECT COUNT(*) c FROM {$t}")->fetch()["c"];
  }
  brighton_json_response(["ok" => true, "counts" => $counts]);
} catch (Throwable $e) {
  brighton_fail("DB connection failed", 500, ["detail" => $e->getMessage()]);
}
