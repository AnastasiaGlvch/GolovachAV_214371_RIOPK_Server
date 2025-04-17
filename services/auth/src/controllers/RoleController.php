<?php

require_once __DIR__ . '/../models/Role.php';
require_once __DIR__ . '/../utils/ResponseUtil.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class RoleController {
    private $roleModel;
    
    public function __construct() {
        $this->roleModel = new Role();
    }
    
    public function getAll() {
        // Anyone with a valid token can see roles
        $authMiddleware = new AuthMiddleware();
        $currentUser = $authMiddleware->authorize();
        
        $roles = $this->roleModel->getAll();
        ResponseUtil::success($roles);
    }
    
    public function getById($id) {
        // Anyone with a valid token can see roles
        $authMiddleware = new AuthMiddleware();
        $currentUser = $authMiddleware->authorize();
        
        $role = $this->roleModel->getById($id);
        
        if (!$role) {
            ResponseUtil::notFound('Role not found');
        }
        
        ResponseUtil::success($role);
    }
    
    public function create() {
        // Only administrators can create roles
        $authMiddleware = new AuthMiddleware();
        $currentUser = $authMiddleware->authorize(['administrator']);
        
        // Get posted data
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validate required fields
        if (!isset($data['name']) || empty($data['name'])) {
            ResponseUtil::error('Role name is required', 400);
        }
        
        // Check if role with this name already exists
        $existingRole = $this->roleModel->getByName($data['name']);
        if ($existingRole) {
            ResponseUtil::error('Role with this name already exists', 400);
        }
        
        // Create role
        $result = $this->roleModel->create($data['name']);
        
        if (!$result) {
            ResponseUtil::error('Failed to create role', 500);
        }
        
        $role = $this->roleModel->getByName($data['name']);
        ResponseUtil::success($role, 'Role created successfully');
    }
    
    public function update($id) {
        // Only administrators can update roles
        $authMiddleware = new AuthMiddleware();
        $currentUser = $authMiddleware->authorize(['administrator']);
        
        // Get posted data
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Validate required fields
        if (!isset($data['name']) || empty($data['name'])) {
            ResponseUtil::error('Role name is required', 400);
        }
        
        // Check if role exists
        $role = $this->roleModel->getById($id);
        if (!$role) {
            ResponseUtil::notFound('Role not found');
        }
        
        // Check if role with this name already exists
        $existingRole = $this->roleModel->getByName($data['name']);
        if ($existingRole && $existingRole['id'] != $id) {
            ResponseUtil::error('Role with this name already exists', 400);
        }
        
        // Update role
        $result = $this->roleModel->update($id, $data['name']);
        
        if (!$result) {
            ResponseUtil::error('Failed to update role', 500);
        }
        
        $updatedRole = $this->roleModel->getById($id);
        ResponseUtil::success($updatedRole, 'Role updated successfully');
    }
    
    public function delete($id) {
        // Only administrators can delete roles
        $authMiddleware = new AuthMiddleware();
        $currentUser = $authMiddleware->authorize(['administrator']);
        
        // Check if role exists
        $role = $this->roleModel->getById($id);
        if (!$role) {
            ResponseUtil::notFound('Role not found');
        }
        
        // Delete role
        $result = $this->roleModel->delete($id);
        
        if ($result === false) {
            ResponseUtil::error('Role is in use and cannot be deleted', 400);
        }
        
        ResponseUtil::success(null, 'Role deleted successfully');
    }
} 