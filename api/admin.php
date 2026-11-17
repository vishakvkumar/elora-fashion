<?php
// POST api/admin.php?action=... — the admin panel's server side.
require __DIR__ . '/_bootstrap.php';
$action = (string)($_GET['action'] ?? '');
start_session();

if ($action === 'me') json_out(['admin' => is_admin(), 'csrf' => is_admin() ? $_SESSION['csrf'] : null, 'username' => is_admin() ? ($_SESSION['user'] ?? '') : null,
    'mustChange' => is_admin() && !empty($_SESSION['mustChange']), 'configured' => load_admin() !== null, 'payments' => STRIPE_KEY !== '']);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') fail('Method not allowed', 405);

if ($action === 'login') {
    $admin = load_admin();
    if (!$admin) fail('The admin login has not been set up on the server yet.', 503);
    rate_check();
    $in = read_json_body();
    $user = trim((string)($in['username'] ?? '')); $pw = (string)($in['password'] ?? '');
    $ok = hash_equals(strtolower($admin['username']), strtolower($user)) & password_verify($pw, $admin['hash']);
    if (!$ok) { rate_check(true); usleep(800000); fail('Wrong username or password.', 401); }
    session_regenerate_id(true);
    $_SESSION['admin'] = true;
    $_SESSION['user'] = $admin['username'];
    $_SESSION['mustChange'] = !empty($admin['mustChange']);
    $_SESSION['csrf'] = bin2hex(random_bytes(24));
    json_out(['admin' => true, 'csrf' => $_SESSION['csrf'], 'username' => $admin['username'], 'mustChange' => $_SESSION['mustChange']]);
}

if ($action === 'logout') { $_SESSION = []; session_destroy(); json_out(['admin' => false]); }

require_admin();

if ($action === 'change_credentials') {
    $in = read_json_body();
    $admin = load_admin() ?: fail('Admin login not set up.', 503);
    $forced = !empty($_SESSION['mustChange']);
    if (!$forced && !password_verify((string)($in['currentPassword'] ?? ''), $admin['hash'])) { rate_check(true); fail('Your current password is wrong.', 401); }
    $user = trim((string)($in['username'] ?? '')); $pw = (string)($in['password'] ?? '');
    if (!preg_match('/^[A-Za-z0-9._@-]{3,40}$/', $user)) fail('Username must be 3–40 characters: letters, numbers, dot, dash, underscore or @.');
    if (strlen($pw) < 10 || !preg_match('/[A-Za-z]/', $pw) || !preg_match('/[0-9]/', $pw)) fail('Password must be at least 10 characters and include letters and numbers.');
    if (password_verify($pw, $admin['hash'])) fail('Choose a new password, different from the current one.');
    save_admin(['username' => $user, 'hash' => password_hash($pw, PASSWORD_DEFAULT), 'mustChange' => false, 'changedAt' => date('c')]);
    session_regenerate_id(true);
    $_SESSION['user'] = $user; $_SESSION['mustChange'] = false; $_SESSION['csrf'] = bin2hex(random_bytes(24));
    json_out(['ok' => true, 'csrf' => $_SESSION['csrf'], 'username' => $user]);
}
if (!empty($_SESSION['mustChange'])) fail('Please set your own username and password first.', 403);

