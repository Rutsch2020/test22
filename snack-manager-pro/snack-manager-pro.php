<?php
/**
 * Plugin Name: Snack Manager Pro
 * Plugin URI: https://example.com/snack-manager-pro
 * Description: Ultra-modernes Snack-Management für Wohnmobilparks mit Session-Tracking, Barcode-Scanner, Voice-Control und Analytics
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: snack-manager-pro
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('SMP_VERSION', '1.0.0');
define('SMP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SMP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once SMP_PLUGIN_DIR . 'includes/database.php';
require_once SMP_PLUGIN_DIR . 'includes/session.php';
require_once SMP_PLUGIN_DIR . 'includes/product.php';
require_once SMP_PLUGIN_DIR . 'includes/transaction.php';
require_once SMP_PLUGIN_DIR . 'includes/scanner-controller.php';

// Main plugin class
class SnackManagerPro {
    
    private static $instance = null;
    private $scanner_controller;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Initialize controllers
        $this->scanner_controller = new SMP_Scanner_Controller();
        
        // Hook into WordPress
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Register AJAX handlers
        $this->register_ajax_handlers();
        
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Initialize session if needed
        if (is_admin()) {
            $session = new SMP_Session();
            if (!$session->get_active_session()) {
                $session->start_session();
            }
        }
    }
    
    public function activate() {
        // Create database tables
        $this->create_tables();
        
        // Set default options
        add_option('smp_settings', array(
            'currency' => 'EUR',
            'tax_rate' => 19,
            'low_stock_threshold' => 10,
            'enable_sound' => true,
            'enable_vibration' => true
        ));
        
        // Create demo data
        $this->create_demo_data();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Clean up scheduled tasks
        wp_clear_scheduled_hook('smp_cleanup_sessions');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Sessions table
        $table_sessions = $wpdb->prefix . 'smp_sessions';
        $sql_sessions = "CREATE TABLE IF NOT EXISTS $table_sessions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_token varchar(255) NOT NULL,
            user_id bigint(20) NOT NULL,
            start_time datetime NOT NULL,
            end_time datetime DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            total_revenue decimal(10,2) DEFAULT 0.00,
            transaction_count int DEFAULT 0,
            notes text,
            PRIMARY KEY (id),
            KEY session_token (session_token),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";
        
        // Products table
        $table_products = $wpdb->prefix . 'smp_products';
        $sql_products = "CREATE TABLE IF NOT EXISTS $table_products (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            barcode varchar(255) NOT NULL,
            category varchar(100) DEFAULT NULL,
            price decimal(10,2) NOT NULL,
            stock_quantity int DEFAULT 0,
            stock_min_quantity int DEFAULT 10,
            image_url varchar(500) DEFAULT NULL,
            description text,
            status enum('active','inactive') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY barcode (barcode),
            KEY category (category),
            KEY status (status)
        ) $charset_collate;";
        
        // Transactions table
        $table_transactions = $wpdb->prefix . 'smp_transactions';
        $sql_transactions = "CREATE TABLE IF NOT EXISTS $table_transactions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id bigint(20) NOT NULL,
            product_id bigint(20) NOT NULL,
            product_name varchar(255) NOT NULL,
            product_barcode varchar(255) NOT NULL,
            transaction_type enum('sale','refund','adjustment') DEFAULT 'sale',
            quantity int NOT NULL,
            unit_price decimal(10,2) NOT NULL,
            amount decimal(10,2) NOT NULL,
            payment_method varchar(50) DEFAULT 'cash',
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY product_id (product_id),
            KEY transaction_type (transaction_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Scanner logs table
        $table_scanner_logs = $wpdb->prefix . 'smp_scanner_logs';
        $sql_scanner_logs = "CREATE TABLE IF NOT EXISTS $table_scanner_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            action varchar(50) NOT NULL,
            data text,
            ip_address varchar(45),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_sessions);
        dbDelta($sql_products);
        dbDelta($sql_transactions);
        dbDelta($sql_scanner_logs);
    }
    
    private function create_demo_data() {
        $product = new SMP_Product();
        
        $demo_products = array(
            array(
                'name' => 'Coca Cola 0,33l',
                'barcode' => '5449000000996',
                'category' => 'Getränke',
                'price' => 1.50,
                'stock_quantity' => 50
            ),
            array(
                'name' => 'Snickers',
                'barcode' => '5000159461122',
                'category' => 'Süßwaren',
                'price' => 1.00,
                'stock_quantity' => 30
            ),
            array(
                'name' => 'Chips Paprika',
                'barcode' => '4001686216644',
                'category' => 'Snacks',
                'price' => 2.50,
                'stock_quantity' => 20
            ),
            array(
                'name' => 'Red Bull',
                'barcode' => '9002490100070',
                'category' => 'Getränke',
                'price' => 2.00,
                'stock_quantity' => 40
            ),
            array(
                'name' => 'Haribo Goldbären',
                'barcode' => '4001686390146',
                'category' => 'Süßwaren',
                'price' => 1.20,
                'stock_quantity' => 35
            )
        );
        
        foreach ($demo_products as $demo) {
            if (!$product->get_by_barcode($demo['barcode'])) {
                // Verwende save() statt create()
                $product->save($demo);
            }
        }
    }
    
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            'Snack Manager Pro',
            'Snack Manager',
            'manage_options',
            'snack-manager-pro',
            array($this, 'render_dashboard'),
            'dashicons-store',
            30
        );
        
        // Submenu items
        add_submenu_page(
            'snack-manager-pro',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'snack-manager-pro',
            array($this, 'render_dashboard')
        );
        
        add_submenu_page(
            'snack-manager-pro',
            'Scanner',
            'Scanner',
            'manage_options',
            'smp-scanner',
            array($this, 'render_scanner')
        );
        
        add_submenu_page(
            'snack-manager-pro',
            'Produkte',
            'Produkte',
            'manage_options',
            'smp-products',
            array($this, 'render_products')
        );
        
        add_submenu_page(
            'snack-manager-pro',
            'Analytics',
            'Analytics',
            'manage_options',
            'smp-analytics',
            array($this, 'render_analytics')
        );
        
        add_submenu_page(
            'snack-manager-pro',
            'Einstellungen',
            'Einstellungen',
            'manage_options',
            'smp-settings',
            array($this, 'render_settings')
        );
    }
    
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'smp-') === false && $hook !== 'toplevel_page_snack-manager-pro') {
            return;
        }
        
        // CSS
        wp_enqueue_style('smp-admin', SMP_PLUGIN_URL . 'assets/css/admin.css', array(), SMP_VERSION);
        wp_enqueue_style('smp-modern-base', SMP_PLUGIN_URL . 'assets/css/modern-base.css', array(), SMP_VERSION);
        
        // Google Fonts
        wp_enqueue_style('google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        // Font Awesome
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css');
        
        // Scanner specific assets
        if ($hook === 'snack-manager_page_smp-scanner') {
            // jQuery UI für bessere UI-Elemente
            wp_enqueue_script('jquery-ui-dialog');
            wp_enqueue_style('wp-jquery-ui-dialog');
            
            // Quagga für Desktop Scanner
            wp_enqueue_script('quagga', 'https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js', array(), '0.12.1', true);
            wp_enqueue_script('smp-scanner', SMP_PLUGIN_URL . 'assets/js/scanner.js', array('jquery', 'quagga'), SMP_VERSION, true);
            wp_enqueue_style('smp-scanner', SMP_PLUGIN_URL . 'assets/css/scanner.css', array(), SMP_VERSION);
            
            // Localize script - WICHTIG: Dies stellt smp_ajax zur Verfügung
            wp_localize_script('smp-scanner', 'smp_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('smp_ajax_nonce'),
                'strings' => array(
                    'error' => __('Fehler', 'snack-manager-pro'),
                    'success' => __('Erfolg', 'snack-manager-pro'),
                    'product_not_found' => __('Produkt nicht gefunden', 'snack-manager-pro'),
                    'no_stock' => __('Produkt nicht auf Lager', 'snack-manager-pro'),
                    'sale_confirmed' => __('Verkauf bestätigt', 'snack-manager-pro'),
                    'network_error' => __('Netzwerkfehler', 'snack-manager-pro')
                )
            ));
        }
        
        // Analytics specific assets
        if ($hook === 'snack-manager_page_smp-analytics') {
            wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.0', true);
            wp_enqueue_script('smp-analytics', SMP_PLUGIN_URL . 'assets/js/analytics.js', array('jquery', 'chart-js'), SMP_VERSION, true);
            wp_enqueue_style('smp-analytics', SMP_PLUGIN_URL . 'assets/css/analytics.css', array(), SMP_VERSION);
        }
        
        // General admin JS
        wp_enqueue_script('smp-admin', SMP_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), SMP_VERSION, true);
        
        // Localize script with AJAX data für admin.js
        wp_localize_script('smp-admin', 'smp_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('smp_ajax_nonce')
        ));
    }
    
    private function register_ajax_handlers() {
        // Scanner AJAX handlers
        add_action('wp_ajax_smp_handle_scan', array($this, 'ajax_handle_scan'));
        add_action('wp_ajax_smp_quick_sale', array($this, 'ajax_quick_sale'));
        add_action('wp_ajax_smp_get_quick_products', array($this, 'ajax_get_quick_products'));
        
        // Product AJAX handlers
        add_action('wp_ajax_smp_save_product', array($this, 'ajax_save_product'));
        add_action('wp_ajax_smp_delete_product', array($this, 'ajax_delete_product'));
        add_action('wp_ajax_smp_get_product', array($this, 'ajax_get_product'));
        add_action('wp_ajax_smp_search_products', array($this, 'ajax_search_products'));
        
        // Dashboard AJAX handlers
        add_action('wp_ajax_smp_get_dashboard_data', array($this, 'ajax_get_dashboard_data'));
        add_action('wp_ajax_smp_end_session', array($this, 'ajax_end_session'));
        add_action('wp_ajax_smp_start_session', array($this, 'ajax_start_session'));
        
        // Analytics AJAX handlers
        add_action('wp_ajax_smp_get_revenue_data', array($this, 'ajax_get_revenue_data'));
        add_action('wp_ajax_smp_get_product_stats', array($this, 'ajax_get_product_stats'));
        
        // Transaction AJAX handlers
        add_action('wp_ajax_smp_get_recent_transactions', array($this, 'ajax_get_recent_transactions'));
        add_action('wp_ajax_smp_refund_transaction', array($this, 'ajax_refund_transaction'));
    }
    
    // AJAX Handler: Handle barcode scan
    public function ajax_handle_scan() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'smp_ajax_nonce')) {
            wp_send_json_error('Ungültige Anfrage - Sicherheitsprüfung fehlgeschlagen');
            return;
        }
        
        $barcode = isset($_POST['barcode']) ? sanitize_text_field($_POST['barcode']) : '';
        
        if (empty($barcode)) {
            wp_send_json_error('Kein Barcode angegeben');
            return;
        }
        
        // Use scanner controller to handle the scan
        $result = $this->scanner_controller->handle_scan($barcode);
        
        if ($result['success']) {
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    // AJAX Handler: Quick sale
    public function ajax_quick_sale() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'smp_ajax_nonce')) {
            wp_send_json_error('Ungültige Anfrage');
            return;
        }
        
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        
        if (!$product_id) {
            wp_send_json_error('Keine Produkt-ID angegeben');
            return;
        }
        
        // Use scanner controller to process the sale
        $result = $this->scanner_controller->process_quick_sale($product_id);
        
        if ($result['success']) {
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    // AJAX Handler: Get quick products
    public function ajax_get_quick_products() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
            return;
        }
        
        // Verify nonce für POST requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'smp_ajax_nonce')) {
                wp_send_json_error('Ungültige Anfrage');
                return;
            }
        }
        
        $products = $this->scanner_controller->get_quick_products();
        wp_send_json_success($products);
    }
    
    // AJAX Handler: Save product
    public function ajax_save_product() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'smp_ajax_nonce')) {
            wp_send_json_error('Ungültige Anfrage');
            return;
        }
        
        $product_data = array(
            'id' => isset($_POST['id']) ? intval($_POST['id']) : 0,
            'name' => sanitize_text_field($_POST['name']),
            'barcode' => sanitize_text_field($_POST['barcode']),
            'category' => sanitize_text_field($_POST['category']),
            'price' => floatval($_POST['price']),
            'stock_quantity' => intval($_POST['stock_quantity']),
            'stock_min_quantity' => intval($_POST['stock_min_quantity']),
            'description' => sanitize_textarea_field($_POST['description']),
            'status' => sanitize_text_field($_POST['status'])
        );
        
        $product_model = new SMP_Product();
        $result = $product_model->save($product_data);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Produkt erfolgreich gespeichert',
                'product_id' => $result
            ));
        } else {
            wp_send_json_error('Fehler beim Speichern des Produkts');
        }
    }
    
    // AJAX Handler: Get dashboard data
    public function ajax_get_dashboard_data() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
            return;
        }
        
        $session_model = new SMP_Session();
        $product_model = new SMP_Product();
        $transaction_model = new SMP_Transaction();
        
        $active_session = $session_model->get_active_session();
        
        $data = array(
            'has_active_session' => !empty($active_session),
            'session' => null,
            'stats' => array(
                'total_products' => 0,
                'low_stock_products' => 0,
                'today_revenue' => 0,
                'today_transactions' => 0
            ),
            'recent_transactions' => array(),
            'low_stock_products' => array()
        );
        
        if ($active_session) {
            $data['session'] = array(
                'id' => $active_session->id,
                'start_time' => $active_session->start_time,
                'revenue' => $session_model->get_session_revenue($active_session->id),
                'transaction_count' => $session_model->get_session_transaction_count($active_session->id)
            );
            
            // Recent transactions
            $data['recent_transactions'] = $transaction_model->get_recent_by_session($active_session->id, 5);
        }
        
        // Product stats
        $product_stats = $product_model->get_stats();
        $data['stats']['total_products'] = $product_stats->active_products;
        $data['stats']['low_stock_products'] = $product_stats->low_stock;
        
        // Today's stats
        $today_stats = $transaction_model->get_sales_stats(array(
            'date_from' => date('Y-m-d'),
            'date_to' => date('Y-m-d')
        ));
        
        $data['stats']['today_revenue'] = $today_stats->total_revenue ?: 0;
        $data['stats']['today_transactions'] = $today_stats->total_transactions ?: 0;
        
        // Low stock products
        $data['low_stock_products'] = $product_model->get_low_stock_products(5);
        
        wp_send_json_success($data);
    }
    
    // AJAX Handler: Start new session
    public function ajax_start_session() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'smp_ajax_nonce')) {
            wp_send_json_error('Ungültige Anfrage');
            return;
        }
        
        $session_model = new SMP_Session();
        $session_id = $session_model->start_session();
        
        if ($session_id) {
            wp_send_json_success(array(
                'message' => 'Neue Session gestartet',
                'session_id' => $session_id
            ));
        } else {
            wp_send_json_error('Fehler beim Starten der Session');
        }
    }
    
    // AJAX Handler: End session
    public function ajax_end_session() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'smp_ajax_nonce')) {
            wp_send_json_error('Ungültige Anfrage');
            return;
        }
        
        $session_model = new SMP_Session();
        $result = $session_model->end_session();
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Session erfolgreich beendet'
            ));
        } else {
            wp_send_json_error('Fehler beim Beenden der Session');
        }
    }
    
    // AJAX Handler: Get revenue data for analytics
    public function ajax_get_revenue_data() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
            return;
        }
        
        $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : 'day';
        $days = isset($_POST['days']) ? intval($_POST['days']) : 30;
        
        $transaction_model = new SMP_Transaction();
        
        $revenue_data = $transaction_model->get_revenue_by_period($period, array(
            'date_from' => date('Y-m-d', strtotime("-{$days} days")),
            'date_to' => date('Y-m-d')
        ));
        
        // Format data for Chart.js
        $labels = array();
        $data = array();
        
        foreach ($revenue_data as $row) {
            $labels[] = $row->period_label;
            $data[] = floatval($row->revenue);
        }
        
        wp_send_json_success(array(
            'labels' => $labels,
            'datasets' => array(
                array(
                    'label' => 'Umsatz (€)',
                    'data' => $data,
                    'borderColor' => 'rgb(75, 192, 192)',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                    'tension' => 0.1
                )
            )
        ));
    }
    
    // AJAX Handler: Get product statistics
    public function ajax_get_product_stats() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
            return;
        }
        
        $product_model = new SMP_Product();
        $transaction_model = new SMP_Transaction();
        
        // Get top selling products
        $top_products = $product_model->get_top_selling(10, 'month');
        
        // Format data for Chart.js
        $labels = array();
        $data = array();
        $colors = array();
        
        $color_palette = array(
            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
            '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0', '#36A2EB'
        );
        
        foreach ($top_products as $index => $product) {
            $labels[] = $product->name;
            $data[] = intval($product->total_sold);
            $colors[] = $color_palette[$index % count($color_palette)];
        }
        
        wp_send_json_success(array(
            'labels' => $labels,
            'datasets' => array(
                array(
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderColor' => '#fff',
                    'borderWidth' => 2
                )
            )
        ));
    }
    
    // AJAX Handler: Search products
    public function ajax_search_products() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
            return;
        }
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        if (strlen($search) < 2) {
            wp_send_json_success(array());
            return;
        }
        
        $product_model = new SMP_Product();
        $products = $product_model->search_autocomplete($search, 10);
        
        $results = array();
        foreach ($products as $product) {
            $results[] = array(
                'id' => $product->id,
                'label' => $product->name . ' (' . $product->barcode . ')',
                'value' => $product->name,
                'barcode' => $product->barcode,
                'price' => number_format($product->price, 2, ',', '.'),
                'stock' => $product->stock_quantity
            );
        }
        
        wp_send_json_success($results);
    }
    
    // AJAX Handler: Get recent transactions
    public function ajax_get_recent_transactions() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
            return;
        }
        
        $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
        
        $transaction_model = new SMP_Transaction();
        
        if ($session_id) {
            $transactions = $transaction_model->get_recent_by_session($session_id, $limit);
        } else {
            // Get from all sessions
            $transactions = $transaction_model->get_by_session(0, array('limit' => $limit));
        }
        
        wp_send_json_success($transactions);
    }
    
    // AJAX Handler: Refund transaction
    public function ajax_refund_transaction() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'smp_ajax_nonce')) {
            wp_send_json_error('Ungültige Anfrage');
            return;
        }
        
        $transaction_id = isset($_POST['transaction_id']) ? intval($_POST['transaction_id']) : 0;
        $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';
        
        if (!$transaction_id) {
            wp_send_json_error('Keine Transaktions-ID angegeben');
            return;
        }
        
        $transaction_model = new SMP_Transaction();
        $refund_id = $transaction_model->refund($transaction_id, $reason);
        
        if ($refund_id) {
            wp_send_json_success(array(
                'message' => 'Stornierung erfolgreich',
                'refund_id' => $refund_id
            ));
        } else {
            wp_send_json_error('Fehler bei der Stornierung');
        }
    }
    
    // AJAX Handler: Delete product
    public function ajax_delete_product() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'smp_ajax_nonce')) {
            wp_send_json_error('Ungültige Anfrage');
            return;
        }
        
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        
        if (!$product_id) {
            wp_send_json_error('Keine Produkt-ID angegeben');
            return;
        }
        
        $product_model = new SMP_Product();
        $result = $product_model->delete($product_id);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Produkt erfolgreich gelöscht'
            ));
        } else {
            wp_send_json_error('Fehler beim Löschen des Produkts');
        }
    }
    
    // AJAX Handler: Get single product
    public function ajax_get_product() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
            return;
        }
        
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        
        if (!$product_id) {
            wp_send_json_error('Keine Produkt-ID angegeben');
            return;
        }
        
        $product_model = new SMP_Product();
        $product = $product_model->get($product_id);
        
        if ($product) {
            wp_send_json_success($product);
        } else {
            wp_send_json_error('Produkt nicht gefunden');
        }
    }
    
    // Render methods
    public function render_dashboard() {
        include SMP_PLUGIN_DIR . 'views/dashboard.php';
    }
    
    public function render_scanner() {
        include SMP_PLUGIN_DIR . 'views/scanner.php';
    }
    
    public function render_products() {
        include SMP_PLUGIN_DIR . 'views/products.php';
    }
    
    public function render_analytics() {
        include SMP_PLUGIN_DIR . 'views/analytics.php';
    }
    
    public function render_settings() {
        include SMP_PLUGIN_DIR . 'views/settings.php';
    }
}

// Initialize plugin
SnackManagerPro::get_instance();

// Initialize session system
add_action('init', array('SMP_Session', 'init'));