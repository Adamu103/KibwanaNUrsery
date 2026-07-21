<?php
// classes/Encryption.php

class Encryption {
    private static $key;
    private static $cipher = 'AES-256-CBC';
    private static $initialized = false;
    
    private static function init() {
        if (self::$initialized) return;
        $key = getenv('ENCRYPTION_KEY') ?: 'kibwana_nursery_secret_key_2026_secure_32';
        self::$key = hash('sha256', $key, true);
        self::$initialized = true;
    }
    
    public static function encrypt($data) {
        if (empty($data)) return null;
        self::init();
        $ivLength = openssl_cipher_iv_length(self::$cipher);
        $iv = openssl_random_pseudo_bytes($ivLength);
        $encrypted = openssl_encrypt($data, self::$cipher, self::$key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    public static function decrypt($data) {
        if (empty($data)) return null;
        self::init();
        
        try {
            $data = base64_decode($data);
            $ivLength = openssl_cipher_iv_length(self::$cipher);
            
            if (strlen($data) < $ivLength) {
                return $data; // Return as is if not encrypted
            }
            
            $iv = substr($data, 0, $ivLength);
            $encrypted = substr($data, $ivLength);
            
            $decrypted = openssl_decrypt($encrypted, self::$cipher, self::$key, 0, $iv);
            
            if ($decrypted === false) {
                return $data; // Return original if decryption fails
            }
            
            return $decrypted;
        } catch(Exception $e) {
            return $data; // Return original if any error
        }
    }
}
?>
