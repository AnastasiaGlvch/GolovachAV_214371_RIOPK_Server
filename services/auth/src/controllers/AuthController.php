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
        
        // Check if username and password are provided
        if (!isset($data['username']) || !isset($data['password'])) {
            ResponseUtil::error('Username and password are required');
        }
        
        $username = $data['username'];
        $password = $data['password'];
        
        // Validate credentials
        if (!$this->userModel->validateCredentials($username, $password)) {
            ResponseUtil::unauthorized('Invalid username or password');
        }
        
        // Get user data for token generation
        $user = $this->userModel->getByUsername($username);
        
        if (!$user['active']) {
            ResponseUtil::unauthorized('Account is inactive');
        }
        
        // Generate token
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
        // Get authorization header
        $headers = getallheaders();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        // Check if token is provided
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
        // Get authorization header
        $headers = getallheaders();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
        
        // Check if token is provided
        if (empty($authHeader) || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            ResponseUtil::unauthorized('Token not provided');
        }
        
        $token = $matches[1];
        $userData = JwtUtil::validateToken($token);
        
        if (!$userData) {
            ResponseUtil::unauthorized('Invalid or expired token');
        }
        
        // Get fresh user data
        $user = $this->userModel->getById($userData['id']);
        
        if (!$user) {
            ResponseUtil::unauthorized('User no longer exists');
        }
        
        if (!$user['active']) {
            ResponseUtil::unauthorized('Account is inactive');
        }
        
        // Generate new token
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