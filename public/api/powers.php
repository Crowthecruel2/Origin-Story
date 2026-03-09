<?php
declare(strict_types=1);

require_once __DIR__ . "/util.php";
brighton_apply_cors();

try {
  $pdo = brighton_pdo();

  $classes = $pdo->query("SELECT name FROM power_classes ORDER BY name ASC")->fetchAll();
  $powers = $pdo->query("SELECT * FROM powers ORDER BY class_name ASC, name ASC")->fetchAll();
  $levels = $pdo->query("SELECT * FROM power_levels ORDER BY power_id ASC, idx ASC")->fetchAll();

  $levelsByPower = [];
  foreach ($levels as $lvl) {
    $pid = (string)$lvl["power_id"];
    if (!isset($levelsByPower[$pid])) $levelsByPower[$pid] = [];
    $levelsByPower[$pid][] = [
      "level" => $lvl["level"] !== null ? (int)$lvl["level"] : null,
      "cost" => $lvl["cost"] !== null ? (int)$lvl["cost"] : null,
      "text" => $lvl["text"],
    ];
  }

  $powersByClass = [];
  foreach ($powers as $p) {
    $className = (string)$p["class_name"];
    if (!isset($powersByClass[$className])) $powersByClass[$className] = [];
    $pid = (string)$p["id"];
    $powersByClass[$className][] = [
      "id" => $pid,
      "name" => $p["name"],
      "class_name" => $className,
      "path" => $p["path"],
      "description" => $p["description"],
      "content" => $p["content"],
      "min_level" => $p["min_level"] !== null ? (int)$p["min_level"] : null,
      "prerequisites" => brighton_decode_json($p["prerequisites_json"], []),
      "tags" => brighton_decode_json($p["tags_json"], []),
      "all_sub_powers" => $levelsByPower[$pid] ?? [],
    ];
  }

  $classRows = [];
  foreach ($classes as $c) {
    $name = (string)$c["name"];
    $classRows[] = [
      "class_name" => $name,
      "all_class_powers" => $powersByClass[$name] ?? [],
    ];
  }

  brighton_json_response([
    "schemaVersion" => 8,
    "classes" => $classRows,
  ]);
} catch (Throwable $e) {
  brighton_fail("Failed to load powers", 500, ["detail" => $e->getMessage()]);
}
