<?php
// classes/User.php - FIXED VERSION (No encryption for now)

include_once 'Database.php';
include_once 'BaseModel.php';
include_once 'CRUDInterface.php';

class User extends BaseModel implements CRUDInterface {
    protected $table = 'users';
    protected $primaryKey = 'id';
    
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * CREATE - No encryption for now
     */
    public function create($data) {
        try {
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            
            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, password, fullname, phone, role, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $result = $stmt->execute([
                $data['username'],
                $data['email'] ?? null,
                $hashedPassword,
                $data['fullname'],
                $data['phone'],
                $data['role'] ?? 'parent',
                $data['status'] ?? 'active'
            ]);
            
            if ($result) {
                return $this->db->lastInsertId();
            }
            return false;
        } catch(Exception $e) {
            error_log("Create user error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * READ - No decryption needed
     */
    public function find($id) {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} WHERE id = ? AND status = 'active'
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * UPDATE - No encryption for now
     */
    public function update($id, $data) {
        try {
            $fields = [];
            $params = [];
            
            if (isset($data['fullname'])) {
                $fields[] = "fullname = ?";
                $params[] = $data['fullname'];
            }
            if (isset($data['username'])) {
                $fields[] = "username = ?";
                $params[] = $data['username'];
            }
            if (isset($data['email'])) {
                $fields[] = "email = ?";
                $params[] = $data['email'];
            }
            if (isset($data['phone'])) {
                $fields[] = "phone = ?";
                $params[] = $data['phone'];
            }
            if (isset($data['status'])) {
                $fields[] = "status = ?";
                $params[] = $data['status'];
            }
            if (isset($data['password']) && !empty($data['password'])) {
                $fields[] = "password = ?";
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
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
            error_log("Update user error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * DELETE
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE {$this->table} SET status = 'inactive', updated_at = NOW() WHERE id = ?
            ");
            return $stmt->execute([$id]);
        } catch(Exception $e) {
            error_log("Delete user error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Login user - FIXED: No encryption
     */
    public function login($username, $password) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM {$this->table} 
                WHERE (username = ? OR email = ?) AND status = 'active'
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'fullname' => $user['fullname'],
                    'role' => $user['role'],
                    'email' => $user['email'],
                    'phone' => $user['phone']
                ];
                
                return ['success' => true, 'role' => $user['role']];
            }
            return ['success' => false, 'message' => 'Invalid username or password!'];
        } catch(Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Login failed!'];
        }
    }
    
    /**
     * Register parent only
     */
    public function register($data) {
        try {
            // Check if user exists
            if ($this->exists('username', $data['username'])) {
                return ['success' => false, 'message' => 'Username already exists!'];
            }
            
            if (!empty($data['email']) && $this->exists('email', $data['email'])) {
                return ['success' => false, 'message' => 'Email already exists!'];
            }
            
            // Validate password
            if (strlen($data['password']) < 6) {
                return ['success' => false, 'message' => 'Password must be at least 6 characters!'];
            }
            
            if ($data['password'] !== $data['confirm_password']) {
                return ['success' => false, 'message' => 'Passwords do not match!'];
            }
            
            $this->beginTransaction();
            
            $userId = $this->create([
                'username' => $data['username'],
                'email' => $data['email'] ?? '',
                'password' => $data['password'],
                'fullname' => $data['fullname'],
                'phone' => $data['phone'],
                'role' => 'parent',
                'status' => 'active'
            ]);
            
            if (!$userId) {
                $this->rollBack();
                return ['success' => false, 'message' => 'Registration failed!'];
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO parents (user_id, created_at) VALUES (?, NOW())
            ");
            $stmt->execute([$userId]);
            
            $this->commit();
            
            // Auto login after registration
            return $this->login($data['username'], $data['password']);
            
        } catch(Exception $e) {
            $this->rollBack();
            error_log("Registration error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get current user from session
     */
    public function getCurrentUser() {
        return $_SESSION['user'] ?? null;
    }
    
    /**
     * Check if logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['user']) && !empty($_SESSION['user']);
    }
    
    /**
     * Logout
     */
    public function logout() {
        session_destroy();
        return ['success' => true];
    }
    
    /**
     * Get all parents (for admin)
     */
    public function getAllParents() {
        try {
            $stmt = $this->db->prepare("
                SELECT u.*, p.id as parent_id 
                FROM {$this->table} u
                JOIN parents p ON u.id = p.user_id
                WHERE u.role = 'parent' AND u.status = 'active'
                ORDER BY u.fullname
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch(Exception $e) {
            error_log("Get all parents error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get parent by user ID
     */
    public function getParentByUserId($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT u.*, p.id as parent_id 
                FROM {$this->table} u
                JOIN parents p ON u.id = p.user_id
                WHERE u.id = ? AND u.role = 'parent'
            ");
            $stmt->execute([$userId]);
            return $stmt->fetch();
        } catch(Exception $e) {
            error_log("Get parent by user ID error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Count parents
     */
    public function countParents() {
        try {
            return $this->count("role = 'parent' AND status = 'active'");
        } catch(Exception $e) {
            error_log("Count parents error: " . $e->getMessage());
            return 0;
        }
    }
}
?>
