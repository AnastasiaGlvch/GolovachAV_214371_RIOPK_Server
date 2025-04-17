<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Role.php';
require_once __DIR__ . '/../utils/ResponseUtil.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class UserController {
    private $userModel;
    private $roleModel;
    
    public function __construct() {
        $this->userModel = new User();
        $this->roleModel = new Role();
    }
    
    public function getAll() {
        // Only administrators can get all users
        $authMiddleware = new AuthMiddleware();
        $currentUser = $authMiddleware->authorize(['administrator']);
        
        $users = $this->userModel->getAll();
        ResponseUtil::success($users);
    }
    
    public function getById($id) {
        // Administrators can get any user, other users can only get themselves
        $authMiddleware = new AuthMiddleware();
        $currentUser = $authMiddleware->authorize();
        
        if ($currentUser['role'] !== 'administrator' && $currentUser['id'] != $id) {
            ResponseUtil::forbidden('You can only view your own profile');
        }
        
        $user = $this->userModel->getById($id);
        
        if (!$user) {
            ResponseUtil::notFound('User not found');
        }
        
        ResponseUtil::success($user);
    }
    
    public function create() {
        // Only administrators can create users
        $authMiddleware = new AuthMiddleware();
        $currentUser = $authMiddleware->authorize(['administrator']);
        
        // Get posted data
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validate required fields
        $requiredFields = ['username', 'password', 'email', 'role_id'];
        $errors = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[$field] = "$field is required";
            }
        }
        
        if (!empty($errors)) {
            ResponseUtil::error('Validation failed', 400, $errors);
        }
        
        // Validate role exists
        $role = $this->roleModel->getById($data['role_id']);
        if (!$role) {
            ResponseUtil::error('Invalid role', 400, ['role_id' => 'Role does not exist']);
        }
        
        // Check if user already exists
        $existingUser = $this->userModel->getByUsername($data['username']);
        if ($existingUser) {
            ResponseUtil::error('User already exists', 400, ['username' => 'Username already taken']);
            return;
        }
        
        try {
            // Create user
            $result = $this->userModel->create(
                $data['username'],
                $data['password'],
                $data['email'],
                $data['role_id']
            );
            
            if (!$result) {
                ResponseUtil::error('Failed to create user', 500);
                return;
            }
            
            $newUser = $this->userModel->getByUsername($data['username']);
            ResponseUtil::success(['id' => $newUser['id']], 'User created successfully');
        } catch (PDOException $e) {
            $errorMessage = $e->getMessage();
            
            if (strpos($errorMessage, "уже существует") !== false || 
                strpos($errorMessage, "Duplicate entry") !== false) {
                ResponseUtil::error('User already exists', 400, ['username' => 'Username already taken']);
            } else {
                ResponseUtil::error('Database error: ' . $errorMessage, 500);
            }
        } catch (Exception $e) {
            ResponseUtil::error('Error creating user: ' . $e->getMessage(), 500);
        }
    }
    
    public function update($id) {
        // Get posted data
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (empty($data)) {
            ResponseUtil::error('No data provided');
        }
        
        // Check authorization
        $authMiddleware = new AuthMiddleware();
        $currentUser = $authMiddleware->authorize();
        
        // Non-administrators can only update their own profile and cannot change role
        if ($currentUser['role'] !== 'administrator' && $currentUser['id'] != $id) {
            ResponseUtil::forbidden('You can only update your own profile');
        }
        
        // Only administrators can change user roles
        if (isset($data['role_id']) && $currentUser['role'] !== 'administrator') {
            ResponseUtil::forbidden('Only administrators can change user roles');
        }
        
        // Validate role exists if provided
        if (isset($data['role_id'])) {
            $role = $this->roleModel->getById($data['role_id']);
            if (!$role) {
                ResponseUtil::error('Invalid role', 400, ['role_id' => 'Role does not exist']);
            }
        }
        
        // Update user
        $result = $this->userModel->update($id, $data);
        
        if (!$result) {
            ResponseUtil::error('Failed to update user', 500);
        }
        
        $user = $this->userModel->getById($id);
        ResponseUtil::success($user, 'User updated successfully');
    }
    
    public function delete($id) {
        // Only administrators can delete users
        $authMiddleware = new AuthMiddleware();
        $currentUser = $authMiddleware->authorize(['administrator']);
        
        // Prevent deleting yourself
        if ($currentUser['id'] == $id) {
            ResponseUtil::error('You cannot delete your own account', 400);
        }
        
        $user = $this->userModel->getById($id);
        
        if (!$user) {
            ResponseUtil::notFound('User not found');
        }
        
        $result = $this->userModel->delete($id);
        
        if (!$result) {
            ResponseUtil::error('Failed to delete user', 500);
        }
        
        ResponseUtil::success(null, 'User deleted successfully');
    }
} 