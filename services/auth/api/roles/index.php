<?php

require_once __DIR__ . '/../../src/controllers/RoleController.php';

// Set CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$roleController = new RoleController();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // Get all roles
        $roleController->getAll();
        break;
    
    case 'POST':
        // Create a new role
        $roleController->create();
        break;
    
    default:
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Method not allowed"]);
        break;
} 