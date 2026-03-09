<?php
declare(strict_types=1);

require_once __DIR__ . "/util.php";

admin_require_login();
admin_start_session();

$pdo = brighton_pdo();
$error = null;
$ok = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  admin_check_csrf();
  $action = (string)($_POST["action"] ?? "");

  if ($action === "create") {
    $username = trim((string)($_POST["username"] ?? ""));
    $password = (string)($_POST["password"] ?? "");
    $confirm = (string)($_POST["confirm_password"] ?? "");

    if ($username === "" || $password === "" || $confirm === "") {
      $error = "Username, password, and confirmation are required.";
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,64}$/', $username)) {
      $error = "Username must be 3-64 chars and use letters, numbers, dot, underscore, or dash.";
    } elseif (strlen($password) < 8) {
      $error = "Password must be at least 8 characters.";
    } elseif (!hash_equals($password, $confirm)) {
      $error = "Password confirmation does not match.";
    } else {
      try {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admin_users(username,password_hash,created_at) VALUES(?,?,NOW())");
        $stmt->execute([$username, $hash]);
        $ok = "Admin user created.";
      } catch (Throwable $e) {
        $msg = (string)$e->getMessage();
        if (stripos($msg, "Duplicate") !== false || stripos($msg, "uq_admin_users_username") !== false) {
          $error = "That username already exists.";
        } else {
          $error = "Failed to create admin: " . $msg;
        }
      }
    }
  }
}

$rows = $pdo->query("SELECT id,username,created_at,last_login_at FROM admin_users ORDER BY id ASC")->fetchAll();

$body = "<section class='card'><div style='display:flex;justify-content:space-between;align-items:baseline;gap:.8rem;flex-wrap:wrap'>
  <h2>Admin Users</h2>
  <div class='muted'>Create additional admin accounts</div>
</div>";
if ($error) $body .= "<div class='muted' style='color:#fecaca'>" . h($error) . "</div>";
if ($ok) $body .= "<div class='muted' style='color:#bbf7d0'>" . h($ok) . "</div>";

$body .= "<form method='post' class='card' style='background:transparent;border:none;padding:0'>
  <input type='hidden' name='csrf' value='" . h(admin_csrf_token()) . "' />
  <input type='hidden' name='action' value='create' />
  <div class='row'>
    <label class='muted'>Username
      <input name='username' autocomplete='username' placeholder='new-admin' />
    </label>
    <label class='muted'>Password
      <input name='password' type='password' autocomplete='new-password' />
    </label>
  </div>
  <label class='muted'>Confirm password
    <input name='confirm_password' type='password' autocomplete='new-password' />
  </label>
  <div style='display:flex;gap:.6rem;flex-wrap:wrap'>
    <button class='btn primary' type='submit'>Create Admin</button>
  </div>
</form>";

$body .= "<table><thead><tr>
  <th>ID</th>
  <th>Username</th>
  <th>Created</th>
  <th>Last login</th>
</tr></thead><tbody>";
foreach ($rows as $r) {
  $body .= "<tr>
    <td>" . h((string)$r["id"]) . "</td>
    <td>" . h((string)$r["username"]) . "</td>
    <td class='muted'>" . h((string)$r["created_at"]) . "</td>
    <td class='muted'>" . h((string)($r["last_login_at"] ?? "")) . "</td>
  </tr>";
}
$body .= "</tbody></table></section>";

admin_layout("Admin Users", $body);

