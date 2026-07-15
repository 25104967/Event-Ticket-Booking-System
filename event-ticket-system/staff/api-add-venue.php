<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_role(['Admin', 'Organizer']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
verify_csrf($data['csrf_token'] ?? null);

$name = trim($data['venue_name'] ?? '');
$address = trim($data['address'] ?? '');
$capacity = (int)($data['max_capacity'] ?? 0);

if ($name === '' || $address === '' || $capacity <= 0) {
    http_response_code(422);
    echo json_encode(['error' => 'Please fill in venue name, address, and a capacity greater than 0.']);
    exit;
}

$db = get_db();
$stmt = $db->prepare('INSERT INTO Venues (Venue_Name, Address, Max_Capacity) VALUES (?,?,?)');
$stmt->execute([$name, $address, $capacity]);

echo json_encode(['id' => (int)$db->lastInsertId(), 'name' => $name]);
