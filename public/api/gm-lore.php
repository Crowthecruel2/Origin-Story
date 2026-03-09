<?php
declare(strict_types=1);

require_once __DIR__ . "/util.php";
brighton_apply_cors();

try {
  $pdo = brighton_pdo();
  $rows = $pdo->query("SELECT slug,title,event_date,content,is_era,era_bg_color,era_text_color,sort_order FROM gm_lore WHERE is_published = 1 ORDER BY (event_date IS NULL) ASC, event_date ASC, sort_order ASC, title ASC")->fetchAll();
  $out = [];
  foreach ($rows as $r) {
    $out[] = [
      "slug" => $r["slug"],
      "title" => $r["title"],
      "event_date" => $r["event_date"],
      "content" => $r["content"],
      "is_era" => ((int)($r["is_era"] ?? 0)) === 1,
      "era_bg_color" => $r["era_bg_color"],
      "era_text_color" => $r["era_text_color"],
      "sort_order" => $r["sort_order"] !== null ? (int)$r["sort_order"] : 0,
    ];
  }
  brighton_json_response(["sections" => $out]);
} catch (Throwable $e) {
  brighton_fail("Failed to load GM lore", 500, ["detail" => $e->getMessage()]);
}
