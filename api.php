<?php
declare(strict_types=1);
require __DIR__ . '/store.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Strict']);
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$token = $_SESSION['csrf'];
session_write_close();
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $input = null;
    if ($method === 'POST') {
        if (!hash_equals($token, $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
            http_response_code(403);
            throw new InvalidArgumentException('Your session changed. Refresh the page before saving.');
        }
        $body = file_get_contents('php://input', false, null, 0, 4097);
        if ($body === false || strlen($body) > 4096) throw new InvalidArgumentException('Request is too large.');
        $input = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($input)) throw new InvalidArgumentException('Expected a JSON object.');
    } elseif ($method !== 'GET') {
        header('Allow: GET, POST');
        http_response_code(405);
        throw new InvalidArgumentException('Method not allowed.');
    }
    echo json_encode(inventory(__DIR__ . '/storage', $input) + ['csrf' => $token], JSON_THROW_ON_ERROR);
} catch (DomainException $e) {
    http_response_code(409);
    echo json_encode(['error' => $e->getMessage()]);
} catch (InvalidArgumentException $e) {
    if (http_response_code() < 400) http_response_code(422);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Inventory could not be loaded or saved. Check server logs and storage permissions.']);
}
