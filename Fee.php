<?php
// classes/Fee.php

include_once 'Database.php';
include_once 'BaseModel.php';
include_once 'CRUDInterface.php';
include_once 'Encryption.php';

class Fee extends BaseModel implements CRUDInterface {
    protected $table = 'fees';
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
                INSERT INTO fees (student_id, term, academic_year, amount, amount_paid, due_date, status, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $result = $stmt->execute([
                $data['student_id'],
                $data['term'],
                $data['academic_year'],
                $data['amount'],
                $data['amount_paid'] ?? 0,
                $data['due_date'],
                $data['status'] ?? 'pending',
                $data['notes'] ?? ''
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
            
            if (isset($data['amount'])) {
                $fields[] = "amount = ?";
                $params[] = $data['amount'];
            }
            if (isset($data['amount_paid'])) {
                $fields[] = "amount_paid = ?";
                $params[] = $data['amount_paid'];
            }
            if (isset($data['due_date'])) {
                $fields[] = "due_date = ?";
                $params[] = $data['due_date'];
            }
            if (isset($data['status'])) {
                $fields[] = "status = ?";
                $params[] = $data['status'];
            }
            if (isset($data['notes'])) {
                $fields[] = "notes = ?";
                $params[] = $data['notes'];
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
            SELECT f.*, c.name as student_name, u.fullname as parent_name
            FROM {$this->table} f
            JOIN children c ON f.student_id = c.id
            JOIN parents p ON c.parent_id = p.id
            JOIN users u ON p.user_id = u.id
            WHERE f.id = ?
        ");
        $stmt->execute([$id]);
        $fee = $stmt->fetch();
        
        if ($fee) {
            $fee['student_name'] = Encryption::decrypt($fee['student_name']);
            $fee['parent_name'] = !empty($fee['parent_name']) ? Encryption::decrypt($fee['parent_name']) : null;
        }
        
        return $fee;
    }
    
    /**
     * Get total fees due for children - Using student_id
     */
    public function getTotalFeesDue($studentIds) {
        if (empty($studentIds)) return 0;
        
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $stmt = $this->db->prepare("
            SELECT SUM(amount - amount_paid) as total 
            FROM {$this->table}
            WHERE student_id IN ($placeholders) AND status != 'paid'
        ");
        $stmt->execute($studentIds);
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    }
    
    /**
     * Get total paid fees for children - Using student_id
     */
    public function getTotalPaid($studentIds) {
        if (empty($studentIds)) return 0;
        
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $stmt = $this->db->prepare("
            SELECT SUM(amount_paid) as total 
            FROM {$this->table}
            WHERE student_id IN ($placeholders)
        ");
        $stmt->execute($studentIds);
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    }
    
    /**
     * Get fee summary for children - Using student_id
     */
    public function getFeeSummary($studentIds) {
        if (empty($studentIds)) {
            return ['total' => 0, 'paid' => 0, 'balance' => 0];
        }
        
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $stmt = $this->db->prepare("
            SELECT 
                SUM(amount) as total,
                SUM(amount_paid) as paid,
                SUM(amount - amount_paid) as balance
            FROM {$this->table}
            WHERE student_id IN ($placeholders)
        ");
        $stmt->execute($studentIds);
        $result = $stmt->fetch();
        
        return [
            'total' => $result['total'] ?? 0,
            'paid' => $result['paid'] ?? 0,
            'balance' => $result['balance'] ?? 0
        ];
    }
    
    /**
     * Get all fees (admin) - Using student_id
     */
    public function getAllFees() {
        $stmt = $this->db->prepare("
            SELECT f.*, c.name as student_name, u.fullname as parent_name
            FROM {$this->table} f
            JOIN children c ON f.student_id = c.id
            JOIN parents p ON c.parent_id = p.id
            JOIN users u ON p.user_id = u.id
            WHERE c.status = 'active'
            ORDER BY f.due_date DESC
        ");
        $stmt->execute();
        $fees = $stmt->fetchAll();
        
        foreach ($fees as &$fee) {
            $fee['student_name'] = Encryption::decrypt($fee['student_name']);
            $fee['parent_name'] = !empty($fee['parent_name']) ? Encryption::decrypt($fee['parent_name']) : null;
        }
        
        return $fees;
    }
    
    /**
     * Record a payment
     */
    public function recordPayment($feeId, $amount, $paymentMethod, $receiptNumber, $recordedBy) {
        try {
            $this->beginTransaction();
            
            // Insert payment record
            $stmt = $this->db->prepare("
                INSERT INTO fee_payments (fee_id, amount, payment_date, payment_method, receipt_number, recorded_by, created_at)
                VALUES (?, ?, NOW(), ?, ?, ?, NOW())
            ");
            $stmt->execute([$feeId, $amount, $paymentMethod, $receiptNumber, $recordedBy]);
            
            // Update fee
            $updateStmt = $this->db->prepare("
                UPDATE {$this->table}
                SET amount_paid = amount_paid + ?,
                    status = CASE 
                        WHEN amount_paid + ? >= amount THEN 'paid'
                        ELSE 'partial'
                    END,
                    payment_date = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$amount, $amount, $feeId]);
            
            $this->commit();
            return true;
        } catch(Exception $e) {
            $this->rollBack();
            return false;
        }
    }
    
    /**
     * Get fees by status - Using student_id
     */
    public function getFeesByStatus($status) {
        $stmt = $this->db->prepare("
            SELECT f.*, c.name as student_name
            FROM {$this->table} f
            JOIN children c ON f.student_id = c.id
            WHERE f.status = ?
            ORDER BY f.due_date DESC
        ");
        $stmt->execute([$status]);
        $fees = $stmt->fetchAll();
        
        foreach ($fees as &$fee) {
            $fee['student_name'] = Encryption::decrypt($fee['student_name']);
        }
        
        return $fees;
    }
    
    /**
     * Count total fees
     */
    public function countFees() {
        return $this->count();
    }
    
    /**
     * Get fees by student ID
     */
    public function getFeesByStudent($studentId) {
        $stmt = $this->db->prepare("
            SELECT f.*, c.name as student_name
            FROM {$this->table} f
            JOIN children c ON f.student_id = c.id
            WHERE f.student_id = ?
            ORDER BY f.due_date DESC
        ");
        $stmt->execute([$studentId]);
        $fees = $stmt->fetchAll();
        
        foreach ($fees as &$fee) {
            $fee['student_name'] = Encryption::decrypt($fee['student_name']);
        }
        
        return $fees;
    }
    
    /**
     * Get fees by parent ID
     */
    public function getFeesByParent($parentId) {
        $stmt = $this->db->prepare("
            SELECT f.*, c.name as student_name
            FROM {$this->table} f
            JOIN children c ON f.student_id = c.id
            WHERE c.parent_id = ? AND c.status = 'active'
            ORDER BY f.due_date DESC
        ");
        $stmt->execute([$parentId]);
        $fees = $stmt->fetchAll();
        
        foreach ($fees as &$fee) {
            $fee['student_name'] = Encryption::decrypt($fee['student_name']);
        }
        
        return $fees;
    }
}
?>
