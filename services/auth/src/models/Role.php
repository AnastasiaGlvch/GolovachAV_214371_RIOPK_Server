<?php

require_once __DIR__ . '/../../config/database.php';

class Role {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll() {
        $stmt = $this->db->prepare("SELECT * FROM roles");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM roles WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getByName($name) {
        $stmt = $this->db->prepare("SELECT * FROM roles WHERE name = :name");
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($name) {
        $stmt = $this->db->prepare("INSERT INTO roles (name) VALUES (:name)");
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        return $stmt->execute();
    }

    public function update($id, $name) {
        $stmt = $this->db->prepare("UPDATE roles SET name = :name WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        return $stmt->execute();
    }

    public function delete($id) {
        // Check if any users are using this role
        $check = $this->db->prepare("SELECT COUNT(*) FROM users WHERE role_id = :id");
        $check->bindParam(':id', $id, PDO::PARAM_INT);
        $check->execute();
        
        if ($check->fetchColumn() > 0) {
            return false; // Role is in use, cannot delete
        }
        
        $stmt = $this->db->prepare("DELETE FROM roles WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
} 