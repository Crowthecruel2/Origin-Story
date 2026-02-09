<?php
declare(strict_types=1);

require_once __DIR__ . "/db.php";

function brighton_apply_cors(): void {
  $origin = (string)($_SERVER["HTTP_ORIGIN"] ?? "");
  $cfg = brighton_config();
  $allowed = $cfg["cors"]["allowed_origins"] ?? [];

  if ($origin !== "" && is_array($allowed) && in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header("Vary: Origin");
    header("Access-Control-Allow-Methods: GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
  }

  if (($_SERVER["REQUEST_METHOD"] ?? "") === "OPTIONS") {
    http_response_code(204);
    exit;
  }
}

function brighton_json_response(mixed $data, int $status = 200): void {
  http_response_code($status);
  header("Content-Type: application/json; charset=utf-8");
  header("Cache-Control: no-store");
  echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

function brighton_fail(string $message, int $status = 400, array $extra = []): void {
  brighton_json_response(["error" => $message] + $extra, $status);
}

function brighton_decode_json(mixed $value, mixed $fallback): mixed {
  if ($value === null) return $fallback;
  if (is_array($value)) return $value;
  $text = (string)$value;
  if ($text === "") return $fallback;
  $decoded = json_decode($text, true);
  if (json_last_error() !== JSON_ERROR_NONE) return $fallback;
  return $decoded;
}