function clean_product(array $d): array {
    $colors = [];
    foreach (array_slice($d['colors'] ?? [], 0, 20) as $c) {
        if (!is_array($c) || trim((string)($c['name'] ?? '')) === '') continue;
        $hex = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($c['hex'] ?? '')) ? $c['hex'] : '#5b0e2d';
        $colors[] = ['name' => str_clip($c['name'], 40), 'hex' => $hex, 'image' => valid_img($c['image'] ?? '') ? $c['image'] : ''];
    }
    $price = round((float)($d['price'] ?? 0), 2);
    if ($price <= 0) fail('Please add a price.');
    $compare = isset($d['compareAt']) && $d['compareAt'] !== '' && $d['compareAt'] !== null ? round((float)$d['compareAt'], 2) : null;
    $images = array_values(array_filter(array_slice($d['images'] ?? [], 0, 12), 'valid_img'));
    if (!$images) fail('Please add at least one photo.');
    return [
        'name' => str_clip($d['name'] ?? '', 150) ?: fail('Please add a product name.'),
        'category' => valid_id($d['category'] ?? '') ? $d['category'] : fail('Please choose a category.'),
        'price' => $price,
        'compareAt' => $compare && $compare > 0 ? $compare : null,
        'images' => $images,
        'colors' => $colors,
        'sizes' => array_values(array_filter(array_map(fn($s) => str_clip($s, 20), array_slice($d['sizes'] ?? [], 0, 20)), 'strlen')),
        'sizeLabel' => str_clip($d['sizeLabel'] ?? 'Size', 30) ?: 'Size',
        'description' => str_clip($d['description'] ?? '', 3000),
        'details' => str_clip($d['details'] ?? '', 2000),
        'care' => str_clip($d['care'] ?? '', 500),
        'inStock' => ($d['inStock'] ?? true) !== false,
        'isNew' => !empty($d['isNew']),
        'featured' => !empty($d['featured']),
        'hidden' => !empty($d['hidden']),
        'order' => isset($d['order']) && $d['order'] !== null && $d['order'] !== '' ? (int)$d['order'] : null,
        'createdAt' => (int)($d['createdAt'] ?? round(microtime(true) * 1000)),
    ];
}

