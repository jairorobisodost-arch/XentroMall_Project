<?php
// Contact functions for XentroMall
function getMallContacts() {
    return [
        [
            'name' => 'Mall Admin',
            'email' => 'admin@xentromall.com',
            'phone' => '+63 912 345 6789',
            'role' => 'General Inquiries'
        ],
        [
            'name' => 'Leasing Office',
            'email' => 'leasing@xentromall.com',
            'phone' => '+63 917 555 1234',
            'role' => 'Stall Leasing'
        ],
        [
            'name' => 'Technical Support',
            'email' => 'support@xentromall.com',
            'phone' => '+63 927 888 4321',
            'role' => 'System Support'
        ]
    ];
}

function displayMallContacts() {
    $contacts = getMallContacts();
    echo '<div class="max-w-2xl mx-auto mt-12">';
    echo '<h2 class="text-2xl font-bold mb-6 text-center text-emerald-700">Mall Contacts</h2>';
    echo '<div class="grid gap-6">';
    foreach ($contacts as $c) {
        echo '<div class="rounded-xl bg-white/80 shadow-lg p-6 flex flex-col md:flex-row items-center justify-between">';
        echo '<div class="flex-1">';
        echo '<div class="font-bold text-lg text-slate-900">' . htmlspecialchars($c['name']) . '</div>';
        echo '<div class="text-sm text-slate-600 mb-2">' . htmlspecialchars($c['role']) . '</div>';
        echo '<div class="text-sm text-slate-700"><i class="fas fa-envelope mr-1"></i> ' . htmlspecialchars($c['email']) . '</div>';
        echo '<div class="text-sm text-slate-700"><i class="fas fa-phone mr-1"></i> ' . htmlspecialchars($c['phone']) . '</div>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';
}

// Page rendering
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - XentroMall</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-emerald-50 to-blue-50 min-h-screen">
    <header class="py-6 bg-gradient-to-r from-emerald-500 to-blue-500 text-white text-center shadow-lg">
        <h1 class="text-3xl font-bold">Contact Us</h1>
        <p class="text-lg mt-2">Reach out to XentroMall for assistance</p>
    </header>
    <?php displayMallContacts(); ?>
    <footer class="mt-16 py-6 text-center text-slate-500 text-sm">
        &copy; <?php echo date('Y'); ?> XentroMall. All rights reserved.
    </footer>
</body>
</html>
