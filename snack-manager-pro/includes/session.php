<?php
/**
 * Session Management Class
 * 
 * @package SnackManagerPro
 */

// Direktzugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

class SMP_Session {
    
    private $table_name;
    private $wpdb;
    private static $instance = null;
    
    /**
     * Singleton-Pattern für Session-Management
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'smp_sessions';
    }
    
    /**
     * Session-System initialisieren
     */
    public static function init() {
        $instance = self::get_instance();
        // Automatisches Cleanup alter Sessions
        if (!wp_next_scheduled('smp_cleanup_sessions')) {
            wp_schedule_event(time(), 'hourly', 'smp_cleanup_sessions');
        }
        add_action('smp_cleanup_sessions', array($instance, 'cleanup_old_sessions'));
    }
    
    /**
     * Startet eine neue Session, wenn keine aktive vorhanden ist
     * 
     * @return int|false Session ID oder false bei Fehler
     */
    public function maybe_start_new_session() {
        // Prüfen ob bereits eine aktive Session existiert
        $active_session = $this->get_active_session();
        
        if ($active_session) {
            return $active_session->id;
        }
        
        // Neue Session starten
        return $this->start_session();
    }
    
    /**
     * Startet eine neue Session
     * 
     * @return int|false Session ID oder false bei Fehler
     */
    public function start_session() {
        $user_id = get_current_user_id();
        
        // Session Token generieren
        $session_token = wp_generate_password(32, false);
        
        $result = $this->wpdb->insert(
            $this->table_name,
            array(
                'session_token' => $session_token,
                'start_time' => current_time('mysql'),
                'status' => 'active',
                'user_id' => $user_id,
                'total_revenue' => 0,
                'transaction_count' => 0
            ),
            array('%s', '%s', '%s', '%d', '%f', '%d')
        );
        
        if ($result === false) {
            return false;
        }
        
        $session_id = $this->wpdb->insert_id;
        
        // Session-Start Event
        do_action('smp_session_started', $session_id, $user_id);
        
        return $session_id;
    }
    
