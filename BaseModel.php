<?php
// classes/BaseModel.php

abstract class BaseModel {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    abstract public function create($data);
    abstract public function update($id, $data);
    abstract public function delete($id);
    abstract public function find($id);
    
    public function findAll($conditions = '', $orderBy = '') {
        $sql = "SELECT * FROM {$this->table}";
        if (!empty($conditions)) $sql .= " WHERE " . $conditions;
        if (!empty($orderBy)) $sql .= " ORDER BY " . $orderBy;
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function findAllActive() {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE status = 'active' ORDER BY created_at DESC");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function count($conditions = '') {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        if (!empty($conditions)) $sql .= " WHERE " . $conditions;
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    }
    
    public function exists($field, $value) {
        $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM {$this->table} WHERE {$field} = ?");
        $stmt->execute([$value]);
        $result = $stmt->fetch();
        return ($result['total'] ?? 0) > 0;
    }
    
    public function beginTransaction() { return $this->db->beginTransaction(); }
    public function commit() { return $this->db->commit(); }
    public function rollBack() { return $this->db->rollBack(); }
    public function lastInsertId() { return $this->db->lastInsertId(); }
}
?>
