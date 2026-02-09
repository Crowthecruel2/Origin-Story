<?php
declare(strict_types=1);

require_once __DIR__ . "/../api/db.php";

function admin_cfg(): array {
  return brighton_config();
}

function admin_start_session(): void {
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
}

function admin_require_ip_allowlist(): void {
  $cfg = admin_cfg();
  $allow = $cfg["admin"]["allow_ips"] ?? [];
  if (!is_array($allow) || count($allow) === 0) return;
  $ip = $_SERVER["REMOTE_ADDR"] ?? "";
  if (!in_array($ip, $allow, true)) {
    http_response_code(403);
    echo "Forbidden";
    exit;
  }
}

function admin_is_logged_in(): bool {
  admin_start_session();
  return !empty($_SESSION["admin_user"]);
}

function admin_require_login(): void {
  admin_require_ip_allowlist();
  if (!admin_is_logged_in()) {
    header("Location: login.php");
    exit;
  }
}

function admin_csrf_token(): string {
  admin_start_session();
  if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(16));
  }
  return (string)$_SESSION["csrf"];
}

function admin_check_csrf(): void {
  admin_start_session();
  $token = $_POST["csrf"] ?? "";
  if (!$token || !hash_equals((string)($_SESSION["csrf"] ?? ""), (string)$token)) {
    http_response_code(400);
    echo "Bad request (CSRF)";
    exit;
  }
}

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

function admin_layout(string $title, string $bodyHtml): void {
  $user = $_SESSION["admin_user"]["username"] ?? null;
  echo "<!doctype html><html><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'>";
  echo "<title>" . h($title) . "</title>";
  echo "<style>
    :root{--bg:#050910;--panel:#0d1624;--card:#0f1c2d;--border:#233449;--accent:#38f2c7;--text:#e6ebf5;--muted:#9fb0c7;}
    body{margin:0;font-family:Segoe UI,system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--text);}
    a{color:inherit}
    .top{display:flex;gap:1rem;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border-bottom:1px solid var(--border);background:rgba(7,12,21,0.9);position:sticky;top:0}
    .nav{display:flex;gap:.5rem;flex-wrap:wrap}
    .nav a{padding:.45rem .65rem;border:1px solid transparent;border-radius:10px;color:var(--muted);text-decoration:none;font-weight:700}
    .nav a:hover{border-color:var(--border);color:var(--text)}
    .nav a.active{border-color:var(--accent);color:var(--text)}
    .wrap{max-width:1200px;margin:1.2rem auto 2rem;padding:0 1.25rem;display:grid;gap:.9rem}
    .card{background:var(--panel);border:1px solid var(--border);border-radius:14px;padding:1rem;display:grid;gap:.6rem}
    input,textarea,select{width:100%;background:var(--card);border:1px solid var(--border);border-radius:10px;padding:.55rem .65rem;color:var(--text)}
    textarea{min-height:120px;font-family:ui-monospace,Menlo,Consolas,monospace}
    table{width:100%;border-collapse:collapse}
    th,td{border-bottom:1px solid var(--border);padding:.5rem .4rem;text-align:left;vertical-align:top}
    th{color:var(--muted);font-size:.9rem}
    .row{display:grid;grid-template-columns:1fr 1fr;gap:.8rem}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:.35rem;padding:.55rem .85rem;border-radius:10px;border:1px solid var(--border);background:transparent;color:var(--text);font-weight:800;cursor:pointer;text-decoration:none}
    .btn.primary{background:linear-gradient(120deg,var(--accent),#34d399);color:#0a1525;border:none}
    .btn.danger{border-color:#7f1d1d;color:#fecaca}
    .muted{color:var(--muted)}
    code{background:rgba(255,255,255,0.06);padding:.1rem .35rem;border-radius:6px}
  </style></head><body>";
  echo "<div class='top'><div><strong>Brighton Admin</strong><div class='muted' style='font-size:.9rem'>Manage DB-backed content</div></div>";
  echo "<div class='nav'>";
  $links = [
    ["index.php", "Home"],
    ["items.php", "Items"],
    ["powers.php", "Powers"],
    ["rpg-factions.php", "RPG Factions"],
    ["wargame-factions.php", "Wargame Factions"],
    ["wargame-units.php", "Wargame Units"],
  ];
  $self = basename($_SERVER["PHP_SELF"] ?? "");
  foreach ($links as [$href, $label]) {
    $active = ($self === $href) ? "active" : "";
    echo "<a class='{$active}' href='{$href}'>" . h($label) . "</a>";
  }
  echo "</div>";
  echo "<div>";
  if ($user) {
    echo "<span class='muted' style='margin-right:.6rem'>Signed in as " . h((string)$user) . "</span>";
    echo "<a class='btn' href='logout.php'>Logout</a>";
  } else {
    echo "<a class='btn' href='login.php'>Login</a>";
  }
  echo "</div></div>";
  echo "<main class='wrap'>{$bodyHtml}</main></body></html>";
}

