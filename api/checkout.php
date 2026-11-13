<?php
// POST api/checkout.php — creates a Stripe Checkout session. Prices come from data/store.json, never the browser.
require __DIR__ . '/_bootstrap.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') fail('Method not allowed', 405);
$in = read_json_body();
$items = $in['items'] ?? null;
if (!is_array($items) || !$items || count($items) > 30) fail('Your bag is empty.');

$store = load_store();
$byId = [];
foreach ($store['products'] as $p) $byId[$p['id']] = $p;
$base = base_url();
$line = []; $subtotal = 0; $missing = []; $summary = [];

foreach ($items as $it) {
    $p = $byId[$it['id'] ?? ''] ?? null;
    $qty = max(1, min(20, (int)($it['qty'] ?? 1)));
    if (!$p || !empty($p['hidden']) || (($p['inStock'] ?? true) === false)) { $missing[] = str_clip($it['name'] ?? ($it['id'] ?? 'item'), 80); continue; }
    $sizes = $p['sizes'] ?? [];
    $size = (string)($it['size'] ?? '');
    if ($sizes && !in_array($size, $sizes, true)) fail('Please choose a valid size for ' . $p['name'] . '.');
    $colourNames = array_map(fn($c) => $c['name'], $p['colors'] ?? []);
    $colour = in_array($it['color'] ?? '', $colourNames, true) ? $it['color'] : ($colourNames[0] ?? '');
    $pence = (int)round(((float)$p['price']) * 100);
    $subtotal += $pence * $qty;
    $img = '';
    foreach ($p['colors'] ?? [] as $c) if ($c['name'] === $colour && !empty($c['image'])) $img = $c['image'];
    if (!$img) $img = $p['images'][0] ?? '';
    $desc = implode(' · ', array_filter([$colour ? "Colour: $colour" : '', $sizes ? (($p['sizeLabel'] ?? 'Size') . ": $size") : '']));
    $pd = ['name' => $p['name'], 'metadata' => ['product_id' => $p['id'], 'size' => $size, 'colour' => $colour]];
    if ($desc) $pd['description'] = $desc;
    if (valid_img($img)) $pd['images'] = [$base . '/' . $img];
    $line[] = ['quantity' => $qty, 'price_data' => ['currency' => 'gbp', 'unit_amount' => $pence, 'product_data' => $pd]];
    $summary[] = $p['name'] . ' (' . implode(', ', array_filter([$size, $colour])) . ") x$qty";
}
if ($missing) fail("Sorry, these items can't be bought online right now: " . implode(', ', $missing) . '. Please remove them from your bag.', 409);

$set = $store['settings'];
$freeOver = (int)round(((float)($set['freeOver'] ?? 0)) * 100);
$fee = (int)round(((float)($set['deliveryFee'] ?? 0)) * 100);
$shipping = ($freeOver && $subtotal >= $freeOver) ? 0 : $fee;

$c = is_array($in['customer'] ?? null) ? $in['customer'] : [];
$ref = (is_string($in['ref'] ?? null) && preg_match('/^ELR-[A-Z0-9]{4,10}$/', $in['ref'])) ? $in['ref'] : 'ELR-' . strtoupper(substr(base_convert((string)time(), 10, 36), -6));
$addr = ['line1' => str_clip($c['line1'] ?? '', 200), 'line2' => str_clip($c['line2'] ?? '', 200), 'city' => str_clip($c['city'] ?? '', 100),
         'state' => str_clip($c['county'] ?? '', 100), 'postal_code' => strtoupper(str_clip($c['postcode'] ?? '', 20)), 'country' => 'GB'];
$addrText = mb_substr(implode(', ', array_filter([$addr['line1'], $addr['line2'], $addr['city'], $addr['state'], $addr['postal_code']])), 0, 450);
$email = str_clip($c['email'] ?? '', 200);

$params = [
    'mode' => 'payment',
    'line_items' => $line,
    'shipping_options' => [['shipping_rate_data' => [
        'type' => 'fixed_amount',
        'display_name' => $shipping ? 'UK delivery' : 'Free UK delivery',
        'fixed_amount' => ['amount' => $shipping, 'currency' => 'gbp'],
        'delivery_estimate' => ['minimum' => ['unit' => 'business_day', 'value' => 3], 'maximum' => ['unit' => 'business_day', 'value' => 7]],
    ]]],
    'client_reference_id' => $ref,
    'payment_intent_data' => [
        'description' => "Elora order $ref",
        'shipping' => ['name' => str_clip($c['name'] ?? '', 100), 'phone' => str_clip($c['phone'] ?? '', 30), 'address' => array_filter($addr)],
        'metadata' => ['order_ref' => $ref, 'phone' => str_clip($c['phone'] ?? '', 30), 'notes' => str_clip($c['notes'] ?? '', 450)],
    ],
    'metadata' => [
        'order_ref' => $ref, 'customer_name' => str_clip($c['name'] ?? '', 100), 'phone' => str_clip($c['phone'] ?? '', 30),
        'address' => $addrText, 'items' => mb_substr(implode('; ', $summary), 0, 490), 'notes' => str_clip($c['notes'] ?? '', 450),
    ],
    'success_url' => $base . '/?session_id={CHECKOUT_SESSION_ID}#order-success',
    'cancel_url' => $base . '/#checkout',
];
if (filter_var($email, FILTER_VALIDATE_EMAIL)) $params['customer_email'] = $email;

$session = stripe('POST', 'checkout/sessions', $params);
json_out(['url' => $session['url'], 'ref' => $ref]);
