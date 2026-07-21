<?php
// classes/Attendance.php

include_once 'Database.php';
include_once 'BaseModel.php';
include_once 'CRUDInterface.php';
include_once 'Encryption.php';

class Attendance extends BaseModel implements CRUDInterface {
    protected $table = 'attendance';
    protected $primaryKey = 'id';
    
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * CREATE - Implementation of CRUDInterface
     */
    public function create($data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO attendance (child_id, date, status, notes, marked_by, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $result = $stmt->execute([
                $data['child_id'],
                $data['date'],
                $data['status'],
                $data['notes'] ?? null,
                $data['marked_by']
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
            
            if (isset($data['status'])) {
                $fields[] = "status = ?";
                $params[] = $data['status'];
            }
            if (isset($data['notes'])) {
                $fields[] = "notes = ?";
                $params[] = $data['notes'];
            }
            if (isset($data['check_in_time'])) {
                $fields[] = "check_in_time = ?";
                $params[] = $data['check_in_time'];
            }
            if (isset($data['check_out_time'])) {
                $fields[] = "check_out_time = ?";
                $params[] = $data['check_out_time'];
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
            SELECT a.*, c.name as child_name
            FROM {$this->table} a
            JOIN children c ON a.child_id = c.id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        $attendance = $stmt->fetch();
        
        if ($attendance) {
            $attendance['child_name'] = Encryption::decrypt($attendance['child_name']);
        }
        
        return $attendance;
    }
    
    /**
     * Get attendance by child ID
     */
    public function getAttendanceByChild($childId, $month = null, $year = null) {
        $sql = "SELECT * FROM {$this->table} WHERE child_id = ?";
        $params = [$childId];
        
        if ($month && $year) {
            $sql .= " AND MONTH(date) = ? AND YEAR(date) = ?";
            $params[] = $month;
            $params[] = $year;
        }
        
        $sql .= " ORDER BY date DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Mark attendance (simplified version)
     */
    public function markAttendance($childId, $date, $status, $markedBy, $notes = null) {
        $data = [
            'child_id' => $childId,
            'date' => $date,
            'status' => $status,
            'notes' => $notes,
            'marked_by' => $markedBy
        ];
        
        // Check if attendance already exists for this child on this date
        $check = $this->db->prepare("
            SELECT id FROM {$this->table} WHERE child_id = ? AND date = ?
        ");
        $check->execute([$childId, $date]);
        $existing = $check->fetch();
        
        if ($existing) {
            // Update existing
            return $this->update($existing['id'], ['status' => $status, 'notes' => $notes]);
        } else {
            // Create new
            return $this->create($data);
        }
    }
    
    /**
     * Get attendance summary for children
     */
    public function getAttendanceSummary($childIds) {
        if (empty($childIds)) {
            return ['rate' => 0, 'present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'total' => 0];
        }
        
        $placeholders = implode(',', array_fill(0, count($childIds), '?'));
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
                SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused
            FROM {$this->table}
            WHERE child_id IN ($placeholders)
            AND date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute($childIds);
        $result = $stmt->fetch();
        
        $total = $result['total'] ?: 1;
        return [
            'rate' => ($result['present'] / $total) * 100,
            'present' => $result['present'] ?? 0,
            'absent' => $result['absent'] ?? 0,
            'late' => $result['late'] ?? 0,
            'excused' => $result['excused'] ?? 0,
            'total' => $result['total'] ?? 0
        ];
    }
    
    /**
     * Get today's attendance
     */
    public function getTodayAttendance($classId = null) {
        $sql = "SELECT a.*, c.name as child_name, cl.name as class_name
                FROM {$this->table} a
                JOIN children c ON a.child_id = c.id
                LEFT JOIN classes cl ON c.class_id = cl.id
                WHERE a.date = CURDATE()";
        
        if ($classId) {
            $sql .= " AND c.class_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$classId]);
        } else {
            $stmt = $this->db->query($sql);
        }
        
        $attendance = $stmt->fetchAll();
        
        foreach ($attendance as &$row) {
            $row['child_name'] = Encryption::decrypt($row['child_name']);
        }
        
        return $attendance;
    }
    
    /**
     * Get attendance by date range
     */
    public function getAttendanceByDateRange($childId, $startDate, $endDate) {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table}
            WHERE child_id = ? AND date BETWEEN ? AND ?
            ORDER BY date
        ");
        $stmt->execute([$childId, $startDate, $endDate]);
        return $stmt->fetchAll();
    }
}
?>
