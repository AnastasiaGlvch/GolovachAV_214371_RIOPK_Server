<?php

require_once __DIR__ . '/../../config/database.php';

class User {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll() {
        $stmt = $this->db->prepare("
            SELECT u.id, u.username, u.email, u.active, r.name as role 
            FROM users u
            JOIN roles r ON u.role_id = r.id
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT u.id, u.username, u.email, u.active, r.name as role 
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.id = :id
        ");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getByUsername($username) {
        $stmt = $this->db->prepare("
            SELECT u.*, r.name as role_name
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.username = :username
        ");
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($username, $password, $email, $role_id) {
        try {
           
            $checkUser = $this->getByUsername($username);
            if ($checkUser) {
                throw new PDOException("Пользователь с именем '{$username}' уже существует");
            }
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $this->db->prepare("
                INSERT INTO users (username, password, email, role_id)
                VALUES (:username, :password, :email, :role_id)
            ");
            
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->bindParam(':role_id', $role_id, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            
            error_log("Ошибка при создании пользователя: " . $e->getMessage());
            
           
            if (strpos($e->getMessage(), "Дублирующаяся запись") !== false || 
                strpos($e->getMessage(), "Duplicate entry") !== false ||
                strpos($e->getMessage(), "уже существует") !== false) {
                throw new PDOException("Пользователь с таким именем уже существует");
            }
            
           
            throw $e;
        }
    }

    public function update($id, $data) {
        $fields = [];
        $params = [':id' => $id];
        
        if (isset($data['username'])) {
            $fields[] = "username = :username";
            $params[':username'] = $data['username'];
        }
        
        if (isset($data['email'])) {
            $fields[] = "email = :email";
            $params[':email'] = $data['email'];
        }
        
        if (isset($data['password'])) {
            $fields[] = "password = :password";
            $params[':password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        if (isset($data['role_id'])) {
            $fields[] = "role_id = :role_id";
            $params[':role_id'] = $data['role_id'];
        }
        
        if (isset($data['active'])) {
            $fields[] = "active = :active";
            $params[':active'] = $data['active'] ? 1 : 0;
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        return $stmt->execute();
    }

    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function validateCredentials($username, $password) {
        $user = $this->getByUsername($username);
        
        if (!$user) {
            return false;
        }
        
        return password_verify($password, $user['password']);
    }
} 