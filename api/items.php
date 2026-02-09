<?php
declare(strict_types=1);

require_once __DIR__ . "/util.php";
brighton_apply_cors();

try {
  $pdo = brighton_pdo();
  $rows = $pdo->query("SELECT * FROM items ORDER BY class_name ASC, name ASC")->fetchAll();
  $items = [];
  foreach ($rows as $r) {
    $items[] = [
      "id" => $r["id"],
      "name" => $r["name"],
      "from_power" => $r["from_power"],
      "class_name" => $r["class_name"],
      "description" => $r["description"],
      "effects" => $r["effects"],
      "cost" => $r["cost"],
      "prerequisites" => brighton_decode_json($r["prerequisites_json"], []),
    ];
  }
  brighton_json_response(["items" => $items]);
} catch (Throwable $e) {
  brighton_fail("Failed to load items", 500, ["detail" => $e->getMessage()]);
}
