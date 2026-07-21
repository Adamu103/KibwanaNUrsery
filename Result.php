<?php
// classes/Result.php

include_once 'Database.php';
include_once 'BaseModel.php';
include_once 'CRUDInterface.php';
include_once 'Encryption.php';

class Result extends BaseModel implements CRUDInterface {
    protected $table = 'results';
    protected $primaryKey = 'id';
    
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * CREATE - Implementation of CRUDInterface
     */
    public function create($data) {
        try {
            $grade = $data['grade'] ?? $this->calculateGrade($data['score']);
            
            $stmt = $this->db->prepare("
                INSERT INTO results (child_id, subject, term, academic_year, score, grade, remarks, teacher_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $result = $stmt->execute([
                $data['child_id'],
                $data['subject'],
                $data['term'],
                $data['academic_year'],
                $data['score'],
                $grade,
                $data['remarks'] ?? '',
                $data['teacher_id'] ?? $_SESSION['user']['id']
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
     * UPDATE - Implementation of CRUDInterface
     */
    public function update($id, $data) {
        try {
            $fields = [];
            $params = [];
            
            if (isset($data['subject'])) {
                $fields[] = "subject = ?";
                $params[] = $data['subject'];
            }
            if (isset($data['score'])) {
                $fields[] = "score = ?";
                $params[] = $data['score'];
                $fields[] = "grade = ?";
                $params[] = $this->calculateGrade($data['score']);
            }
            if (isset($data['remarks'])) {
                $fields[] = "remarks = ?";
                $params[] = $data['remarks'];
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
            $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
            return $stmt->execute([$id]);
        } catch(Exception $e) {
            return false;
        }
    }
    
    /**
     * READ - Implementation of CRUDInterface
     */
    public function find($id) {
        $stmt = $this->db->prepare("
            SELECT r.*, c.name as child_name
            FROM {$this->table} r
            JOIN children c ON r.child_id = c.id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        if ($result) {
            $result['child_name'] = Encryption::decrypt($result['child_name']);
        }
        
        return $result;
    }
    
    /**
     * Get results by child ID
     */
    public function getResultsByChild($childId, $term = null) {
        $sql = "SELECT * FROM {$this->table} WHERE child_id = ?";
        $params = [$childId];
        
        if ($term) {
            $sql .= " AND term = ?";
            $params[] = $term;
        }
        
        $sql .= " ORDER BY term DESC, subject";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Add result (simplified version)
     */
    public function addResult($data) {
        return $this->create($data);
    }
    
    /**
     * Get all subjects
     */
    public function getSubjects() {
        $stmt = $this->db->query("
            SELECT DISTINCT subject FROM {$this->table} ORDER BY subject
        ");
        return $stmt->fetchAll();
    }
    
    /**
     * Get child results summary
     */
    public function getResultsSummary($childId) {
        $stmt = $this->db->prepare("
            SELECT 
                term,
                AVG(score) as average_score,
                COUNT(*) as subjects_count,
                MAX(score) as highest_score,
                MIN(score) as lowest_score
            FROM {$this->table}
            WHERE child_id = ?
            GROUP BY term
            ORDER BY term DESC
        ");
        $stmt->execute([$childId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Calculate grade from score
     */
    public function calculateGrade($score) {
        if ($score >= 80) return 'A';
        if ($score >= 70) return 'B';
        if ($score >= 60) return 'C';
        if ($score >= 50) return 'D';
        return 'E';
    }
    
    /**
     * Get all results (admin)
     */
    public function getAllResults() {
        $stmt = $this->db->prepare("
            SELECT r.*, c.name as child_name, u.fullname as parent_name
            FROM {$this->table} r
            JOIN children c ON r.child_id = c.id
            JOIN parents p ON c.parent_id = p.id
            JOIN users u ON p.user_id = u.id
            ORDER BY r.term DESC, r.child_id
        ");
        $stmt->execute();
        $results = $stmt->fetchAll();
        
        foreach ($results as &$result) {
            $result['child_name'] = Encryption::decrypt($result['child_name']);
            $result['parent_name'] = !empty($result['parent_name']) ? Encryption::decrypt($result['parent_name']) : null;
        }
        
        return $results;
    }
    
    /**
     * Get result by ID (alias for find)
     */
    public function getResultById($resultId) {
        return $this->find($resultId);
    }
}
?>
