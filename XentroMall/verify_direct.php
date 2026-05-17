<?php
require 'config.php';

// Enable full error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$token = "bba0ce0ce79d71dd51bb93bfb983588064391c533fe344b028cbd904f0c8ee3c";

echo "<h1>Direct Email Verification Test</h1>";
echo "<h2>Testing Token: " . substr($token, 0, 20) . "...</h2>";

try {
    // Find user with this verification token
    $stmt = $pdo->prepare('SELECT id, email, username, verification_token_expires FROM users WHERE verification_token = :token AND role = :role');
    $stmt->execute(['token' => $token, 'role' => 'tenant']);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "<p style='color: green;'>✅ User found!</p>";
        echo "<p><strong>ID:</strong> {$user['id']}</p>";
        echo "<p><strong>Username:</strong> {$user['username']}</p>";
        echo "<p><strong>Email:</strong> {$user['email']}</p>";
        echo "<p><strong>Token Expires:</strong> {$user['verification_token_expires']}</p>";
        echo "<p><strong>Current Time:</strong> " . date('Y-m-d H:i:s') . "</p>";
        
        // Check if token has expired
        if (strtotime($user['verification_token_expires']) > time()) {
            echo "<p style='color: green; font-weight: bold;'>✅ Token is VALID and not expired!</p>";
            
            // Verify the email
            $stmt = $pdo->prepare('UPDATE users SET email_verified = TRUE, verification_token = NULL, verification_token_expires = NULL WHERE id = :id');
            $result = $stmt->execute(['id' => $user['id']]);
            
            if ($result) {
                echo "<p style='color: green; font-size: 18px; font-weight: bold;'>🎉 EMAIL VERIFIED SUCCESSFULLY!</p>";
                echo "<p>Your email {$user['email']} has been verified.</p>";
                echo "<p>You can now login to your account.</p>";
                echo "<p><a href='login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login</a></p>";
            } else {
                echo "<p style='color: red;'>❌ Failed to update email verification status.</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ Token has EXPIRED!</p>";
            echo "<p>Token expired at: {$user['verification_token_expires']}</p>";
            echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ No user found with this token.</p>";
        
        // Show all users with tokens for debugging
        $stmt = $pdo->prepare('SELECT id, username, email, verification_token FROM users WHERE role = :role AND verification_token IS NOT NULL');
        $stmt->execute(['role' => 'tenant']);
        $users = $stmt->fetchAll();
        
        echo "<h3>Users with tokens:</h3>";
        foreach ($users as $u) {
            echo "<p>ID: {$u['id']}, Username: {$u['username']}, Token: " . substr($u['verification_token'], 0, 20) . "...</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 600px;
    margin: 0 auto;
    padding: 20px;
    background-color: #f5f5f5;
}
h1, h2 {
    color: #333;
}
p {
    margin: 10px 0;
}
</style>
