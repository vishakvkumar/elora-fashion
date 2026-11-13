<?php
// GET api/session.php?id=cs_... — lets the confirmation page check that the payment went through.
require __DIR__ . '/_bootstrap.php';
$id = (string)($_GET['id'] ?? '');
if (!preg_match('/^cs_(test|live)_[A-Za-z0-9]+$/', $id)) fail('Invalid session.');
$s = stripe('GET', 'checkout/sessions/' . $id, ['expand' => ['line_items']]);
json_out([
    'paid' => ($s['payment_status'] ?? '') === 'paid',
    'status' => $s['payment_status'] ?? '',
    'ref' => $s['client_reference_id'] ?? '',
    'email' => $s['customer_details']['email'] ?? '',
    'name' => $s['metadata']['customer_name'] ?? '',
    'total' => ($s['amount_total'] ?? 0) / 100,
    'shipping' => ($s['shipping_cost']['amount_total'] ?? 0) / 100,
    'testMode' => empty($s['livemode']),
    'items' => array_map(fn($l) => ['name' => $l['description'], 'qty' => $l['quantity'], 'total' => $l['amount_total'] / 100], $s['line_items']['data'] ?? []),
]);
