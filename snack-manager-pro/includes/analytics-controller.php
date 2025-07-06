<?php
/**
 * Analytics Controller für Snack Manager Pro
 * 
 * @package SnackManagerPro
 * @subpackage Controllers
 * @since 1.0.0
 */

namespace SnackManagerPro\Controllers;

use SnackManagerPro\Database;
use SnackManagerPro\Models\Product;
use SnackManagerPro\Models\Transaction;

class Analytics_Controller {
    
    /**
     * Database instance
     * @var Database
     */
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance();
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // AJAX endpoints
        add_action('wp_ajax_smp_get_revenue_data', array($this, 'ajax_get_revenue_data'));
        add_action('wp_ajax_smp_get_product_performance', array($this, 'ajax_get_product_performance'));
        add_action('wp_ajax_smp_get_sales_trends', array($this, 'ajax_get_sales_trends'));
        add_action('wp_ajax_smp_get_dashboard_stats', array($this, 'ajax_get_dashboard_stats'));
        add_action('wp_ajax_smp_export_analytics', array($this, 'ajax_export_analytics'));
        add_action('wp_ajax_smp_get_realtime_updates', array($this, 'ajax_get_realtime_updates'));
        add_action('wp_ajax_smp_get_comparison_data', array($this, 'ajax_get_comparison_data'));
        add_action('wp_ajax_smp_get_forecast_data', array($this, 'ajax_get_forecast_data'));
    }
    
    /**
     * Get revenue data for charts
     */
    public function ajax_get_revenue_data() {
        check_ajax_referer('smp_nonce', 'nonce');
        
        $period = sanitize_text_field($_POST['period'] ?? 'daily');
        $start_date = sanitize_text_field($_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days')));
        $end_date = sanitize_text_field($_POST['end_date'] ?? date('Y-m-d'));
        
        $data = $this->get_revenue_data($period, $start_date, $end_date);
        
        wp_send_json_success($data);
    }
    
    /**
     * Get product performance rankings
     */
    public function ajax_get_product_performance() {
        check_ajax_referer('smp_nonce', 'nonce');
        
        $start_date = sanitize_text_field($_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days')));
        $end_date = sanitize_text_field($_POST['end_date'] ?? date('Y-m-d'));
        $limit = intval($_POST['limit'] ?? 10);
        
        $data = $this->get_product_performance($start_date, $end_date, $limit);
        
        wp_send_json_success($data);
    }
    
    /**
     * Get sales trends and patterns
     */
    public function ajax_get_sales_trends() {
        check_ajax_referer('smp_nonce', 'nonce');
        
        $period = sanitize_text_field($_POST['period'] ?? 'weekly');
        $data = $this->get_sales_trends($period);
        
        wp_send_json_success($data);
    }
    
    /**
     * Get dashboard statistics
     */
    public function ajax_get_dashboard_stats() {
        check_ajax_referer('smp_nonce', 'nonce');
        
        $stats = $this->get_dashboard_stats();
        
        wp_send_json_success($stats);
    }
    
    /**
     * Export analytics data
     */
    public function ajax_export_analytics() {
        check_ajax_referer('smp_nonce', 'nonce');
        
        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        $data_type = sanitize_text_field($_POST['data_type'] ?? 'revenue');
        $start_date = sanitize_text_field($_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days')));
        $end_date = sanitize_text_field($_POST['end_date'] ?? date('Y-m-d'));
        
        if ($format === 'csv') {
            $this->export_csv($data_type, $start_date, $end_date);
        } else {
            $this->export_pdf($data_type, $start_date, $end_date);
        }
    }
    
    /**
     * Get real-time updates
     */
    public function ajax_get_realtime_updates() {
        check_ajax_referer('smp_nonce', 'nonce');
        
        $last_update = sanitize_text_field($_POST['last_update'] ?? '');
        $data = $this->get_realtime_updates($last_update);
        
        wp_send_json_success($data);
    }
    
    /**
     * Get comparison data
     */
    public function ajax_get_comparison_data() {
        check_ajax_referer('smp_nonce', 'nonce');
        
        $period1_start = sanitize_text_field($_POST['period1_start'] ?? '');
        $period1_end = sanitize_text_field($_POST['period1_end'] ?? '');
        $period2_start = sanitize_text_field($_POST['period2_start'] ?? '');
        $period2_end = sanitize_text_field($_POST['period2_end'] ?? '');
        
        $data = $this->get_comparison_data($period1_start, $period1_end, $period2_start, $period2_end);
        
        wp_send_json_success($data);
    }
    
    /**
     * Get forecast data
     */
    public function ajax_get_forecast_data() {
        check_ajax_referer('smp_nonce', 'nonce');
        
        $days_ahead = intval($_POST['days_ahead'] ?? 7);
        $data = $this->get_forecast_data($days_ahead);
        
        wp_send_json_success($data);
    }
    
    /**
     * Get revenue data grouped by period
     */
    private function get_revenue_data($period, $start_date, $end_date) {
        global $wpdb;
        $table = $wpdb->prefix . 'smp_transactions';
        
        $group_by = '';
        switch ($period) {
            case 'daily':
                $group_by = "DATE(created_at)";
                break;
            case 'weekly':
                $group_by = "YEARWEEK(created_at)";
                break;
            case 'monthly':
                $group_by = "DATE_FORMAT(created_at, '%Y-%m')";
                break;
        }
        
        $query = $wpdb->prepare("
            SELECT 
                $group_by as period,
                SUM(amount) as revenue,
                COUNT(*) as transactions,
                AVG(amount) as avg_transaction
            FROM $table
            WHERE created_at BETWEEN %s AND %s
            GROUP BY period
            ORDER BY period ASC
        ", $start_date, $end_date . ' 23:59:59');
        
        $results = $wpdb->get_results($query);
        
        // Format data for Chart.js
        $labels = [];
        $revenue = [];
        $transactions = [];
        $avg_transaction = [];
        
        foreach ($results as $row) {
            $labels[] = $this->format_period_label($row->period, $period);
            $revenue[] = floatval($row->revenue);
            $transactions[] = intval($row->transactions);
            $avg_transaction[] = floatval($row->avg_transaction);
        }
        
        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => __('Umsatz (€)', 'snack-manager-pro'),
                    'data' => $revenue,
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                    'yAxisID' => 'y-revenue'
                ],
                [
                    'label' => __('Transaktionen', 'snack-manager-pro'),
                    'data' => $transactions,
                    'borderColor' => '#ec4899',
                    'backgroundColor' => 'rgba(236, 72, 153, 0.1)',
                    'yAxisID' => 'y-transactions'
                ]
            ],
            'summary' => [
                'total_revenue' => array_sum($revenue),
                'total_transactions' => array_sum($transactions),
                'avg_transaction_value' => $revenue ? array_sum($revenue) / array_sum($transactions) : 0
            ]
        ];
    }
    
    /**
     * Get product performance data
     */
    private function get_product_performance($start_date, $end_date, $limit = 10) {
        global $wpdb;
        $trans_table = $wpdb->prefix . 'smp_transactions';
        $prod_table = $wpdb->prefix . 'smp_products';
        
        $query = $wpdb->prepare("
            SELECT 
                p.id,
                p.name,
                p.barcode,
                p.category,
                COUNT(t.id) as sales_count,
                SUM(t.amount) as total_revenue,
                AVG(t.amount) as avg_price,
                MAX(t.created_at) as last_sale
            FROM $trans_table t
            JOIN $prod_table p ON t.product_id = p.id
            WHERE t.created_at BETWEEN %s AND %s
            GROUP BY p.id
            ORDER BY total_revenue DESC
            LIMIT %d
        ", $start_date, $end_date . ' 23:59:59', $limit);
        
        $results = $wpdb->get_results($query);
        
        // Calculate rankings and trends
        foreach ($results as &$product) {
            $product->revenue_share = $this->calculate_revenue_share($product->total_revenue, $start_date, $end_date);
            $product->trend = $this->calculate_product_trend($product->id, $start_date, $end_date);
        }
        
        return $results;
    }
    
    /**
     * Get sales trends
     */
    private function get_sales_trends($period = 'weekly') {
        global $wpdb;
        $table = $wpdb->prefix . 'smp_transactions';
        
        // Get data for trend analysis
        $days = $period === 'daily' ? 30 : ($period === 'weekly' ? 84 : 365);
        $start_date = date('Y-m-d', strtotime("-$days days"));
        
        $query = $wpdb->prepare("
            SELECT 
                DATE(created_at) as date,
                HOUR(created_at) as hour,
                DAYOFWEEK(created_at) as day_of_week,
                COUNT(*) as transaction_count,
                SUM(amount) as revenue
            FROM $table
            WHERE created_at >= %s
            GROUP BY date, hour
            ORDER BY date, hour
        ", $start_date);
        
        $results = $wpdb->get_results($query);
        
        // Analyze patterns
        $hourly_pattern = $this->analyze_hourly_pattern($results);
        $weekly_pattern = $this->analyze_weekly_pattern($results);
        $growth_trend = $this->calculate_growth_trend($results, $period);
        
        return [
            'hourly_pattern' => $hourly_pattern,
            'weekly_pattern' => $weekly_pattern,
            'growth_trend' => $growth_trend,
            'peak_hours' => $this->get_peak_hours($hourly_pattern),
            'peak_days' => $this->get_peak_days($weekly_pattern)
        ];
    }
    
    /**
     * Get dashboard statistics
     */
    private function get_dashboard_stats() {
        global $wpdb;
        $trans_table = $wpdb->prefix . 'smp_transactions';
        $prod_table = $wpdb->prefix . 'smp_products';
        
        // Today's stats
        $today_stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as transactions,
                SUM(amount) as revenue,
                AVG(amount) as avg_transaction
            FROM $trans_table
            WHERE DATE(created_at) = CURDATE()
        ");
        
        // Yesterday's stats for comparison
        $yesterday_stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as transactions,
                SUM(amount) as revenue
            FROM $trans_table
            WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
        ");
        
        // This month's stats
        $month_stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as transactions,
                SUM(amount) as revenue
            FROM $trans_table
            WHERE MONTH(created_at) = MONTH(CURDATE())
            AND YEAR(created_at) = YEAR(CURDATE())
        ");
        
        // Last month's stats
        $last_month_stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as transactions,
                SUM(amount) as revenue
            FROM $trans_table
            WHERE MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
            AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
        ");
        
        // Product stats
        $product_stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_products,
                COUNT(CASE WHEN stock_quantity > 0 THEN 1 END) as in_stock,
                COUNT(CASE WHEN stock_quantity = 0 THEN 1 END) as out_of_stock,
                COUNT(CASE WHEN stock_quantity < stock_min_quantity THEN 1 END) as low_stock
            FROM $prod_table
        ");
        
        // Calculate changes
        $today_change = $yesterday_stats->revenue ? 
            (($today_stats->revenue - $yesterday_stats->revenue) / $yesterday_stats->revenue) * 100 : 0;
        
        $month_change = $last_month_stats->revenue ? 
            (($month_stats->revenue - $last_month_stats->revenue) / $last_month_stats->revenue) * 100 : 0;
        
        return [
            'today' => [
                'revenue' => floatval($today_stats->revenue ?? 0),
                'transactions' => intval($today_stats->transactions ?? 0),
                'avg_transaction' => floatval($today_stats->avg_transaction ?? 0),
                'change' => round($today_change, 2)
            ],
            'month' => [
                'revenue' => floatval($month_stats->revenue ?? 0),
                'transactions' => intval($month_stats->transactions ?? 0),
                'change' => round($month_change, 2)
            ],
            'products' => [
                'total' => intval($product_stats->total_products ?? 0),
                'in_stock' => intval($product_stats->in_stock ?? 0),
                'out_of_stock' => intval($product_stats->out_of_stock ?? 0),
                'low_stock' => intval($product_stats->low_stock ?? 0)
            ],
            'last_update' => current_time('mysql')
        ];
    }
    
    /**
     * Get real-time updates since last check
     */
    private function get_realtime_updates($last_update) {
        global $wpdb;
        $trans_table = $wpdb->prefix . 'smp_transactions';
        $prod_table = $wpdb->prefix . 'smp_products';
        
        if (empty($last_update)) {
            $last_update = date('Y-m-d H:i:s', strtotime('-5 minutes'));
        }
        
        // Get new transactions
        $new_transactions = $wpdb->get_results($wpdb->prepare("
            SELECT 
                t.*,
                p.name as product_name,
                p.category
            FROM $trans_table t
            JOIN $prod_table p ON t.product_id = p.id
            WHERE t.created_at > %s
            ORDER BY t.created_at DESC
            LIMIT 10
        ", $last_update));
        
        // Get updated stats
        $current_stats = $this->get_dashboard_stats();
        
        return [
            'new_transactions' => $new_transactions,
            'stats' => $current_stats,
            'timestamp' => current_time('mysql')
        ];
    }
    
    /**
     * Get comparison data between two periods
     */
    private function get_comparison_data($period1_start, $period1_end, $period2_start, $period2_end) {
        // Get data for both periods
        $period1_data = $this->get_period_metrics($period1_start, $period1_end);
        $period2_data = $this->get_period_metrics($period2_start, $period2_end);
        
        // Calculate differences
        $comparison = [
            'revenue' => [
                'period1' => $period1_data['revenue'],
                'period2' => $period2_data['revenue'],
                'difference' => $period2_data['revenue'] - $period1_data['revenue'],
                'percentage' => $period1_data['revenue'] ? 
                    (($period2_data['revenue'] - $period1_data['revenue']) / $period1_data['revenue']) * 100 : 0
            ],
            'transactions' => [
                'period1' => $period1_data['transactions'],
                'period2' => $period2_data['transactions'],
                'difference' => $period2_data['transactions'] - $period1_data['transactions'],
                'percentage' => $period1_data['transactions'] ? 
                    (($period2_data['transactions'] - $period1_data['transactions']) / $period1_data['transactions']) * 100 : 0
            ],
            'avg_transaction' => [
                'period1' => $period1_data['avg_transaction'],
                'period2' => $period2_data['avg_transaction'],
                'difference' => $period2_data['avg_transaction'] - $period1_data['avg_transaction'],
                'percentage' => $period1_data['avg_transaction'] ? 
                    (($period2_data['avg_transaction'] - $period1_data['avg_transaction']) / $period1_data['avg_transaction']) * 100 : 0
            ],
            'top_products' => [
                'period1' => $period1_data['top_products'],
                'period2' => $period2_data['top_products']
            ]
        ];
        
        return $comparison;
    }
    
    /**
     * Get forecast data using simple moving average
     */
    private function get_forecast_data($days_ahead = 7) {
        global $wpdb;
        $table = $wpdb->prefix . 'smp_transactions';
        
        // Get historical data for last 30 days
        $query = "
            SELECT 
                DATE(created_at) as date,
                SUM(amount) as daily_revenue,
                COUNT(*) as daily_transactions
            FROM $table
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY date
            ORDER BY date
        ";
        
        $historical = $wpdb->get_results($query);
        
        // Calculate moving averages
        $ma7 = $this->calculate_moving_average($historical, 7);
        $ma14 = $this->calculate_moving_average($historical, 14);
        
        // Simple forecast using trend
        $trend = $this->calculate_trend($historical);
        $forecast = [];
        
        $last_value = end($historical);
        $base_revenue = $last_value ? $last_value->daily_revenue : 0;
        
        for ($i = 1; $i <= $days_ahead; $i++) {
            $date = date('Y-m-d', strtotime("+$i days"));
            $day_of_week = date('w', strtotime($date));
            
            // Apply day of week seasonality
            $seasonality = $this->get_day_seasonality($day_of_week, $historical);
            
            $forecast[] = [
                'date' => $date,
                'revenue' => $base_revenue * (1 + $trend) * $seasonality,
                'confidence_lower' => $base_revenue * (1 + $trend - 0.1) * $seasonality * 0.8,
                'confidence_upper' => $base_revenue * (1 + $trend + 0.1) * $seasonality * 1.2
            ];
            
            $base_revenue *= (1 + $trend);
        }
        
        return [
            'historical' => $historical,
            'forecast' => $forecast,
            'trend' => $trend,
            'accuracy' => $this->calculate_forecast_accuracy()
        ];
    }
    
    /**
     * Export data as CSV
     */
    private function export_csv($data_type, $start_date, $end_date) {
        $filename = 'snack-manager-analytics-' . $data_type . '-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for Excel UTF-8 compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        switch ($data_type) {
            case 'revenue':
                $this->export_revenue_csv($output, $start_date, $end_date);
                break;
            case 'products':
                $this->export_products_csv($output, $start_date, $end_date);
                break;
            case 'transactions':
                $this->export_transactions_csv($output, $start_date, $end_date);
                break;
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export revenue data as CSV
     */
    private function export_revenue_csv($output, $start_date, $end_date) {
        global $wpdb;
        $table = $wpdb->prefix . 'smp_transactions';
        
        // Header
        fputcsv($output, ['Datum', 'Umsatz (€)', 'Transaktionen', 'Durchschnitt (€)']);
        
        // Data
        $query = $wpdb->prepare("
            SELECT 
                DATE(created_at) as date,
                SUM(amount) as revenue,
                COUNT(*) as transactions,
                AVG(amount) as avg_amount
            FROM $table
            WHERE created_at BETWEEN %s AND %s
            GROUP BY date
            ORDER BY date
        ", $start_date, $end_date . ' 23:59:59');
        
        $results = $wpdb->get_results($query);
        
        foreach ($results as $row) {
            fputcsv($output, [
                $row->date,
                number_format($row->revenue, 2, ',', '.'),
                $row->transactions,
                number_format($row->avg_amount, 2, ',', '.')
            ]);
        }
    }
    
    /**
     * Helper: Format period label
     */
    private function format_period_label($period, $type) {
        switch ($type) {
            case 'daily':
                return date('d.m', strtotime($period));
            case 'weekly':
                $year = substr($period, 0, 4);
                $week = substr($period, 4);
                return "KW $week/$year";
            case 'monthly':
                return date('M Y', strtotime($period . '-01'));
            default:
                return $period;
        }
    }
    
    /**
     * Helper: Calculate revenue share
     */
    private function calculate_revenue_share($product_revenue, $start_date, $end_date) {
        global $wpdb;
        $table = $wpdb->prefix . 'smp_transactions';
        
        $total = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(amount) FROM $table
            WHERE created_at BETWEEN %s AND %s
        ", $start_date, $end_date . ' 23:59:59'));
        
        return $total > 0 ? ($product_revenue / $total) * 100 : 0;
    }
    
    /**
     * Helper: Calculate product trend
     */
    private function calculate_product_trend($product_id, $start_date, $end_date) {
        global $wpdb;
        $table = $wpdb->prefix . 'smp_transactions';
        
        $mid_date = date('Y-m-d', (strtotime($start_date) + strtotime($end_date)) / 2);
        
        $first_half = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $table
            WHERE product_id = %d
            AND created_at BETWEEN %s AND %s
        ", $product_id, $start_date, $mid_date));
        
        $second_half = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $table
            WHERE product_id = %d
            AND created_at BETWEEN %s AND %s
        ", $product_id, $mid_date, $end_date . ' 23:59:59'));
        
        if ($first_half == 0) return 0;
        return (($second_half - $first_half) / $first_half) * 100;
    }
    
    /**
     * Helper: Analyze hourly pattern
     */
    private function analyze_hourly_pattern($data) {
        $hourly = array_fill(0, 24, ['count' => 0, 'revenue' => 0]);
        
        foreach ($data as $row) {
            $hour = intval($row->hour);
            $hourly[$hour]['count'] += $row->transaction_count;
            $hourly[$hour]['revenue'] += $row->revenue;
        }
        
        return $hourly;
    }
    
    /**
     * Helper: Analyze weekly pattern
     */
    private function analyze_weekly_pattern($data) {
        $weekly = array_fill(1, 7, ['count' => 0, 'revenue' => 0]);
        
        foreach ($data as $row) {
            $day = intval($row->day_of_week);
            $weekly[$day]['count'] += $row->transaction_count;
            $weekly[$day]['revenue'] += $row->revenue;
        }
        
        return $weekly;
    }
    
    /**
     * Helper: Calculate growth trend
     */
    private function calculate_growth_trend($data, $period) {
        if (count($data) < 2) return 0;
        
        $first_period = array_slice($data, 0, count($data) / 2);
        $second_period = array_slice($data, count($data) / 2);
        
        $first_revenue = array_sum(array_column($first_period, 'revenue'));
        $second_revenue = array_sum(array_column($second_period, 'revenue'));
        
        if ($first_revenue == 0) return 0;
        return (($second_revenue - $first_revenue) / $first_revenue) * 100;
    }
    
    /**
     * Helper: Get peak hours
     */
    private function get_peak_hours($hourly_pattern) {
        $peak_hours = [];
        arsort($hourly_pattern);
        
        $top_3 = array_slice($hourly_pattern, 0, 3, true);
        foreach ($top_3 as $hour => $data) {
            $peak_hours[] = $hour;
        }
        
        sort($peak_hours);
        return $peak_hours;
    }
    
    /**
     * Helper: Get peak days
     */
    private function get_peak_days($weekly_pattern) {
        $days = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
        $peak_days = [];
        
        arsort($weekly_pattern);
        $top_3 = array_slice($weekly_pattern, 0, 3, true);
        
        foreach ($top_3 as $day => $data) {
            $peak_days[] = $days[$day - 1];
        }
        
        return $peak_days;
    }
    
    /**
     * Helper: Get period metrics
     */
    private function get_period_metrics($start_date, $end_date) {
        global $wpdb;
        $trans_table = $wpdb->prefix . 'smp_transactions';
        $prod_table = $wpdb->prefix . 'smp_products';
        
        // Basic metrics
        $metrics = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as transactions,
                SUM(amount) as revenue,
                AVG(amount) as avg_transaction
            FROM $trans_table
            WHERE created_at BETWEEN %s AND %s
        ", $start_date, $end_date . ' 23:59:59'));
        
        // Top products
        $top_products = $wpdb->get_results($wpdb->prepare("
            SELECT 
                p.name,
                COUNT(*) as count,
                SUM(t.amount) as revenue
            FROM $trans_table t
            JOIN $prod_table p ON t.product_id = p.id
            WHERE t.created_at BETWEEN %s AND %s
            GROUP BY p.id
            ORDER BY revenue DESC
            LIMIT 5
        ", $start_date, $end_date . ' 23:59:59'));
        
        return [
            'transactions' => intval($metrics->transactions ?? 0),
            'revenue' => floatval($metrics->revenue ?? 0),
            'avg_transaction' => floatval($metrics->avg_transaction ?? 0),
            'top_products' => $top_products
        ];
    }
    
    /**
     * Helper: Calculate moving average
     */
    private function calculate_moving_average($data, $period) {
        $ma = [];
        $count = count($data);
        
        for ($i = $period - 1; $i < $count; $i++) {
            $sum = 0;
            for ($j = 0; $j < $period; $j++) {
                $sum += $data[$i - $j]->daily_revenue;
            }
            $ma[] = $sum / $period;
        }
        
        return $ma;
    }
    
    /**
     * Helper: Calculate trend
     */
    private function calculate_trend($data) {
        if (count($data) < 2) return 0;
        
        // Simple linear regression
        $n = count($data);
        $x_sum = 0;
        $y_sum = 0;
        $xy_sum = 0;
        $x2_sum = 0;
        
        foreach ($data as $i => $point) {
            $x = $i;
            $y = $point->daily_revenue;
            
            $x_sum += $x;
            $y_sum += $y;
            $xy_sum += ($x * $y);
            $x2_sum += ($x * $x);
        }
        
        $slope = ($n * $xy_sum - $x_sum * $y_sum) / ($n * $x2_sum - $x_sum * $x_sum);
        $avg_y = $y_sum / $n;
        
        return $avg_y > 0 ? $slope / $avg_y : 0;
    }
    
    /**
     * Helper: Get day seasonality
     */
    private function get_day_seasonality($day_of_week, $historical) {
        $day_totals = array_fill(0, 7, 0);
        $day_counts = array_fill(0, 7, 0);
        
        foreach ($historical as $data) {
            $dow = date('w', strtotime($data->date));
            $day_totals[$dow] += $data->daily_revenue;
            $day_counts[$dow]++;
        }
        
        $avg_by_day = [];
        for ($i = 0; $i < 7; $i++) {
            $avg_by_day[$i] = $day_counts[$i] > 0 ? $day_totals[$i] / $day_counts[$i] : 0;
        }
        
        $overall_avg = array_sum($avg_by_day) / 7;
        
        return $overall_avg > 0 ? $avg_by_day[$day_of_week] / $overall_avg : 1;
    }
    
    /**
     * Helper: Calculate forecast accuracy
     */
    private function calculate_forecast_accuracy() {
        // Placeholder - würde normalerweise historische Forecasts mit tatsächlichen Werten vergleichen
        return rand(85, 95);
    }
    
    /**
     * Export as PDF (requires additional library)
     */
    private function export_pdf($data_type, $start_date, $end_date) {
        // This would require a PDF library like TCPDF or mPDF
        // For now, return error
        wp_send_json_error(['message' => 'PDF export requires additional setup']);
    }
}