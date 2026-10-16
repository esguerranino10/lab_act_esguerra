<?php
// STEP 19 TEST: send the SAME payment request twice with the SAME idempotency key.
// Place this file in shopping-cart/ and open http://localhost/shopping-cart/test_idempotency.php
// Uses cURL + JSON only. It never touches payment_gateway_db directly.

$apiUrl = 'http://localhost/payment-gateway/api/payments.php';

// New key on every page load so the first request always creates a transaction.
// Add ?key=dup-test-1 to the URL to reuse a fixed key instead.
$key = preg_replace('/[^A-Za-z0-9\-_]/', '', $_GET['key'] ?? ('dup-test-' . date('His')));

$payload = [
    'order_id'        => 9001,
    'amount'          => 100.00,
    'customer_name'   => 'Student Test',
    'payment_method'  => 'CARD',
    'idempotency_key' => $key,
    'outcome'         => 'SUCCESS',
];

function send(string $url, array $payload): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => 8,
    ]);
    $raw  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['http' => $code, 'raw' => $raw, 'json' => is_string($raw) ? json_decode($raw, true) : null];
}

$first  = send($apiUrl, $payload);
$second = send($apiUrl, $payload);

$t1 = $first['json']['transaction_id']  ?? null;
$t2 = $second['json']['transaction_id'] ?? null;
$same = $t1 !== null && $t1 === $t2;
?>
<!doctype html><html lang="en"><meta charset="utf-8"><title>Step 19 - Idempotency test</title>
<body style="font:16px Arial;max-width:800px;margin:40px auto">
<h1>Step 19: Duplicate-request protection</h1>
<p>Idempotency key used for both requests: <code><?= htmlspecialchars($key) ?></code></p>

<h3>Request 1 (HTTP <?= $first['http'] ?>)</h3>
<pre><?= htmlspecialchars((string)$first['raw']) ?></pre>

<h3>Request 2 (HTTP <?= $second['http'] ?>)</h3>
<pre><?= htmlspecialchars((string)$second['raw']) ?></pre>

<h2 style="color:<?= $same ? 'green' : 'crimson' ?>">
<?= $same ? 'PASS: both requests returned the same transaction ID (' . htmlspecialchars($t1) . ')'
          : 'FAIL: transaction IDs differ or a request failed' ?>
</h2>
<p>Now check <code>payment_gateway_db.transactions</code>: there should be only ONE row for this key.</p>
</body></html>