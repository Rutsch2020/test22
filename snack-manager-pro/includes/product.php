<?php
/**
 * Product Model für Snack Manager Pro
 * 
 * @package SnackManagerPro
 * @subpackage Models
 * @since 1.0.0
 */

// Direktzugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

class SMP_Product {
    
    /**
     * Tabellen-Name
     * @var string
     */
    private $table_name;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'smp_products';
    }
    
    /**
     * Produkt speichern (Erstellen oder Aktualisieren)
     *
     * @param array $data Produktdaten
     * @return int|false Product ID bei Erfolg, false bei Fehler
     */
    public function save($data) {
        global $wpdb;
        
        // Daten vorbereiten
        $product_data = array(
            'name' => sanitize_text_field($data['name']),
            'barcode' => sanitize_text_field($data['barcode'] ?? ''),
            'category' => sanitize_text_field($data['category'] ?? ''),
            'price' => floatval($data['price']),
            'stock_quantity' => intval($data['stock_quantity'] ?? 0),
            'stock_min_quantity' => intval($data['stock_min_quantity'] ?? 10),
            'image_url' => esc_url_raw($data['image_url'] ?? ''),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'status' => in_array($data['status'] ?? 'active', ['active', 'inactive']) ? $data['status'] : 'active'
        );
        
        // Format-Spezifikationen für wpdb
        $formats = array(
            '%s', // name
            '%s', // barcode
            '%s', // category
            '%f', // price
            '%d', // stock_quantity
            '%d', // stock_min_quantity
            '%s', // image_url
            '%s', // description
            '%s'  // status
        );
        
        // Update oder Insert
        if (!empty($data['id'])) {
            // Update
            $product_id = intval($data['id']);
            $result = $wpdb->update(
                $this->table_name,
                $product_data,
                array('id' => $product_id),
                $formats,
                array('%d')
            );
            
            return $result !== false ? $product_id : false;
        } else {
            // Insert
            $result = $wpdb->insert(
                $this->table_name,
                $product_data,
                $formats
            );
            
            return $result ? $wpdb->insert_id : false;
        }
    }
    
    /**
     * Produkt nach ID abrufen
     *
     * @param int $id Product ID
     * @return object|null
     */
    public function get($id) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        );
        
        return $wpdb->get_row($query);
    }
    
    /**
     * Alias für get() für Kompatibilität
     */
    public function get_product($id) {
        return $this->get($id);
    }
    
    /**
     * Produkt nach Barcode abrufen
     *
     * @param string $barcode
     * @return object|null
     */
    public function get_by_barcode($barcode) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE barcode = %s AND status = 'active'",
            $barcode
        );
        
        return $wpdb->get_row($query);
    }
    
    /**
     * Alias für get_by_barcode() für Kompatibilität
     */
    public function get_product_by_barcode($barcode) {
        return $this->get_by_barcode($barcode);
    }
    
    /**
     * Alle Produkte abrufen
     *
     * @param array $args Optionale Filter
     * @return array
     */
    public function get_all($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => '',
            'category' => '',
            'search' => '',
            'orderby' => 'name',
            'order' => 'ASC',
            'limit' => -1,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Query aufbauen
        $query = "SELECT * FROM {$this->table_name} WHERE 1=1";
        
        // Status Filter
        if (!empty($args['status'])) {
            $query .= $wpdb->prepare(" AND status = %s", $args['status']);
        }
        
        // Kategorie Filter
        if (!empty($args['category'])) {
            $query .= $wpdb->prepare(" AND category = %s", $args['category']);
        }
        
        // Suche
        if (!empty($args['search'])) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $query .= $wpdb->prepare(" AND (name LIKE %s OR barcode LIKE %s OR description LIKE %s)", $search, $search, $search);
        }
        
        // Sortierung
        $allowed_orderby = array('name', 'price', 'stock_quantity', 'category', 'created_at', 'updated_at');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'name';
        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';
        $query .= " ORDER BY {$orderby} {$order}";
        
        // Limit
        if ($args['limit'] > 0) {
            $query .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Alias für get_all() für Kompatibilität
     */
    public function get_products($args = array()) {
        return $this->get_all($args);
    }
    
    /**
     * Produkt löschen
     *
     * @param int $id Product ID
     * @return bool
     */
    public function delete($id) {
        global $wpdb;
        
        // Soft delete - Status auf inactive setzen
        $result = $wpdb->update(
            $this->table_name,
            array('status' => 'inactive'),
            array('id' => $id),
            array('%s'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Lagerbestand aktualisieren
     *
     * @param int $id Product ID
     * @param int $quantity Menge
     * @param string $type 'add', 'subtract' oder 'set'
     * @return bool
     */
    public function update_stock($id, $quantity, $type = 'set') {
        global $wpdb;
        
        $product = $this->get($id);
        if (!$product) {
            return false;
        }
        
        $new_stock = 0;
        
        switch ($type) {
            case 'add':
                $new_stock = $product->stock_quantity + $quantity;
                break;
            case 'subtract':
                $new_stock = max(0, $product->stock_quantity - $quantity);
                break;
            case 'set':
            default:
                $new_stock = max(0, $quantity);
                break;
        }
        
        $result = $wpdb->update(
            $this->table_name,
            array('stock_quantity' => $new_stock),
            array('id' => $id),
            array('%d'),
            array('%d')
        );
        
        // Trigger für niedrigen Lagerbestand
        if ($new_stock <= $product->stock_min_quantity) {
            do_action('smp_low_stock_alert', $product, $new_stock);
        }
        
        return $result !== false;
    }
    
    /**
     * Produkt bei Verkauf verarbeiten
     *
     * @param int $id Product ID
     * @param int $quantity Verkaufte Menge
     * @return bool
     */
    public function process_sale($id, $quantity = 1) {
        return $this->update_stock($id, $quantity, 'subtract');
    }
    
    /**
     * Kategorien abrufen
     *
     * @return array
     */
    public function get_categories() {
        global $wpdb;
        
        $query = "SELECT DISTINCT category FROM {$this->table_name} 
                 WHERE category IS NOT NULL AND category != '' 
                 ORDER BY category ASC";
        
        $results = $wpdb->get_col($query);
        
        return $results ? $results : array();
    }
    
    /**
     * Produkte mit niedrigem Lagerbestand
     *
     * @param int $limit
     * @return array
     */
    public function get_low_stock_products($limit = 10) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE stock_quantity <= stock_min_quantity 
            AND status = 'active' 
            ORDER BY stock_quantity ASC 
            LIMIT %d",
            $limit
        );
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Statistiken abrufen
     *
     * @return object
     */
    public function get_stats() {
        global $wpdb;
        
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_products,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_products,
                COUNT(CASE WHEN stock_quantity > 0 THEN 1 END) as in_stock,
                COUNT(CASE WHEN stock_quantity = 0 THEN 1 END) as out_of_stock,
                COUNT(CASE WHEN stock_quantity <= stock_min_quantity THEN 1 END) as low_stock,
                SUM(stock_quantity * price) as inventory_value
            FROM {$this->table_name}
        ");
        
        return $stats;
    }
    
    /**
     * Bulk-Import von Produkten
     *
     * @param array $products Array von Produktdaten
     * @return array Ergebnis mit Erfolg/Fehler-Zählern
     */
    public function bulk_import($products) {
        $results = array(
            'success' => 0,
            'failed' => 0,
            'errors' => array()
        );
        
        foreach ($products as $index => $product) {
            $result = $this->save($product);
            
            if ($result) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = sprintf(
                    __('Fehler bei Produkt %d: %s', 'snack-manager-pro'),
                    $index + 1,
                    $product['name'] ?? 'Unbekannt'
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Produkt-Suche für Autocomplete
     *
     * @param string $term Suchbegriff
     * @param int $limit
     * @return array
     */
    public function search_autocomplete($term, $limit = 10) {
        global $wpdb;
        
        $search = '%' . $wpdb->esc_like($term) . '%';
        
        $query = $wpdb->prepare(
            "SELECT id, name, barcode, price, stock_quantity 
            FROM {$this->table_name} 
            WHERE status = 'active' 
            AND (name LIKE %s OR barcode LIKE %s) 
            ORDER BY name ASC 
            LIMIT %d",
            $search,
            $search,
            $limit
        );
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Neues Produkt erstellen - Alias für Kompatibilität
     */
    public function create_product($data) {
        return $this->save($data);
    }
    
    /**
     * NEUE METHODE: Erstellt ein neues Produkt
     *
     * @param array $data Produktdaten
     * @return int|false Product ID bei Erfolg, false bei Fehler
     */
    public function create($data) {
        // Validierung
        $validation = $this->validate($data);
        if ($validation !== true) {
            return false;
        }
        
        // ID entfernen falls vorhanden (für create)
        unset($data['id']);
        
        // Save aufrufen
        return $this->save($data);
    }
    
    /**
     * Preis-Historie abrufen
     *
     * @param int $id Product ID
     * @param int $days Anzahl Tage zurück
     * @return array
     */
    public function get_price_history($id, $days = 30) {
        global $wpdb;
        
        // Diese Funktion würde eine separate Preis-Historie-Tabelle benötigen
        // Für den Moment geben wir ein leeres Array zurück
        return array();
    }
    
    /**
     * Validierung der Produktdaten
     *
     * @param array $data
     * @return array|true Array mit Fehlern oder true bei Erfolg
     */
    public function validate($data) {
        $errors = array();
        
        // Name ist Pflichtfeld
        if (empty($data['name'])) {
            $errors['name'] = __('Produktname ist erforderlich.', 'snack-manager-pro');
        }
        
        // Preis muss positiv sein
        if (!isset($data['price']) || floatval($data['price']) < 0) {
            $errors['price'] = __('Preis muss eine positive Zahl sein.', 'snack-manager-pro');
        }
        
        // Barcode muss eindeutig sein (wenn angegeben)
        if (!empty($data['barcode'])) {
            $existing = $this->get_by_barcode($data['barcode']);
            if ($existing && (empty($data['id']) || $existing->id != $data['id'])) {
                $errors['barcode'] = __('Dieser Barcode wird bereits verwendet.', 'snack-manager-pro');
            }
        }
        
        return empty($errors) ? true : $errors;
    }
    
    /**
     * NEUE METHODE: Holt die meistverkauften Produkte
     *
     * @param int $limit Anzahl der Produkte
     * @param string $period Zeitraum (today, week, month, all)
     * @return array Array von Produkten mit Verkaufszahlen
     */
    public function get_top_selling($limit = 10, $period = 'all') {
        global $wpdb;
        
        $transactions_table = $wpdb->prefix . 'smp_transactions';
        
        // Zeitfilter erstellen
        $date_filter = '';
        switch ($period) {
            case 'today':
                $date_filter = "AND DATE(t.created_at) = CURDATE()";
                break;
            case 'week':
                $date_filter = "AND t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $date_filter = "AND t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
        }
        
        $query = $wpdb->prepare(
            "SELECT p.*, 
                    COALESCE(SUM(t.quantity), 0) as total_sold,
                    COALESCE(SUM(t.amount), 0) as total_revenue
             FROM {$this->table_name} p
             LEFT JOIN {$transactions_table} t ON p.id = t.product_id 
                AND t.transaction_type = 'sale' {$date_filter}
             WHERE p.status = 'active'
             GROUP BY p.id
             ORDER BY total_sold DESC, p.name ASC
             LIMIT %d",
            $limit
        );
        
        return $wpdb->get_results($query);
    }
}