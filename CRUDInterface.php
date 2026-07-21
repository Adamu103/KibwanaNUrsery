<?php
// interfaces/CRUDInterface.php - Interface for polymorphism

interface CRUDInterface {
    /**
     * Create a new record
     * @param array $data Data to insert
     * @return int|bool Inserted ID or false
     */
    public function create($data);
    
    /**
     * Update a record
     * @param int $id Record ID
     * @param array $data Data to update
     * @return bool
     */
    public function update($id, $data);
    
    /**
     * Delete a record (soft delete)
     * @param int $id Record ID
     * @return bool
     */
    public function delete($id);
    
    /**
     * Find a record by ID
     * @param int $id Record ID
     * @return array|false
     */
    public function find($id);
}
?>
