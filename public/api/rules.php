<?php
declare(strict_types=1);

require_once __DIR__ . "/util.php";
brighton_apply_cors();

function rules_cmp_section_code(string $a, string $b): int {
  $pa = array_values(array_filter(explode(".", trim($a)), fn($v) => $v !== ""));
  $pb = array_values(array_filter(explode(".", trim($b)), fn($v) => $v !== ""));
  $max = max(count($pa), count($pb));
  for ($i = 0; $i < $max; $i++) {
    $sa = $pa[$i] ?? null;
    $sb = $pb[$i] ?? null;
    if ($sa === null) return -1;
    if ($sb === null) return 1;
    $na = ctype_digit($sa) ? (int)$sa : null;
    $nb = ctype_digit($sb) ? (int)$sb : null;
    if ($na !== null && $nb !== null) {
      if ($na < $nb) return -1;
      if ($na > $nb) return 1;
      continue;
    }
    $cmp = strcmp($sa, $sb);
    if ($cmp !== 0) return $cmp;
  }
  return 0;
}

try {
  $pdo = brighton_pdo();
  $q = trim((string)($_GET["q"] ?? ""));

  if ($q !== "") {
    $stmt = $pdo->prepare(
      "SELECT id,section_code,section_number,title,rules_html
       FROM rules_sections
       WHERE title LIKE ? OR rules_html LIKE ?"
    );
    $like = "%" . $q . "%";
    $stmt->execute([$like, $like]);
    $rows = $stmt->fetchAll();
  } else {
    $rows = $pdo->query(
      "SELECT id,section_code,section_number,title,rules_html
       FROM rules_sections"
    )->fetchAll();
  }
  usort($rows, function (array $a, array $b): int {
    $sa = (string)($a["section_code"] ?? $a["section_number"] ?? "");
    $sb = (string)($b["section_code"] ?? $b["section_number"] ?? "");
    $cmp = rules_cmp_section_code($sa, $sb);
    if ($cmp !== 0) return $cmp;
    return (int)$a["id"] <=> (int)$b["id"];
  });

  $sections = [];
  foreach ($rows as $r) {
    $sections[] = [
      "id" => (int)$r["id"],
      "number" => (string)($r["section_code"] ?? $r["section_number"] ?? ""),
      "title" => (string)$r["title"],
      "rules_html" => (string)$r["rules_html"],
    ];
  }

  brighton_json_response(["sections" => $sections]);
} catch (Throwable $e) {
  brighton_fail("Failed to load rules", 500, ["detail" => $e->getMessage()]);
}
