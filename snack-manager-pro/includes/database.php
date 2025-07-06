<?php
/**
 * Database Wrapper Class für Snack Manager Pro
 * 
 * @package SnackManagerPro
 * @subpackage Database
 * @since 1.0.0
 */

// Direktzugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

class SMP_Database {
    
    /**
     * WordPress Database Object
     * @var wpdb
     */
    private $wpdb;
    
    /**
     * Tabellen-Präfix
     * @var string
     */
    private $prefix;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->prefix = $wpdb->prefix . 'smp_';
    }
    
    /**
     * Get table name with prefix
     *
     * @param string $table Table name without prefix
     * @return string Full table name
     */
    public function get_table_name($table) {
        return $this->prefix . $table;
    }
    
    /**
     * Get charset collate
     *
     * @return string
     */
    public function get_charset_collate() {
        return $this->wpdb->get_charset_collate();
    }
    
    /**
     * Prepare query
     *
     * @param string $query SQL query with placeholders
     * @param array $args Arguments for placeholders
     * @return string Prepared query
     */
    public function prepare($query, ...$args) {
        return $this->wpdb->prepare($query, ...$args);
    }
    
    /**
     * Execute query
     *
     * @param string $query SQL query
     * @return int|false Number of rows affected/selected or false on error
     */
    public function query($query) {
        return $this->wpdb->query($query);
    }
    
    /**
     * Get single variable
     *
     * @param string $query SQL query
     * @param int $x Column offset (default 0)
     * @param int $y Row offset (default 0)
     * @return string|null
     */
    public function get_var($query, $x = 0, $y = 0) {
        return $this->wpdb->get_var($query, $x, $y);
    }
    
    /**
     * Get single row
     *
     * @param string $query SQL query
     * @param string $output OBJECT, ARRAY_A or ARRAY_N
     * @param int $y Row offset
     * @return object|array|null
     */
    public function get_row($query, $output = OBJECT, $y = 0) {
        return $this->wpdb->get_row($query, $output, $y);
    }
    
    /**
     * Get single column
     *
     * @param string $query SQL query
     * @param int $x Column offset
     * @return array
     */
    public function get_col($query, $x = 0) {
        return $this->wpdb->get_col($query, $x);
    }
    
    /**
     * Get multiple rows
     *
     * @param string $query SQL query
     * @param string $output OBJECT, ARRAY_A or ARRAY_N
     * @return array
     */
    public function get_results($query, $output = OBJECT) {
        return $this->wpdb->get_results($query, $output);
    }
    
    /**
     * Insert row
     *
     * @param string $table Table name
     * @param array $data Data to insert
     * @param array $format Format array
     * @return int|false Number of rows inserted or false
     */
    public function insert($table, $data, $format = null) {
        return $this->wpdb->insert($this->get_table_name($table), $data, $format);
    }
    
    /**
     * Update rows
     *
     * @param string $table Table name
     * @param array $data Data to update
     * @param array $where WHERE conditions
     * @param array $format Format array for data
     * @param array $where_format Format array for where
     * @return int|false Number of rows updated or false
     */
    public function update($table, $data, $where, $format = null, $where_format = null) {
        return $this->wpdb->update($this->get_table_name($table), $data, $where, $format, $where_format);
    }
    
    /**
     * Delete rows
     *
     * @param string $table Table name
     * @param array $where WHERE conditions
     * @param array $where_format Format array
     * @return int|false Number of rows deleted or false
     */
    public function delete($table, $where, $where_format = null) {
        return $this->wpdb->delete($this->get_table_name($table), $where, $where_format);
    }
    
    /**
     * Replace row
     *
     * @param string $table Table name
     * @param array $data Data to insert
     * @param array $format Format array
     * @return int|false Number of rows affected or false
     */
    public function replace($table, $data, $format = null) {
        return $this->wpdb->replace($this->get_table_name($table), $data, $format);
    }
    
    /**
     * Get last insert ID
     *
     * @return int
     */
    public function insert_id() {
        return $this->wpdb->insert_id;
    }
    
    /**
     * Get last error
     *
     * @return string
     */
    public function last_error() {
        return $this->wpdb->last_error;
    }
    
    /**
     * Show/hide errors
     *
     * @param bool $show
     */
    public function show_errors($show = true) {
        if ($show) {
            $this->wpdb->show_errors();
        } else {
            $this->wpdb->hide_errors();
        }
    }
    
    /**
     * Print error
     */
    public function print_error() {
        $this->wpdb->print_error();
    }
    
    /**
     * Begin transaction
     *
     * @return bool
     */
    public function begin_transaction() {
        return $this->query('START TRANSACTION');
    }
    
    /**
     * Commit transaction
     *
     * @return bool
     */
    public function commit() {
        return $this->query('COMMIT');
    }
    
    /**
     * Rollback transaction
     *
     * @return bool
     */
    public function rollback() {
        return $this->query('ROLLBACK');
    }
    
    /**
     * Escape string
     *
     * @param string $string
     * @return string
     */
    public function escape($string) {
        return esc_sql($string);
    }
    
    /**
     * Escape for LIKE queries
     *
     * @param string $string
     * @return string
     */
    public function esc_like($string) {
        return $this->wpdb->esc_like($string);
    }
    
    /**
     * Check if table exists
     *
     * @param string $table Table name without prefix
     * @return bool
     */
    public function table_exists($table) {
        $full_table_name = $this->get_table_name($table);
        $result = $this->get_var("SHOW TABLES LIKE '{$full_table_name}'");
        return !is_null($result);
    }
    
    /**
     * Get table columns
     *
     * @param string $table Table name without prefix
     * @return array
     */
    public function get_columns($table) {
        $full_table_name = $this->get_table_name($table);
        return $this->get_results("SHOW COLUMNS FROM {$full_table_name}");
    }
    
    /**
     * Optimize table
     *
     * @param string $table Table name without prefix
     * @return bool
     */
    public function optimize_table($table) {
        $full_table_name = $this->get_table_name($table);
        return $this->query("OPTIMIZE TABLE {$full_table_name}");
    }
    
    /**
     * Get database version
     *
     * @return string
     */
    public function get_db_version() {
        return $this->wpdb->db_version();
    }
}