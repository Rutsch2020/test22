<?php
/**
 * Scanner Controller
 * Handles all scanner-related operations
 * 
 * WICHTIG: Keine Namespaces verwenden!
 */

if (!defined('ABSPATH')) {
    exit;
}

class SMP_Scanner_Controller {
    
    private $product_model;
    private $transaction_model;
    private $session_model;
    
    public function __construct() {
        $this->product_model = new SMP_Product();
        $this->transaction_model = new SMP_Transaction();
        $this->session_model = new SMP_Session();
    }
    
    /**
     * Handle barcode scan
     */
    public function handle_scan($barcode) {
        // Validate barcode format
        if (!$this->validate_barcode_format($barcode)) {
            return array(
                'success' => false,
                'message' => 'Ungültiges Barcode-Format'
            );
        }
        
        // Get active session
        $session = $this->session_model->get_active_session();
        if (!$session) {
            return array(
                'success' => false,
                'message' => 'Keine aktive Session. Bitte starten Sie eine neue Session.'
            );
        }
        
        // Find product
        $product = $this->product_model->get_by_barcode($barcode);
        
        if (!$product) {
            return array(
                'success' => true,
                'data' => array(
                    'action' => 'not_found',
                    'barcode' => $barcode,
                    'message' => 'Produkt nicht gefunden'
                )
            );
        }
        
        // Check stock
        if ($product->stock_quantity <= 0) {
            return array(
                'success' => false,
                'message' => 'Produkt ist nicht auf Lager'
            );
        }
        
        // Return product data
        return array(
            'success' => true,
            'data' => array(
                'action' => 'found',
                'product' => array(
                    'id' => $product->id,
                    'name' => $product->name,
                    'barcode' => $product->barcode,
                    'price' => number_format((float)$product->price, 2, '.', ''),
                    'stock' => $product->stock_quantity,
                    'category' => $product->category,
                    'image_url' => $product->image_url
                )
            )
        );
    }
    
    /**
     * Process quick sale
     */
    public function process_quick_sale($product_id) {
        // Get active session
        $session = $this->session_model->get_active_session();
        if (!$session) {
            return array(
                'success' => false,
                'message' => 'Keine aktive Session'
            );
        }
        
        // Get product
        $product = $this->product_model->get($product_id);
        if (!$product) {
            return array(
                'success' => false,
                'message' => 'Produkt nicht gefunden'
            );
        }
        
        // Check stock
        if ($product->stock_quantity <= 0) {
            return array(
                'success' => false,
                'message' => 'Produkt nicht auf Lager'
            );
        }
        
        // Create transaction
        $transaction_data = array(
            'session_id' => $session->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_barcode' => $product->barcode,
            'transaction_type' => 'sale',
            'quantity' => 1,
            'unit_price' => $product->price,
            'amount' => $product->price,
            'payment_method' => 'cash',
            'notes' => 'Quick Sale via Scanner'
        );
        
        $transaction_id = $this->transaction_model->create($transaction_data);
        
        if (!$transaction_id) {
            return array(
                'success' => false,
                'message' => 'Transaktion konnte nicht erstellt werden'
            );
        }
        
        // Update product stock
        $this->product_model->update_stock($product_id, 1, 'subtract');
        
        // Update session totals
        $this->session_model->update_session_totals($session->id);
        
        // Log scanner activity
        $this->log_scanner_activity('quick_sale', array(
            'product_id' => $product_id,
            'transaction_id' => $transaction_id
        ));
        
        return array(
            'success' => true,
            'data' => array(
                'transaction_id' => $transaction_id,
                'product' => array(
                    'name' => $product->name,
                    'price' => number_format((float)$product->price, 2, ',', '.'),
                    'new_stock' => $product->stock_quantity - 1
                ),
                'session' => array(
                    'total_revenue' => $this->session_model->get_session_revenue($session->id),
                    'transaction_count' => $this->session_model->get_session_transaction_count($session->id)
                )
            )
        );
    }
    
    /**
     * Get quick products for display
     */
    public function get_quick_products() {
        $products = $this->product_model->get_top_selling(6);
        
        $formatted_products = array();
        foreach ($products as $product) {
            $formatted_products[] = array(
                'id' => $product->id,
                'name' => $product->name,
                'price' => number_format((float)$product->price, 2, ',', '.'),
                'stock' => $product->stock_quantity,
                'category' => $product->category
            );
        }
        
        return $formatted_products;
    }
    
    /**
     * Validate barcode format
     */
    private function validate_barcode_format($barcode) {
        // Remove whitespace
        $barcode = trim($barcode);
        
        // Check length
        if (strlen($barcode) < 3 || strlen($barcode) > 20) {
            return false;
        }
        
        // Check format based on type
        $format = $this->detect_barcode_format($barcode);
        
        switch ($format) {
            case 'EAN-13':
                return preg_match('/^\d{13}$/', $barcode) && $this->validate_ean13_checksum($barcode);
                
            case 'EAN-8':
                return preg_match('/^\d{8}$/', $barcode) && $this->validate_ean8_checksum($barcode);
                
            case 'UPC-A':
                return preg_match('/^\d{12}$/', $barcode) && $this->validate_upca_checksum($barcode);
                
            case 'CODE-128':
            case 'CODE-39':
                return preg_match('/^[A-Z0-9\-\.\ \$\/\+\%]+$/i', $barcode);
                
            default:
                return true; // Allow other formats
        }
    }
    
    /**
     * Detect barcode format
     */
    private function detect_barcode_format($barcode) {
        if (preg_match('/^\d{13}$/', $barcode)) {
            return 'EAN-13';
        } elseif (preg_match('/^\d{8}$/', $barcode)) {
            return 'EAN-8';
        } elseif (preg_match('/^\d{12}$/', $barcode)) {
            return 'UPC-A';
        } elseif (preg_match('/^[A-Z0-9\-\.\ \$\/\+\%]+$/i', $barcode)) {
            if (strlen($barcode) <= 20) {
                return 'CODE-39';
            } else {
                return 'CODE-128';
            }
        }
        
        return 'UNKNOWN';
    }
    
    /**
     * Validate EAN-13 checksum
     */
    private function validate_ean13_checksum($barcode) {
        if (strlen($barcode) != 13) return false;
        
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int)$barcode[$i] * (($i % 2 == 0) ? 1 : 3);
        }
        
        $checksum = (10 - ($sum % 10)) % 10;
        return $checksum == (int)$barcode[12];
    }
    
    /**
     * Validate EAN-8 checksum
     */
    private function validate_ean8_checksum($barcode) {
        if (strlen($barcode) != 8) return false;
        
        $sum = 0;
        for ($i = 0; $i < 7; $i++) {
            $sum += (int)$barcode[$i] * (($i % 2 == 0) ? 3 : 1);
        }
        
        $checksum = (10 - ($sum % 10)) % 10;
        return $checksum == (int)$barcode[7];
    }
    
    /**
     * Validate UPC-A checksum
     */
    private function validate_upca_checksum($barcode) {
        if (strlen($barcode) != 12) return false;
        
        $sum = 0;
        for ($i = 0; $i < 11; $i++) {
            $sum += (int)$barcode[$i] * (($i % 2 == 0) ? 3 : 1);
        }
        
        $checksum = (10 - ($sum % 10)) % 10;
        return $checksum == (int)$barcode[11];
    }
    
    /**
     * Log scanner activity
     */
    private function log_scanner_activity($action, $data = array()) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'smp_scanner_logs',
            array(
                'user_id' => get_current_user_id(),
                'action' => $action,
                'data' => json_encode($data),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
    }
}