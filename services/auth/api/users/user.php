<?php

require_once __DIR__ . '/../../src/controllers/UserController.php';

// Set CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get user ID from URL parameter
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "User ID is required"]);
    exit;
}

$id = intval($_GET['id']);
$userController = new UserController();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // Get user by ID
        $userController->getById($id);
        break;
    
    case 'PUT':
        // Update user
        $userController->update($id);
        break;
    
    case 'DELETE':
        // Delete user
        $userController->delete($id);
        break;
    
    default:
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Method not allowed"]);
        break;
} 