switch ($action) {
    case 'save_product': {
        $in = read_json_body(); $id = $in['id'] ?? '';
        if (!valid_id($id)) fail('Invalid product id.');
        $p = ['id' => $id] + clean_product(is_array($in['data'] ?? null) ? $in['data'] : []);
        update_store(function (&$s) use ($p) {
            foreach ($s['products'] as $i => $x) if ($x['id'] === $p['id']) { $s['products'][$i] = $p; return; }
            $s['products'][] = $p;
        });
        json_out(['ok' => true, 'item' => $p]);
    }
    case 'delete_product': {
        $id = read_json_body()['id'] ?? '';
        update_store(function (&$s) use ($id) { $s['products'] = array_values(array_filter($s['products'], fn($x) => $x['id'] !== $id)); });
        json_out(['ok' => true]);
    }
    case 'save_category': {
        $in = read_json_body(); $id = $in['id'] ?? ''; $d = is_array($in['data'] ?? null) ? $in['data'] : [];
        if (!valid_id($id)) fail('Invalid category id.');
        $c = ['id' => $id, 'name' => str_clip($d['name'] ?? '', 60) ?: fail('Please add a name.'), 'blurb' => str_clip($d['blurb'] ?? '', 200),
              'image' => valid_img($d['image'] ?? '') ? $d['image'] : '', 'order' => isset($d['order']) && $d['order'] !== null && $d['order'] !== '' ? (int)$d['order'] : null];
        update_store(function (&$s) use ($c) {
            foreach ($s['categories'] as $i => $x) if ($x['id'] === $c['id']) { $s['categories'][$i] = $c; return; }
            $s['categories'][] = $c;
        });
        json_out(['ok' => true, 'item' => $c]);
    }
    case 'delete_category': {
        $id = read_json_body()['id'] ?? '';
        update_store(function (&$s) use ($id) { $s['categories'] = array_values(array_filter($s['categories'], fn($x) => $x['id'] !== $id)); });
        json_out(['ok' => true]);
    }
    case 'save_settings': {
        $d = read_json_body()['data'] ?? [];
        if (!is_array($d)) fail('Invalid settings.');
        $hero = [];
        foreach (array_slice($d['hero'] ?? [], 0, 10) as $h) {
            if (!is_array($h) || !valid_img($h['image'] ?? '')) continue;
            $hero[] = ['image' => $h['image'], 'kicker' => str_clip($h['kicker'] ?? '', 80), 'title' => str_clip($h['title'] ?? '', 80),
                       'sub' => str_clip($h['sub'] ?? '', 80), 'link' => preg_match('/^(shop|c-[a-z0-9-]+)$/', (string)($h['link'] ?? '')) ? $h['link'] : 'shop'];
        }
        $set = [
            'whatsapp' => preg_replace('/[^0-9]/', '', (string)($d['whatsapp'] ?? '')),
            'currency' => str_clip($d['currency'] ?? '£', 3) ?: '£',
            'freeOver' => max(0, round((float)($d['freeOver'] ?? 0), 2)),
            'deliveryFee' => max(0, round((float)($d['deliveryFee'] ?? 0), 2)),
            'email' => str_clip($d['email'] ?? '', 120),
            'instagram' => str_clip($d['instagram'] ?? '', 200),
            'location' => str_clip($d['location'] ?? '', 120),
            'announcements' => array_values(array_filter(array_map(fn($a) => str_clip($a, 120), array_slice($d['announcements'] ?? [], 0, 6)), 'strlen')),
            'hero' => $hero,
        ];
        update_store(function (&$s) use ($set) { $s['settings'] = $set; });
        json_out(['ok' => true, 'item' => $set]);
    }
    case 'upload': {
        $f = $_FILES['file'] ?? null;
        if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK) fail('Upload failed. The photo may be too large for this host.');
        if ($f['size'] > 8 * 1024 * 1024) fail('Photos must be under 8 MB.');
        $info = @getimagesize($f['tmp_name']);
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$info['mime'] ?? ''] ?? null;
        if (!$info || !$ext) fail('Please upload a JPG, PNG or WebP photo.');
        if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true)) fail('The uploads folder is missing or not writable.', 500);
        $name = date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . '/' . $name)) fail('Could not save the photo. Check that the uploads folder is writable.', 500);
        json_out(['url' => 'uploads/' . $name]);
    }
    case 'orders': {
        $res = stripe('GET', 'checkout/sessions', ['limit' => 50, 'status' => 'complete', 'expand' => ['data.payment_intent.latest_charge']]);
        $out = [];
        foreach ($res['data'] ?? [] as $s) {
            $pi = $s['payment_intent'] ?? null; $ch = is_array($pi) ? ($pi['latest_charge'] ?? null) : null;
            $out[] = [
                'id' => $s['id'], 'ref' => $s['client_reference_id'] ?? '', 'created' => $s['created'] ?? 0,
                'name' => $s['metadata']['customer_name'] ?? ($s['customer_details']['name'] ?? ''),
                'email' => $s['customer_details']['email'] ?? '', 'phone' => $s['metadata']['phone'] ?? '',
                'address' => $s['metadata']['address'] ?? '', 'items' => $s['metadata']['items'] ?? '', 'notes' => $s['metadata']['notes'] ?? '',
                'total' => ($s['amount_total'] ?? 0) / 100, 'paid' => ($s['payment_status'] ?? '') === 'paid',
                'paymentIntent' => is_array($pi) ? $pi['id'] : $pi,
                'refunded' => is_array($ch) ? ($ch['amount_refunded'] ?? 0) / 100 : 0,
                'testMode' => empty($s['livemode']),
            ];
        }
        json_out(['orders' => $out]);
    }
    case 'refund': {
        $in = read_json_body();
        $pi = (string)($in['paymentIntent'] ?? '');
        if (!preg_match('/^pi_[A-Za-z0-9]+$/', $pi)) fail('Invalid payment.');
        $params = ['payment_intent' => $pi, 'reason' => 'requested_by_customer', 'metadata' => ['refunded_from' => 'elora-admin']];
        if (isset($in['amount']) && $in['amount'] !== null && $in['amount'] !== '') {
            $pence = (int)round(((float)$in['amount']) * 100);
            if ($pence < 1) fail('Please enter a refund amount.');
            $params['amount'] = $pence;
        }
        $r = stripe('POST', 'refunds', $params);
        json_out(['ok' => true, 'status' => $r['status'] ?? '', 'amount' => ($r['amount'] ?? 0) / 100]);
    }
}
fail('Unknown action.', 404);
