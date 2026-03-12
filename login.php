<?php
require_once __DIR__ . '/config.php';
session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

function ensureUserSchemaForLogin($db) {
    $db->query("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(120) NOT NULL,
        username VARCHAR(80) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $countRes = $db->query("SELECT COUNT(*) AS total FROM users");
    $count = $countRes ? (int)$countRes->fetch_assoc()['total'] : 0;
    if ($count === 0) {
        $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
        $staffPass = password_hash('staff123', PASSWORD_DEFAULT);
        $db->query("INSERT INTO users (full_name, username, password_hash, role) VALUES
            ('Administrator', 'admin', '$adminPass', 'admin'),
            ('POS Staff', 'staff', '$staffPass', 'staff')");
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    ensureUserSchemaForLogin($db);
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$username || !$password) {
        $error = 'Username and password are required.';
    } else {
        $res = $db->query("SELECT * FROM users WHERE username='$username' AND is_active=1 LIMIT 1");
        $user = $res ? $res->fetch_assoc() : null;
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Invalid credentials.';
        } else {
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Brewed &amp; Co. POS</title>
    <style>
    :root {
      --bg: #f4efe7;
      --panel: rgba(255, 255, 255, 0.7);
      --text: #2b2118;
      --muted: #7b6f63;
      --brand: #167a6b;
      --brand-dark: #0f5f54;
      --line: #e6dccf;
      --danger: #b42318;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: "DM Sans", system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      color: var(--text);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 48px 24px;
      background:
        radial-gradient(circle at 20% 20%, rgba(22, 122, 107, 0.10), transparent 38%),
        radial-gradient(circle at 80% 80%, rgba(193, 154, 107, 0.12), transparent 40%),
        linear-gradient(180deg, #fbf8f3 0%, #f4efe7 100%);
    }
    .wrap {
      width: 100%;
      max-width: 520px;
      background: var(--panel);
      backdrop-filter: blur(6px);
      border: 1px solid rgba(28, 18, 9, 0.08);
      border-radius: 20px;
      padding: 32px 32px 28px;
      box-shadow: 0 18px 40px rgba(0,0,0,0.08);
    }
    .brand {
      display: flex;
      align-items: center;
      gap: 14px;
      margin-bottom: 26px;
    }
    .brand-icon {
      width: 48px;
      height: 48px;
      border-radius: 14px;
      background: var(--brand);
      display: grid;
      place-items: center;
      color: #fff;
      box-shadow: 0 12px 22px rgba(22, 122, 107, 0.25);
    }
    .brand-name {
      font-family: Georgia, "Times New Roman", serif;
      font-size: 22px;
      font-weight: 700;
      letter-spacing: 0.2px;
    }
    .title {
      margin: 0 0 6px 0;
      font-size: 30px;
      font-weight: 700;
      font-family: Georgia, "Times New Roman", serif;
    }
    .subtitle {
      margin: 0 0 22px 0;
      color: var(--muted);
      font-size: 15px;
    }
    label {
      display: block;
      font-size: 13px;
      margin-bottom: 8px;
      color: #4f453c;
      font-weight: 600;
    }
    .field { margin-bottom: 16px; }
    .input-wrap {
      position: relative;
    }
    .icon-left, .icon-right {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      color: #9a8f82;
      width: 18px;
      height: 18px;
    }
    .icon-left { left: 12px; }
    .icon-right { right: 12px; }
    input {
      width: 100%;
      height: 48px;
      border: 1px solid var(--line);
      border-radius: 12px;
      padding: 0 40px 0 40px;
      font-size: 15px;
      outline: none;
      background: rgba(255,255,255,0.9);
    }
    input:focus {
      border-color: var(--brand);
      box-shadow: 0 0 0 3px rgba(22, 122, 107, 0.18);
    }
    .err {
      background: #fef3f2;
      border: 1px solid #fecdca;
      color: var(--danger);
      padding: 10px 12px;
      border-radius: 10px;
      margin-bottom: 12px;
      font-size: 14px;
    }
    .btn {
      width: 100%;
      height: 50px;
      border: 0;
      border-radius: 14px;
      background: var(--brand);
      color: #fff;
      font-weight: 700;
      font-size: 16px;
      cursor: pointer;
      margin-top: 6px;
      box-shadow: 0 14px 26px rgba(22, 122, 107, 0.22);
    }
    .btn:hover { background: var(--brand-dark); }
      margin-top: 16px;
      background: #eef3ee;
      border-left: 4px solid var(--brand);
      border-radius: 12px;
      padding: 12px 14px;
      color: #4b5d57;
      font-size: 13px;
    }
    @media (max-width: 720px) {
      body { padding: 28px 16px; justify-content: center; }
      .wrap { padding: 26px 22px; }
    }
  </style>
</head>
<body>
  <form class="wrap" method="post" action="login.php">
    <div class="brand">
      <div class="brand-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M6 3h12"></path>
          <path d="M6 3v7a6 6 0 0 0 12 0V3"></path>
          <path d="M6 8H4a3 3 0 0 0 3 3"></path>
          <path d="M18 8h2a3 3 0 0 1-3 3"></path>
          <path d="M9 21h6"></path>
          <path d="M10 17h4"></path>
        </svg>
      </div>
      <div class="brand-name">Brewed &amp; Co.</div>
    </div>
    <h1 class="title">Welcome back</h1>
    <p class="subtitle">Sign in to your account to continue</p>

    <?php if ($error): ?>
      <div class="err"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="field">
      <label for="username">Username</label>
      <div class="input-wrap">
        <span class="icon-left" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21a8 8 0 0 0-16 0"></path>
            <circle cx="12" cy="7" r="4"></circle>
          </svg>
        </span>
        <input id="username" name="username" type="text" autocomplete="username" placeholder="Enter your username" required onkeydown="if(event.key==='Enter') document.getElementById('password').focus()">
      </div>
    </div>

    <div class="field">
      <label for="password">Password</label>
      <div class="input-wrap">
        <span class="icon-left" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="4" y="10" width="16" height="11" rx="2"></rect>
            <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
          </svg>
        </span>
        <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required onkeydown="if(event.key==='Enter') this.form.submit()">
        <span class="icon-right" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7Z"></path>
            <circle cx="12" cy="12" r="3"></circle>
          </svg>
        </span>
      </div>
    </div>

    <button class="btn" type="submit">Login</button>

  </form>
</body>
</html>

