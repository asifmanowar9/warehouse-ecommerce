<?php require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $passRaw  = $_POST['password'] ?? '';

    if ($username && filter_var($email, FILTER_VALIDATE_EMAIL) && $passRaw) {
        $hash = password_hash($passRaw, PASSWORD_DEFAULT);
        // Always register new users as regular users
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'user')");
        try {
            $stmt->execute([$username, $email, $hash]);
            header('Location: login.php?msg=registered');
            exit;
        } catch (PDOException $e) {
            $error = 'Username or email already exists.';
        }
    } else {
        $error = 'Fill out all fields with valid data.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><title>Register</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
...
<body>
<main class="container px-3">
  <div class="col-lg-4 col-md-6 mx-auto card-glass p-4">
      <div class="logo-circle mb-3">W</div>
      <h3 class="text-center text-white mb-4 fw-semibold">Create account</h3>

      <?php if(!empty($error)): ?>
         <div class="alert alert-danger py-2"><?=htmlspecialchars($error)?></div>
      <?php endif;?>

      <form method="post" novalidate>
        <input class="form-control mb-2"  name="username" placeholder="Username" required>
        <input class="form-control mb-2"  type="email" name="email"    placeholder="Email" required>
        <input class="form-control mb-3"  type="password" name="password" placeholder="Password" required>
        <button class="btn btn-primary w-100 mb-2 fw-semibold">Register</button>
      </form>
      <p class="text-center small mb-0 text-white-50">
         Have an account? <a href="login.php" class="fw-medium">Login</a>
      </p>
  </div>
</main>
<link href="assets/css/style.css" rel="stylesheet">
</body>
...

</html>
