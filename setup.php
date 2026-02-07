<?php
// PIWZ HUNTER SETUP SCRIPT
echo "=== PIWZ HUNTER SETUP ===\n";

// Create necessary files
$files = ['logs.txt', 'devices.json', 'config.json'];
foreach ($files as $file) {
    if (!file_exists($file)) {
        file_put_contents($file, $file === 'devices.json' ? '[]' : '');
        echo "Created: $file\n";
    }
}

// Set permissions
chmod('logs.txt', 0666);
chmod('devices.json', 0666);

// Generate random admin password
$admin_pass = bin2hex(random_bytes(8));
file_put_contents('.htpasswd', 'admin:' . password_hash($admin_pass, PASSWORD_DEFAULT));

echo "\n=== SETUP COMPLETE ===\n";
echo "Admin Password: $admin_pass\n";
echo "Change this immediately in the panel!\n";
?>
