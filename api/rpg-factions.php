<?php
declare(strict_types=1);

require_once __DIR__ . "/util.php";
brighton_apply_cors();

try {
  $pdo = brighton_pdo();
  $rows = $pdo->query("SELECT slug,name,blurb,page FROM rpg_factions ORDER BY name ASC")->fetchAll();
  brighton_json_response(["factions" => $rows]);
} catch (Throwable $e) {
  brighton_fail("Failed to load factions", 500, ["detail" => $e->getMessage()]);
}