    /**
     * Beendet die aktuelle Session
     * 
     * @param int $session_id Session ID (optional)
     * @return bool Erfolg
     */
    public function end_session($session_id = null) {
        if (!$session_id) {
            $active_session = $this->get_active_session();
            if (!$active_session) {
                return false;
            }
            $session_id = $active_session->id;
        }
        
        $result = $this->wpdb->update(
            $this->table_name,
            array(
                'end_time' => current_time('mysql'),
                'status' => 'closed'
            ),
            array('id' => $session_id),
            array('%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            // Session-Ende Event
            do_action('smp_session_ended', $session_id);
            
            // Statistiken aktualisieren
            $this->update_session_stats($session_id);
        }
        
        return $result !== false;
    }
    
    /**
     * Alias für end_session() für Kompatibilität
     */
    public function end_current_session() {
        return $this->end_session();
    }
    
    /**
     * Holt die aktuelle aktive Session
     * 
     * @return object|null Session-Objekt oder null
     */
    public function get_active_session() {
        return $this->wpdb->get_row(
            "SELECT * FROM {$this->table_name} 
             WHERE status = 'active' 
             ORDER BY id DESC 
             LIMIT 1"
        );
    }
    
    /**
     * Alias für get_active_session() für Kompatibilität
     */
    public function get_current_session() {
        return $this->get_active_session();
    }
    
    /**
     * Neue Session starten - Alias für Kompatibilität
     */
    public function start_new_session() {
        return $this->start_session();
    }
    
    /**
     * Holt eine Session nach ID
     * 
     * @param int $session_id Session ID
     * @return object|null Session-Objekt oder null
     */
    public function get_session($session_id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $session_id
            )
        );
    }
    
    /**
     * Holt alle Sessions mit optionalen Filtern
     * 
     * @param array $args Filter-Argumente
     * @return array Array von Session-Objekten
     */
    public function get_sessions($args = array()) {
        $defaults = array(
            'status' => '',
            'user_id' => 0,
            'date_from' => '',
            'date_to' => '',
            'orderby' => 'id',
            'order' => 'DESC',
            'limit' => 50,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_clauses = array('1=1');
        $where_values = array();
        
        if (!empty($args['status'])) {
            $where_clauses[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        if ($args['user_id'] > 0) {
            $where_clauses[] = 'user_id = %d';
            $where_values[] = $args['user_id'];
        }
        
        if (!empty($args['date_from'])) {
            $where_clauses[] = 'start_time >= %s';
            $where_values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where_clauses[] = 'start_time <= %s';
            $where_values[] = $args['date_to'];
        }
        
        $where = implode(' AND ', $where_clauses);
        
        $query = "SELECT * FROM {$this->table_name} WHERE {$where}";
        
        if (!empty($where_values)) {
            $query = $this->wpdb->prepare($query, $where_values);
        }
        
        $query .= " ORDER BY {$args['orderby']} {$args['order']}";
        $query .= " LIMIT {$args['limit']} OFFSET {$args['offset']}";
        
        return $this->wpdb->get_results($query);
    }
    
    /**
     * Aktualisiert die Statistiken einer Session
     * 
     * @param int $session_id Session ID
     * @return bool Erfolg
     */
    public function update_session_stats($session_id) {
        $transactions_table = $this->wpdb->prefix . 'smp_transactions';
        
        // Gesamtumsatz und Transaktionen berechnen
        $stats = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT 
                    SUM(amount) as total_revenue,
                    COUNT(*) as transaction_count
                 FROM {$transactions_table} 
                 WHERE session_id = %d 
                 AND transaction_type = 'sale'",
                $session_id
            )
        );
        
        if (!$stats) {
            return false;
        }
        
        // Session aktualisieren
        $result = $this->wpdb->update(
            $this->table_name,
            array(
                'total_revenue' => $stats->total_revenue ?: 0,
                'transaction_count' => $stats->transaction_count ?: 0
            ),
            array('id' => $session_id),
            array('%f', '%d'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Holt Session-Statistiken
     * 
     * @param int $session_id Session ID
     * @return array Statistiken
     */
    public function get_session_stats($session_id) {
        $transactions_table = $this->wpdb->prefix . 'smp_transactions';
        $products_table = $this->wpdb->prefix . 'smp_products';
        
        $stats = array(
            'total_sales' => 0,
            'total_transactions' => 0,
            'total_items_sold' => 0,
            'top_products' => array(),
            'sales_by_category' => array()
        );
        
        // Gesamtumsatz
        $stats['total_sales'] = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT SUM(amount) 
                 FROM {$transactions_table} 
                 WHERE session_id = %d 
                 AND transaction_type = 'sale'",
                $session_id
            )
        ) ?: 0;
        
        // Anzahl Transaktionen
        $stats['total_transactions'] = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) 
                 FROM {$transactions_table} 
                 WHERE session_id = %d 
                 AND transaction_type = 'sale'",
                $session_id
            )
        ) ?: 0;
        
        // Anzahl verkaufter Artikel
        $stats['total_items_sold'] = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT SUM(quantity) 
                 FROM {$transactions_table} 
                 WHERE session_id = %d 
                 AND transaction_type = 'sale'",
                $session_id
            )
        ) ?: 0;
        
        // Top-Produkte
        $stats['top_products'] = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT p.name, p.id, 
                        SUM(t.quantity) as total_sold, 
                        SUM(t.amount) as revenue
                 FROM {$transactions_table} t
                 JOIN {$products_table} p ON t.product_id = p.id
                 WHERE t.session_id = %d 
                 AND t.transaction_type = 'sale'
                 GROUP BY t.product_id
                 ORDER BY total_sold DESC
                 LIMIT 10",
                $session_id
            )
        );
        
        // Verkäufe nach Kategorie
        $stats['sales_by_category'] = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT p.category, 
                        COUNT(DISTINCT t.id) as transactions,
                        SUM(t.quantity) as items_sold,
                        SUM(t.amount) as revenue
                 FROM {$transactions_table} t
                 JOIN {$products_table} p ON t.product_id = p.id
                 WHERE t.session_id = %d 
                 AND t.transaction_type = 'sale'
                 GROUP BY p.category
                 ORDER BY revenue DESC",
                $session_id
            )
        );
        
        return $stats;
    }
    
    /**
     * Prüft ob eine Session aktiv ist
     * 
     * @param int $session_id Session ID
     * @return bool
     */
    public function is_session_active($session_id) {
        $status = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT status FROM {$this->table_name} WHERE id = %d",
                $session_id
            )
        );
        
        return $status === 'active';
    }
    
    /**
     * Automatisches Schließen alter Sessions
     * Sessions die länger als 24 Stunden aktiv sind werden geschlossen
     */
    public function cleanup_old_sessions() {
        $old_sessions = $this->wpdb->get_results(
            "SELECT id FROM {$this->table_name} 
             WHERE status = 'active' 
             AND start_time < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        
        foreach ($old_sessions as $session) {
            $this->end_session($session->id);
        }
    }
    
    /**
     * Transaktionszähler für Session erhöhen
     */
    public function increment_transaction_count($session_id) {
        global $wpdb;
        
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name} 
             SET transaction_count = transaction_count + 1 
             WHERE id = %d",
            $session_id
        ));
    }
    
    /**
     * NEUE METHODE: Holt den Umsatz einer Session
     * 
     * @param int $session_id Session ID
     * @return float Umsatz
     */
    public function get_session_revenue($session_id) {
        $transactions_table = $this->wpdb->prefix . 'smp_transactions';
        
        $revenue = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT SUM(amount) 
                 FROM {$transactions_table} 
                 WHERE session_id = %d 
                 AND transaction_type = 'sale'",
                $session_id
            )
        );
        
        return $revenue ? floatval($revenue) : 0;
    }
    
    /**
     * NEUE METHODE: Holt die Anzahl der Transaktionen einer Session
     * 
     * @param int $session_id Session ID
     * @return int Anzahl der Transaktionen
     */
    public function get_session_transaction_count($session_id) {
        $transactions_table = $this->wpdb->prefix . 'smp_transactions';
        
        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) 
                 FROM {$transactions_table} 
                 WHERE session_id = %d 
                 AND transaction_type = 'sale'",
                $session_id
            )
        );
        
        return $count ? intval($count) : 0;
    }
    
    /**
     * NEUE METHODE: Aktualisiert Session-Totale (für AJAX-Calls)
     * 
     * @param int $session_id Session ID
     * @return bool Erfolg
     */
    public function update_session_totals($session_id) {
        return $this->update_session_stats($session_id);
    }
}