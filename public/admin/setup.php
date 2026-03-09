<?php
declare(strict_types=1);

require_once __DIR__ . "/util.php";

admin_require_ip_allowlist();
admin_start_session();

$cfg = admin_cfg();
$tokenCfg = (string)($cfg["admin"]["setup_token"] ?? "");
$tokenReq = (string)($_GET["token"] ?? "");

if ($tokenCfg === "" || $tokenCfg === "CHANGE_ME_TO_RANDOM_AND_REMOVE_AFTER_SETUP") {
  admin_layout("Setup disabled", "<section class='card'><h2>Setup disabled</h2><p class='muted'>Set a real <code>admin.setup_token</code> in <code>config.php</code> to enable first-user setup.</p></section>");
  exit;
}
if ($tokenReq === "" || !hash_equals($tokenCfg, $tokenReq)) {
  admin_layout("Forbidden", "<section class='card'><h2>Forbidden</h2><p class='muted'>Missing/invalid setup token.</p></section>");
  exit;
}

$error = null;
$ok = null;
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  admin_check_csrf();
  $username = trim((string)($_POST["username"] ?? ""));
  $password = (string)($_POST["password"] ?? "");
  if ($username === "" || $password === "") {
    $error = "Username and password required.";
  } else {
    try {
      $pdo = brighton_pdo();
      $exists = (int)$pdo->query("SELECT COUNT(*) c FROM admin_users")->fetch()["c"];
      if ($exists > 0) {
        $error = "Admin user already exists. Use login instead.";
      } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admin_users(username,password_hash,created_at) VALUES(?,?,NOW())");
        $stmt->execute([$username, $hash]);
        $ok = "Created admin user. Now remove admin.setup_token from config.php.";
      }
    } catch (Throwable $e) {
      $error = "Setup failed: " . $e->getMessage();
    }
  }
}

$body = "<section class='card'><h2>First admin setup</h2>
<p class='muted'>This page is protected by <code>admin.setup_token</code>. Create the first admin user, then remove the token.</p>";
if ($error) $body .= "<div class='muted' style='color:#fecaca'>" . h($error) . "</div>";
if ($ok) $body .= "<div class='muted' style='color:#bbf7d0'>" . h($ok) . "</div>";
$body .= "<form method='post' class='card' style='background:transparent;border:none;padding:0'>
  <input type='hidden' name='csrf' value='" . h(admin_csrf_token()) . "' />
  <label class='muted'>Username<input name='username' autocomplete='username' /></label>
  <label class='muted'>Password<input name='password' type='password' autocomplete='new-password' /></label>
  <button class='btn primary' type='submit'>Create admin</button>
</form></section>";

admin_layout("Admin Setup", $body);

