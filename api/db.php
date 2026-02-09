<?php
declare(strict_types=1);

function brighton_config(): array {
  $path = __DIR__ . "/../config.php";
  if (!file_exists($path)) {
    http_response_code(500);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode([
      "error" => "Missing config.php",
      "hint" => "Copy config.php.example to config.php and fill in DB credentials.",
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
  }
  /** @var array $cfg */
  $cfg = require $path;
  return $cfg;
}

function brighton_pdo(): PDO {
  $cfg = brighton_config();
  $db = $cfg["db"] ?? [];

  $host = (string)($db["host"] ?? "localhost");
  $name = (string)($db["name"] ?? "");
  $user = (string)($db["user"] ?? "");
  $pass = (string)($db["pass"] ?? "");
  $charset = (string)($db["charset"] ?? "utf8mb4");

  $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
  return new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
}

