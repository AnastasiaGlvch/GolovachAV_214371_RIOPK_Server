<?php

require_once __DIR__ . '/../utils/JwtUtil.php';
require_once __DIR__ . '/../utils/ResponseUtil.php';
require_once __DIR__ . '/../models/User.php';

class AuthMiddleware {
    
    /**
     * Authorize the request based on JWT token and optional allowed roles
     * 
     * @param array $allowedRoles Optional array of roles that are allowed to access the endpoint
     * @return array User data from the token
     */
    public function authorize($allowedRoles = null) {
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
        
        
        $userModel = new User();
        $user = $userModel->getById($userData['id']);
        
        if (!$user) {
            ResponseUtil::unauthorized('User no longer exists');
        }
        
        if (!$user['active']) {
            ResponseUtil::unauthorized('Account is inactive');
        }
        
      
        if ($allowedRoles !== null && !in_array($userData['role'], $allowedRoles)) {
            ResponseUtil::forbidden('You do not have permission to access this resource');
        }
        
        return $userData;
    }
} 