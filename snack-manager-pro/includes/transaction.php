<?php
/**
 * Transaction Model für Snack Manager Pro
 * 
 * @package SnackManagerPro
 * @subpackage Models
 * @since 1.0.0
 */

// Direktzugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

class SMP_Transaction {
    
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
        $this->table_name = $wpdb->prefix . 'smp_transactions';
    }
    
    /**
     * Transaktion erstellen
     *
     * @param array $data Transaktionsdaten
     * @return int|false Transaction ID bei Erfolg, false bei Fehler
     */
    public function create($data) {
        global $wpdb;
        
        // Daten vorbereiten
        $transaction_data = array(
            'session_id' => intval($data['session_id']),
            'product_id' => intval($data['product_id']),
            'product_name' => sanitize_text_field($data['product_name']),
            'product_barcode' => sanitize_text_field($data['product_barcode']),
            'transaction_type' => in_array($data['transaction_type'], ['sale', 'refund', 'adjustment']) ? $data['transaction_type'] : 'sale',
            'quantity' => intval($data['quantity']),
            'unit_price' => floatval($data['unit_price']),
            'amount' => floatval($data['amount']),
            'payment_method' => sanitize_text_field($data['payment_method'] ?? 'cash'),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'created_at' => current_time('mysql')
        );
        
        // Format-Spezifikationen für wpdb
        $formats = array(
            '%d', // session_id
            '%d', // product_id
            '%s', // product_name
            '%s', // product_barcode
            '%s', // transaction_type
            '%d', // quantity
            '%f', // unit_price
            '%f', // amount
            '%s', // payment_method
            '%s', // notes
            '%s'  // created_at
        );
        
        // Insert
        $result = $wpdb->insert(
            $this->table_name,
            $transaction_data,
            $formats
        );
        
        if ($result === false) {
            return false;
        }
        
        $transaction_id = $wpdb->insert_id;
        
        // Event auslösen
        do_action('smp_transaction_created', $transaction_id, $transaction_data);
        
        return $transaction_id;
    }
    
    /**
     * Transaktion abrufen
     *
     * @param int $id Transaction ID
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
     * Transaktionen nach Session abrufen
     *
     * @param int $session_id Session ID
     * @param array $args Optionale Filter
     * @return array
     */
    public function get_by_session($session_id, $args = array()) {
        global $wpdb;
        
        $defaults = array(
            'transaction_type' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => -1
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $query = "SELECT * FROM {$this->table_name} WHERE session_id = %d";
        $query_args = array($session_id);
        
        // Transaction Type Filter
        if (!empty($args['transaction_type'])) {
            $query .= " AND transaction_type = %s";
            $query_args[] = $args['transaction_type'];
        }
        
        // Sortierung
        $allowed_orderby = array('created_at', 'amount', 'product_name', 'quantity');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        $query .= " ORDER BY {$orderby} {$order}";
        
        // Limit
        if ($args['limit'] > 0) {
            $query .= " LIMIT %d";
            $query_args[] = $args['limit'];
        }
        
        $prepared_query = $wpdb->prepare($query, $query_args);
        
        return $wpdb->get_results($prepared_query);
    }
    
    /**
     * Letzte Transaktionen einer Session abrufen
     *
     * @param int $session_id Session ID
     * @param int $limit Anzahl
     * @return array
     */
    public function get_recent_by_session($session_id, $limit = 10) {
        return $this->get_by_session($session_id, array(
            'limit' => $limit,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ));
    }
    
    /**
     * Transaktionen nach Produkt abrufen
     *
     * @param int $product_id Product ID
     * @param array $args Optionale Filter
     * @return array
     */
    public function get_by_product($product_id, $args = array()) {
        global $wpdb;
        
        $defaults = array(
            'transaction_type' => 'sale',
            'date_from' => '',
            'date_to' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => -1
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $query = "SELECT * FROM {$this->table_name} WHERE product_id = %d";
        $query_args = array($product_id);
        
        // Transaction Type Filter
        if (!empty($args['transaction_type'])) {
            $query .= " AND transaction_type = %s";
            $query_args[] = $args['transaction_type'];
        }
        
        // Datum Filter
        if (!empty($args['date_from'])) {
            $query .= " AND created_at >= %s";
            $query_args[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $query .= " AND created_at <= %s";
            $query_args[] = $args['date_to'];
        }
        
        // Sortierung
        $query .= " ORDER BY {$args['orderby']} {$args['order']}";
        
        // Limit
        if ($args['limit'] > 0) {
            $query .= " LIMIT %d";
            $query_args[] = $args['limit'];
        }
        
        $prepared_query = $wpdb->prepare($query, $query_args);
        
        return $wpdb->get_results($prepared_query);
    }
    
    /**
     * Verkaufsstatistiken abrufen
     *
     * @param array $args Filter
     * @return object
     */
    public function get_sales_stats($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'date_from' => date('Y-m-d', strtotime('-30 days')),
            'date_to' => date('Y-m-d'),
            'session_id' => 0,
            'product_id' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = "WHERE transaction_type = 'sale'";
        $where_args = array();
        
        if ($args['session_id'] > 0) {
            $where .= " AND session_id = %d";
            $where_args[] = $args['session_id'];
        }
        
        if ($args['product_id'] > 0) {
            $where .= " AND product_id = %d";
            $where_args[] = $args['product_id'];
        }
        
        if (!empty($args['date_from'])) {
            $where .= " AND created_at >= %s";
            $where_args[] = $args['date_from'] . ' 00:00:00';
        }
        
        if (!empty($args['date_to'])) {
            $where .= " AND created_at <= %s";
            $where_args[] = $args['date_to'] . ' 23:59:59';
        }
        
        $query = "SELECT 
                    COUNT(*) as total_transactions,
                    SUM(quantity) as total_items,
                    SUM(amount) as total_revenue,
                    AVG(amount) as average_sale,
                    MAX(amount) as highest_sale,
                    MIN(amount) as lowest_sale
                  FROM {$this->table_name} 
                  {$where}";
        
        if (!empty($where_args)) {
            $query = $wpdb->prepare($query, $where_args);
        }
        
        return $wpdb->get_row($query);
    }
    
    /**
     * Umsatz nach Zeitraum gruppiert
     *
     * @param string $period hour, day, week, month
     * @param array $args Filter
     * @return array
     */
    public function get_revenue_by_period($period = 'day', $args = array()) {
        global $wpdb;
        
        $defaults = array(
            'date_from' => date('Y-m-d', strtotime('-30 days')),
            'date_to' => date('Y-m-d'),
            'session_id' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Gruppierung festlegen
        switch ($period) {
            case 'hour':
                $group_by = "DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')";
                $date_format = '%d.%m %H:00';
                break;
            case 'week':
                $group_by = "DATE_FORMAT(created_at, '%Y-%u')";
                $date_format = 'KW %u';
                break;
            case 'month':
                $group_by = "DATE_FORMAT(created_at, '%Y-%m')";
                $date_format = '%M %Y';
                break;
            case 'day':
            default:
                $group_by = "DATE(created_at)";
                $date_format = '%d.%m.%Y';
                break;
        }
        
        $where = "WHERE transaction_type = 'sale'";
        $where_args = array();
        
        if ($args['session_id'] > 0) {
            $where .= " AND session_id = %d";
            $where_args[] = $args['session_id'];
        }
        
        if (!empty($args['date_from'])) {
            $where .= " AND created_at >= %s";
            $where_args[] = $args['date_from'] . ' 00:00:00';
        }
        
        if (!empty($args['date_to'])) {
            $where .= " AND created_at <= %s";
            $where_args[] = $args['date_to'] . ' 23:59:59';
        }
        
        $query = "SELECT 
                    {$group_by} as period,
                    DATE_FORMAT(MIN(created_at), '{$date_format}') as period_label,
                    COUNT(*) as transactions,
                    SUM(quantity) as items_sold,
                    SUM(amount) as revenue
                  FROM {$this->table_name} 
                  {$where}
                  GROUP BY {$group_by}
                  ORDER BY period ASC";
        
        if (!empty($where_args)) {
            $query = $wpdb->prepare($query, $where_args);
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Stornierung durchführen
     *
     * @param int $transaction_id Original Transaction ID
     * @param string $reason Stornierungsgrund
     * @return int|false Neue Transaction ID oder false
     */
    public function refund($transaction_id, $reason = '') {
        global $wpdb;
        
        // Original-Transaktion holen
        $original = $this->get($transaction_id);
        if (!$original || $original->transaction_type !== 'sale') {
            return false;
        }
        
        // Stornierung erstellen
        $refund_data = array(
            'session_id' => $original->session_id,
            'product_id' => $original->product_id,
            'product_name' => $original->product_name,
            'product_barcode' => $original->product_barcode,
            'transaction_type' => 'refund',
            'quantity' => $original->quantity,
            'unit_price' => $original->unit_price,
            'amount' => -$original->amount, // Negativer Betrag
            'payment_method' => $original->payment_method,
            'notes' => 'Stornierung von #' . $transaction_id . ($reason ? ': ' . $reason : '')
        );
        
        $refund_id = $this->create($refund_data);
        
        if ($refund_id) {
            // Lagerbestand wieder erhöhen
            $product_model = new SMP_Product();
            $product_model->update_stock($original->product_id, $original->quantity, 'add');
            
            // Session-Totale aktualisieren
            $session_model = new SMP_Session();
            $session_model->update_session_totals($original->session_id);
            
            // Event auslösen
            do_action('smp_transaction_refunded', $refund_id, $transaction_id);
        }
        
        return $refund_id;
    }
    
    /**
     * Transaktion löschen (nur für Admin)
     *
     * @param int $id Transaction ID
     * @return bool
     */
    public function delete($id) {
        global $wpdb;
        
        // Transaktion holen für Cleanup
        $transaction = $this->get($id);
        if (!$transaction) {
            return false;
        }
        
        $result = $wpdb->delete(
            $this->table_name,
            array('id' => $id),
            array('%d')
        );
        
        if ($result !== false) {
            // Session-Totale aktualisieren
            $session_model = new SMP_Session();
            $session_model->update_session_totals($transaction->session_id);
            
            // Event auslösen
            do_action('smp_transaction_deleted', $id, $transaction);
        }
        
        return $result !== false;
    }
    
    /**
     * Zahlungsmethoden-Statistik
     *
     * @param array $args Filter
     * @return array
     */
    public function get_payment_method_stats($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'date_from' => date('Y-m-d', strtotime('-30 days')),
            'date_to' => date('Y-m-d'),
            'session_id' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = "WHERE transaction_type = 'sale'";
        $where_args = array();
        
        if ($args['session_id'] > 0) {
            $where .= " AND session_id = %d";
            $where_args[] = $args['session_id'];
        }
        
        if (!empty($args['date_from'])) {
            $where .= " AND created_at >= %s";
            $where_args[] = $args['date_from'] . ' 00:00:00';
        }
        
        if (!empty($args['date_to'])) {
            $where .= " AND created_at <= %s";
            $where_args[] = $args['date_to'] . ' 23:59:59';
        }
        
        $query = "SELECT 
                    payment_method,
                    COUNT(*) as transaction_count,
                    SUM(amount) as total_amount
                  FROM {$this->table_name} 
                  {$where}
                  GROUP BY payment_method
                  ORDER BY total_amount DESC";
        
        if (!empty($where_args)) {
            $query = $wpdb->prepare($query, $where_args);
        }
        
        return $wpdb->get_results($query);
    }
}