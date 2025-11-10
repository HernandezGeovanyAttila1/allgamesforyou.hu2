<?php
session_start();
require_once "config.php";

$msg = "";

// --- REGISZTRÁCIÓ ---
if (isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm = trim($_POST['confirm_password']);

    if ($password !== $confirm) {
        $msg = "❌ A jelszavak nem egyeznek!";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $email, $hashed);

        try {
            $stmt->execute();
            $msg = "✅ Sikeres regisztráció! Most bejelentkezhetsz.";
        } catch (mysqli_sql_exception $e) {
            $msg = "⚠️ Hiba: Ez a felhasználónév vagy e-mail már létezik.";
        }

        $stmt->close();
    }
}

// --- BEJELENTKEZÉS ---
if (isset($_POST['login'])) {
    $username = trim($_POST['login_username']);
    $password = trim($_POST['login_password']);

    $stmt = $conn->prepare("SELECT * FROM users WHERE username=? OR email=? LIMIT 1");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['user'] = $user['username'];
            header("Location: index.html"); // Itt tér vissza a főoldalra
            exit;
        } else {
            $msg = "❌ Hibás jelszó!";
        }
    } else {
        $msg = "❌ Nincs ilyen felhasználó!";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bejelentkezés / Regisztráció</title>
<style>
body {
  background-color: #191927;
  color: #fff;
  font-family: "Poppins", sans-serif;
  display: flex;
  justify-content: center;
  align-items: center;
  height: 100vh;
}
.container {
  background: #2c2755;
  padding: 30px;
  border-radius: 15px;
  width: 350px;
  text-align: center;
  box-shadow: 0 0 15px rgba(0,0,0,0.4);
}
input {
  width: 90%;
  padding: 10px;
  margin: 8px 0;
  border-radius: 6px;
  border: none;
}
button {
  width: 95%;
  padding: 10px;
  background: #6c63ff;
  border: none;
  color: white;
  border-radius: 8px;
  font-weight: bold;
  cursor: pointer;
}
button:hover {
  background: #5347d3;
}
.message {
  color: #ffeb3b;
  margin-top: 10px;
}
.toggle {
  color: #00e5ff;
  cursor: pointer;
  margin-top: 10px;
  display: inline-block;
}
.hidden {
  display: none;
}
</style>
</head>
<body>
  <div class="container">
    <h2 id="formTitle">Bejelentkezés</h2>
    
    <!-- Bejelentkezés -->
    <form method="POST" id="loginForm">
      <input type="text" name="login_username" placeholder="Felhasználónév vagy e-mail" required><br>
      <input type="password" name="login_password" placeholder="Jelszó" required><br>
      <button type="submit" name="login">Bejelentkezés</button>
    </form>

    <!-- Regisztráció -->
    <form method="POST" id="registerForm" class="hidden">
      <input type="text" name="username" placeholder="Felhasználónév" required><br>
      <input type="email" name="email" placeholder="E-mail" required><br>
      <input type="password" name="password" placeholder="Jelszó" required><br>
      <input type="password" name="confirm_password" placeholder="Jelszó megerősítése" required><br>
      <button type="submit" name="register">Regisztráció</button>
    </form>

    <p class="message"><?= $msg ?></p>
    <span class="toggle" id="toggleLink">Nincs fiókod? Regisztrálj itt!</span>
  </div>

<script>
  const toggle = document.getElementById("toggleLink");
  const loginForm = document.getElementById("loginForm");
  const registerForm = document.getElementById("registerForm");
  const title = document.getElementById("formTitle");

  toggle.addEventListener("click", () => {
    loginForm.classList.toggle("hidden");
    registerForm.classList.toggle("hidden");
    if (registerForm.classList.contains("hidden")) {
      title.textContent = "Bejelentkezés";
      toggle.textContent = "Nincs fiókod? Regisztrálj itt!";
    } else {
      title.textContent = "Regisztráció";
      toggle.textContent = "Van már fiókod? Jelentkezz be!";
    }
  });
</script>
</body>
</html>
