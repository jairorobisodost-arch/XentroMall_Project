<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Fetch admin profile details
try {
    $stmt = $pdo->prepare('SELECT username, email, full_name FROM users WHERE id = :id AND role = :role');
    $stmt->execute(['id' => $_SESSION['user_id'], 'role' => 'admin']);
    $admin = $stmt->fetch();
    if ($admin) {
        $admin_username = htmlspecialchars($admin['username']);
        $admin_email = htmlspecialchars($admin['email']);
        $admin_fullname = htmlspecialchars($admin['full_name']);
    } else {
        $admin_username = 'Admin';
        $admin_email = '';
        $admin_fullname = '';
    }
} catch (Exception $e) {
    $admin_username = 'Admin';
    $admin_email = '';
    $admin_fullname = '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-3xl mx-auto bg-white p-6 rounded shadow">
        <h1 class="text-2xl font-bold mb-4">Admin Profile</h1>
        <div class="space-y-4">
            <div>
                <label class="block font-semibold mb-1">Full Name</label>
                <p class="p-2 border border-gray-300 rounded bg-gray-50"><?php echo $admin_fullname; ?></p>
            </div>
            <div>
                <label class="block font-semibold mb-1">Username</label>
                <p class="p-2 border border-gray-300 rounded bg-gray-50"><?php echo $admin_username; ?></p>
            </div>
            <div>
                <label class="block font-semibold mb-1">Email</label>
                <p class="p-2 border border-gray-300 rounded bg-gray-50"><?php echo $admin_email; ?></p>
            </div>
        </div>
        <a href="admin_dashboard.php" class="inline-block mt-4 text-blue-600 hover:underline">Back to Dashboard</a>
    </div>
</body>
</html>
