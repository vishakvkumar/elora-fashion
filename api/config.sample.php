<?php
// Copy this file to config.php (in the same api folder) and fill in the two values.
// config.php is private: never share it or put it in Git.
return [
    // Stripe → Developers → API keys. Use sk_test_... while testing, sk_live_... when you go live.
    'stripe_secret_key' => 'sk_test_REPLACE_ME',

    // First-time admin login only. After the first login the admin must choose a new
    // username and password, which are saved (hashed) in data/admin.json and these two
    // lines are then ignored. A bcrypt hash (starts with $2y$) can be used instead of plain text.
    'admin_username' => 'admin',
    'admin_password' => '',
];
