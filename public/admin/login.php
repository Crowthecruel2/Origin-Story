<?php
declare(strict_types=1);

require_once __DIR__ . "/util.php";

admin_require_ip_allowlist();
admin_start_session();

if (admin_is_logged_in()) {
  header("Location: index.php");
  exit;
}

$error = null;
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $username = trim((string)($_POST["username"] ?? ""));
  $password = (string)($_POST["password"] ?? "");
  if ($username === "" || $password === "") {
    $error = "Username and password required.";
  } else {
    try {
      $pdo = brighton_pdo();
      $stmt = $pdo->prepare("SELECT id,username,password_hash FROM admin_users WHERE username = ?");
      $stmt->execute([$username]);
      $row = $stmt->fetch();
      if (!$row || !password_verify($password, (string)$row["password_hash"])) {
        $error = "Invalid credentials.";
      } else {
        $_SESSION["admin_user"] = ["id" => (int)$row["id"], "username" => (string)$row["username"]];
        $pdo->prepare("UPDATE admin_users SET last_login_at = NOW() WHERE id = ?")->execute([(int)$row["id"]]);
        header("Location: index.php");
        exit;
      }
    } catch (Throwable $e) {
      $error = "Login failed: " . $e->getMessage();
    }
  }
}

$body = "<section class='card'><h2>Login</h2>";
if ($error) $body .= "<div class='muted' style='color:#fecaca'>" . h($error) . "</div>";
$body .= "<form method='post' class='card' style='background:transparent;border:none;padding:0'>
  <label class='muted'>Username<input name='username' autocomplete='username' /></label>
  <label class='muted'>Password<input name='password' type='password' autocomplete='current-password' /></label>
  <button class='btn primary' type='submit'>Sign in</button>
  <div class='muted'>If you have no users yet, go to <code>/admin/setup.php</code> with your setup token.</div>
</form></section>";

admin_layout("Admin Login", $body);

