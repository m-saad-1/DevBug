<?php
require_once 'config/database.php';

$name = 'test';
$email = 'test@test.com';
$password = 'password';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$avatar_color = '#' . substr(md5(rand()), 0, 6);

$stmt = $pdo->prepare("INSERT INTO users (name, email, password, avatar_color, title) VALUES (?, ?, ?, ?, ?)");
if ($stmt->execute([$name, $email, $hashed_password, $avatar_color, 'Developer'])) {
    echo "User created successfully.";
} else {
    echo "Failed to create user.";
}
?>