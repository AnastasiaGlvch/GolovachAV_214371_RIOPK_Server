<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/JwtUtil.php';
require_once __DIR__ . '/../utils/ResponseUtil.php';

class AuthController {
    private $userModel;
    
    public function __construct() {
        $this->userModel = new User();
    }
    
    public function login() {
        // Get posted data
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (!isset($data['username']) || !isset($data['password'])) {
            ResponseUtil::error('Username and password are required');
        }
        
        $username = $data['username'];
        $password = $data['password'];
        
        
        if (!$this->userModel->validateCredentials($username, $password)) {
            ResponseUtil::unauthorized('Invalid username or password');
        }
        
        
        $user = $this->userModel->getByUsername($username);
        
        if (!$user['active']) {
            ResponseUtil::unauthorized('Account is inactive');
        }
        
        
        $token = JwtUtil::generateToken($user);
        
        ResponseUtil::success([
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role_name']
            ]
        ]);
    }
    
    public function validateToken() {
       
        $headers = getallheaders();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        
        if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            ResponseUtil::unauthorized('Token not provided');
        }
        
        $token = $matches[1];
        $userData = JwtUtil::validateToken($token);
        
        if (!$userData) {
            ResponseUtil::unauthorized('Invalid or expired token');
        }
        
        ResponseUtil::success([
            'user' => $userData
        ]);
    }
    
    public function refreshToken() {
        
        $headers = getallheaders();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        
        if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            ResponseUtil::unauthorized('Token not provided');
        }
        
        $token = $matches[1];
        $userData = JwtUtil::validateToken($token);
        
        if (!$userData) {
            ResponseUtil::unauthorized('Invalid or expired token');
        }
        
        
        $user = $this->userModel->getById($userData['id']);
        
        if (!$user) {
            ResponseUtil::unauthorized('User no longer exists');
        }
        
        if (!$user['active']) {
            ResponseUtil::unauthorized('Account is inactive');
        }
        
       
        $newToken = JwtUtil::generateToken($user);
        
        ResponseUtil::success([
            'token' => $newToken,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role']
            ]
        ]);
    }
} 