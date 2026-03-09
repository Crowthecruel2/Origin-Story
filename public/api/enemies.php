<?php
declare(strict_types=1);

require_once __DIR__ . "/util.php";
brighton_apply_cors();

try {
  $pdo = brighton_pdo();
  $rows = $pdo->query("SELECT * FROM enemies ORDER BY sort_order ASC, name ASC")->fetchAll();
  $enemies = [];

  $read_category = static function (array $row, array $tags): string {
    foreach ($tags as $tag) {
      if (!is_string($tag)) continue;
      if (stripos($tag, "category:") === 0) {
        $raw = trim(substr($tag, 9));
        if ($raw !== "") return $raw;
      }
    }
    $class = trim((string)($row["class_name"] ?? ""));
    if ($class !== "") return $class;
    $origin = trim((string)($row["origin"] ?? ""));
    if ($origin !== "") return $origin;
    return "Uncategorized";
  };

  foreach ($rows as $r) {
    $tags = brighton_decode_json($r["tags_json"], []);
    $stats = [
      // Player-aligned stat keys for GM tools.
      "level" => $r["level_num"] !== null ? (int)$r["level_num"] : null,
      "currentHealth" => $r["health"] !== null ? (int)$r["health"] : null,
      "energy" => $r["energy"] !== null ? (int)$r["energy"] : null,
      "reflexBase" => $r["dexterity"] !== null ? (int)$r["dexterity"] : null,
      "strengthBase" => $r["strength"] !== null ? (int)$r["strength"] : null,
      "smartsBase" => $r["smarts"] !== null ? (int)$r["smarts"] : null,
      "socialBase" => $r["social"] !== null ? (int)$r["social"] : null,
      "durabilityBase" => $r["durability"] !== null ? (int)$r["durability"] : null,
      "armorBase" => $r["armor"] !== null ? (int)$r["armor"] : null,
      // Optional compatibility fields for UIs that still read legacy names.
      "moveStat" => $r["move_stat"] !== null ? (int)$r["move_stat"] : null,
      "size" => $r["size"] !== null ? (int)$r["size"] : null,
    ];

    $enemies[] = [
      "id" => $r["id"],
      "name" => $r["name"],
      "class_name" => $r["class_name"],
      "origin" => $r["origin"],
      "category" => $read_category($r, $tags),
      "stats" => $stats,
      "level" => $r["level_num"] !== null ? (int)$r["level_num"] : null,
      "health" => $r["health"] !== null ? (int)$r["health"] : null,
      "energy" => $r["energy"] !== null ? (int)$r["energy"] : null,
      "move_stat" => $r["move_stat"] !== null ? (int)$r["move_stat"] : null,
      "armor" => $r["armor"] !== null ? (int)$r["armor"] : null,
      "dexterity" => $r["dexterity"] !== null ? (int)$r["dexterity"] : null,
      "strength" => $r["strength"] !== null ? (int)$r["strength"] : null,
      "smarts" => $r["smarts"] !== null ? (int)$r["smarts"] : null,
      "social" => $r["social"] !== null ? (int)$r["social"] : null,
      "durability" => $r["durability"] !== null ? (int)$r["durability"] : null,
      "size" => $r["size"] !== null ? (int)$r["size"] : null,
      "powers_text" => $r["powers_text"],
      "inventory_text" => $r["inventory_text"],
      "notes" => $r["notes"],
      "tags" => $tags,
      "sort_order" => $r["sort_order"] !== null ? (int)$r["sort_order"] : 0,
    ];
  }

  brighton_json_response(["enemies" => $enemies]);
} catch (Throwable $e) {
  brighton_fail("Failed to load enemies", 500, ["detail" => $e->getMessage()]);
}
