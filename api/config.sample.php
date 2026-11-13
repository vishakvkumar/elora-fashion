<?php
// Copy this file to config.php (in the same api folder) and fill in the two values.
// config.php is private: never share it or put it in Git.
return [
    // Stripe → Developers → API keys. Use sk_test_... while testing, sk_live_... when you go live.
    'stripe_secret_key' => 'sk_test_REPLACE_ME',

    // Password for the admin panel. You can also paste a bcrypt hash (starts with $2y$)
    // made with: php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"
    'admin_password' => 'CHANGE-ME-to-a-long-password',
];
