<?php
declare(strict_types=1);

require_once __DIR__ . "/util.php";
brighton_apply_cors();

try {
  $pdo = brighton_pdo();

  $meta = $pdo->query("SELECT `key`,`value` FROM meta")->fetchAll();
  $metaMap = [];
  foreach ($meta as $m) $metaMap[(string)$m["key"]] = (string)$m["value"];

  $factions = $pdo->query("SELECT * FROM wargame_factions ORDER BY name ASC")->fetchAll();
  $units = $pdo->query("SELECT * FROM wargame_units ORDER BY faction_id ASC, name ASC")->fetchAll();

  $factionOut = [];
  foreach ($factions as $f) {
    $starting = null;
    if ($f["starting_name"] !== null || $f["starting_amount"] !== null) {
      $starting = [
        "name" => $f["starting_name"],
        "amount" => $f["starting_amount"] !== null ? (int)$f["starting_amount"] : null,
      ];
    }
    $factionOut[] = [
      "id" => $f["id"],
      "name" => $f["name"],
      "starting" => $starting,
      "overview" => brighton_decode_json($f["overview_json"], []),
      "commandAbilities" => brighton_decode_json($f["command_abilities_json"], []),
      "sourcePages" => brighton_decode_json($f["source_pages_json"], []),
    ];
  }

  $unitOut = [];
  foreach ($units as $u) {
    $unitOut[] = [
      "id" => $u["id"],
      "name" => $u["name"],
      "factionId" => $u["faction_id"],
      "startingEnergy" => $u["starting_energy"] !== null ? (int)$u["starting_energy"] : null,
      "headerNumbers" => brighton_decode_json($u["header_numbers_json"], []),
      "sections" => brighton_decode_json($u["sections_json"], new stdClass()),
      "raw" => $u["raw"],
      "sourcePage" => $u["source_page"] !== null ? (int)$u["source_page"] : null,
    ];
  }

  brighton_json_response([
    "schemaVersion" => 1,
    "source" => [
      "pdf" => $metaMap["wargame_source_pdf"] ?? "",
    ],
    "factions" => $factionOut,
    "units" => $unitOut,
  ]);
} catch (Throwable $e) {
  brighton_fail("Failed to load wargame data", 500, ["detail" => $e->getMessage()]);
}
