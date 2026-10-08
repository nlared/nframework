<?php
header('Content-Type: application/json');

$signed_request = (string) ($_POST['signed_request'] ?? '');
$data = parse_signed_request($signed_request);
if ($data === null) {
  http_response_code(400);
  echo json_encode(['error' => 'invalid signature']);
  exit;
}
$user_id = $data['user_id'];

// Start data deletion

$status_url = 'https://www.<your_website>.com/deletion?id=abc123'; // URL to track the deletion
$confirmation_code = 'abc123'; // unique code for the deletion request

$data = array(
  'url' => $status_url,
  'confirmation_code' => $confirmation_code
);
echo json_encode($data);

function parse_signed_request($signed_request) {
  global $config;
  if (substr_count($signed_request, '.') !== 1) {
    return null;
  }
  list($encoded_sig, $payload) = explode('.', $signed_request, 2);

  $secret = (string) ($config['facebook_oauth_client_secret'] ?? '');
  if ($secret === '') {
    return null;
  }

  // decode the data
  $sig = base64_url_decode($encoded_sig);
  $data = json_decode(base64_url_decode($payload), true);

  // confirm the signature
  $expected_sig = hash_hmac('sha256', $payload, $secret, $raw = true);
  if (!hash_equals($expected_sig, (string) $sig)) {
    error_log('Bad Signed JSON signature!');
    return null;
  }

  return $data;
}

function base64_url_decode($input) {
  return base64_decode(strtr($input, '-_', '+/'));
}
?>