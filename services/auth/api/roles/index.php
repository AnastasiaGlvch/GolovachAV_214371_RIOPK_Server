<?php

require_once __DIR__ . '/../../src/controllers/RoleController.php';


header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$roleController = new RoleController();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
      
        $roleController->getAll();
        break;
    
    case 'POST':
      
        $roleController->create();
        break;
    
    default:
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Method not allowed"]);
        break;
} 