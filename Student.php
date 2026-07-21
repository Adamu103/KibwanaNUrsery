<?php
// classes/Student.php

include_once 'Database.php';
include_once 'BaseModel.php';
include_once 'CRUDInterface.php'; 
include_once 'Encryption.php';

class Student extends BaseModel implements CRUDInterface {
    protected $table = 'children';
    protected $primaryKey = 'id';
    
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * CREATE - Implementation of CRUDInterface
     */
    public function create($data) {
        try {
            $encryptedName = Encryption::encrypt($data['name']);
            
            $stmt = $this->db->prepare("
                INSERT INTO children (parent_id, name, dob, gender, admission_date, class_id, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $result = $stmt->execute([
                $data['parent_id'],
                $encryptedName,
                $data['dob'],
                $data['gender'],
                $data['admission_date'] ?? date('Y-m-d'),
                $data['class_id'] ?? null,
                $data['status'] ?? 'active'
            ]);
            
            if ($result) {
                return $this->db->lastInsertId();
            }
            return false;
        } catch(Exception $e) {
            return false;
        }
    }
    
    /**
     * READ - Implementation of CRUDInterface
     */
    public function find($id) {
        $stmt = $this->db->prepare("
            SELECT c.*, cl.name as class_name, u.fullname as parent_name
            FROM {$this->table} c
            LEFT JOIN classes cl ON c.class_id = cl.id
            LEFT JOIN parents p ON c.parent_id = p.id
            LEFT JOIN users u ON p.user_id = u.id
            WHERE c.id = ? AND c.status = 'active'
        ");
        $stmt->execute([$id]);
        $child = $stmt->fetch();
        
        if ($child) {
            $child['name'] = Encryption::decrypt($child['name']);
            $child['parent_name'] = !empty($child['parent_name']) ? Encryption::decrypt($child['parent_name']) : null;
        }
        
        return $child;
    }
    
    /**
     * UPDATE - Implementation of CRUDInterface
     */
    public function update($id, $data) {
        try {
            $fields = [];
            $params = [];
            
            if (isset($data['name'])) {
                $fields[] = "name = ?";
                $params[] = Encryption::encrypt($data['name']);
            }
            if (isset($data['dob'])) {
                $fields[] = "dob = ?";
                $params[] = $data['dob'];
            }
            if (isset($data['gender'])) {
                $fields[] = "gender = ?";
                $params[] = $data['gender'];
            }
            if (isset($data['class_id'])) {
                $fields[] = "class_id = ?";
                $params[] = $data['class_id'];
            }
            if (isset($data['admission_date'])) {
                $fields[] = "admission_date = ?";
                $params[] = $data['admission_date'];
            }
            if (isset($data['status'])) {
                $fields[] = "status = ?";
                $params[] = $data['status'];
            }
            
            if (empty($fields)) {
                return false;
            }
            
            $fields[] = "updated_at = NOW()";
            $params[] = $id;
            
            $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch(Exception $e) {
            return false;
        }
    }
    
    /**
     * DELETE - Implementation of CRUDInterface
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE {$this->table} SET status = 'inactive', updated_at = NOW() WHERE id = ?
            ");
            return $stmt->execute([$id]);
        } catch(Exception $e) {
            return false;
        }
    }
    
    /**
     * Get children by parent ID
     */
    public function getChildrenByParent($parentId) {
        $stmt = $this->db->prepare("
            SELECT c.*, cl.name as class_name 
            FROM {$this->table} c
            LEFT JOIN classes cl ON c.class_id = cl.id
            WHERE c.parent_id = ? AND c.status = 'active'
            ORDER BY c.name
        ");
        $stmt->execute([$parentId]);
        $children = $stmt->fetchAll();
        
        foreach ($children as &$child) {
            $child['name'] = Encryption::decrypt($child['name']);
        }
        
        return $children;
    }
    
    /**
     * Get child by ID (alias for find)
     */
    public function getChildById($childId) {
        return $this->find($childId);
    }
    
    /**
     * Get all children (admin)
     */
    public function getAllChildren() {
        $stmt = $this->db->prepare("
            SELECT c.*, u.fullname as parent_name, cl.name as class_name
            FROM {$this->table} c
            JOIN parents p ON c.parent_id = p.id
            JOIN users u ON p.user_id = u.id
            LEFT JOIN classes cl ON c.class_id = cl.id
            WHERE c.status = 'active'
            ORDER BY c.name
        ");
        $stmt->execute();
        $children = $stmt->fetchAll();
        
        foreach ($children as &$child) {
            $child['name'] = Encryption::decrypt($child['name']);
            $child['parent_name'] = !empty($child['parent_name']) ? Encryption::decrypt($child['parent_name']) : null;
        }
        
        return $children;
    }
    
    /**
     * Get all children with parent info (for linking)
     */
    public function getAllChildrenWithParents() {
        $stmt = $this->db->prepare("
            SELECT c.*, u.fullname as parent_name, u.id as parent_user_id, cl.name as class_name
            FROM {$this->table} c
            JOIN parents p ON c.parent_id = p.id
            JOIN users u ON p.user_id = u.id
            LEFT JOIN classes cl ON c.class_id = cl.id
            WHERE c.status = 'active'
            ORDER BY c.name
        ");
        $stmt->execute();
        $children = $stmt->fetchAll();
        
        foreach ($children as &$child) {
            $child['name'] = Encryption::decrypt($child['name']);
            $child['parent_name'] = !empty($child['parent_name']) ? Encryption::decrypt($child['parent_name']) : null;
        }
        
        return $children;
    }
    
    /**
     * Add child (admin)
     */
    public function addChild($data) {
        return $this->create($data);
    }
    
    /**
     * Update child
     */
    public function updateChild($id, $data) {
        return $this->update($id, $data);
    }
    
    /**
     * Link child to parent
     */
    public function linkToParent($childId, $parentId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE {$this->table} SET parent_id = ?, updated_at = NOW() WHERE id = ?
            ");
            return $stmt->execute([$parentId, $childId]);
        } catch(Exception $e) {
            return false;
        }
    }
    
    /**
     * Get total students count
     */
    public function getTotalStudents() {
        return $this->count("status = 'active'");
    }
    
    /**
     * Get children by class
     */
    public function getChildrenByClass($classId) {
        $stmt = $this->db->prepare("
            SELECT c.*, u.fullname as parent_name
            FROM {$this->table} c
            JOIN parents p ON c.parent_id = p.id
            JOIN users u ON p.user_id = u.id
            WHERE c.class_id = ? AND c.status = 'active'
            ORDER BY c.name
        ");
        $stmt->execute([$classId]);
        $children = $stmt->fetchAll();
        
        foreach ($children as &$child) {
            $child['name'] = Encryption::decrypt($child['name']);
            $child['parent_name'] = !empty($child['parent_name']) ? Encryption::decrypt($child['parent_name']) : null;
        }
        
        return $children;
    }
    
    /**
     * Get unlinked children
     */
    public function getUnlinkedChildren() {
        $stmt = $this->db->prepare("
            SELECT c.*, cl.name as class_name
            FROM {$this->table} c
            LEFT JOIN classes cl ON c.class_id = cl.id
            WHERE (c.parent_id IS NULL OR c.parent_id = 0) AND c.status = 'active'
            ORDER BY c.name
        ");
        $stmt->execute();
        $children = $stmt->fetchAll();
        
        foreach ($children as &$child) {
            $child['name'] = Encryption::decrypt($child['name']);
        }
        
        return $children;
    }
    
    /**
     * Search children
     */
    public function search($keyword) {
        $keyword = '%' . $keyword . '%';
        $stmt = $this->db->prepare("
            SELECT c.*, u.fullname as parent_name, cl.name as class_name
            FROM {$this->table} c
            LEFT JOIN parents p ON c.parent_id = p.id
            LEFT JOIN users u ON p.user_id = u.id
            LEFT JOIN classes cl ON c.class_id = cl.id
            WHERE c.status = 'active'
            AND (c.name LIKE ? OR u.fullname LIKE ?)
            ORDER BY c.name
        ");
        $stmt->execute([$keyword, $keyword]);
        $results = $stmt->fetchAll();
        
        foreach ($results as &$result) {
            $result['name'] = Encryption::decrypt($result['name']);
            $result['parent_name'] = !empty($result['parent_name']) ? Encryption::decrypt($result['parent_name']) : null;
        }
        
        return $results;
    }
}
?>
