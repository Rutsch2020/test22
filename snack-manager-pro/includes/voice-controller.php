<?php
/**
 * Voice Controller Class
 * 
 * @package SnackManagerPro
 * @since 1.0.0
 */

namespace SnackManagerPro;

if (!defined('ABSPATH')) {
    exit;
}

class VoiceController {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Supported languages
     */
    private $languages = ['de-DE', 'en-US'];
    
    /**
     * Current language
     */
    private $current_language = 'de-DE';
    
    /**
     * Command patterns for different languages
     */
    private $command_patterns = [
        'de-DE' => [
            'sale' => '/^(verkauf|verkaufe)\s+(\d+)?\s*(.+)$/i',
            'refill' => '/^(auffüllen|fülle auf|nachfüllen)\s+(\d+)?\s*(.+)$/i',
            'stock' => '/^(zeige\s+)?bestand(\s+von\s+(.+))?$/i',
            'help' => '/^(hilfe|was kannst du|befehle)$/i',
            'cancel' => '/^(abbrechen|stopp|stop)$/i',
            'statistics' => '/^(statistik|umsatz|zeige statistik)$/i'
        ],
        'en-US' => [
            'sale' => '/^(sale|sell)\s+(\d+)?\s*(.+)$/i',
            'refill' => '/^(refill|restock)\s+(\d+)?\s*(.+)$/i',
            'stock' => '/^(show\s+)?stock(\s+of\s+(.+))?$/i',
            'help' => '/^(help|what can you do|commands)$/i',
            'cancel' => '/^(cancel|stop)$/i',
            'statistics' => '/^(statistics|revenue|show stats)$/i'
        ]
    ];
    
    /**
     * Response templates
     */
    private $responses = [
        'de-DE' => [
            'sale_success' => '%d %s verkauft',
            'refill_success' => '%d %s aufgefüllt',
            'stock_info' => 'Bestand von %s: %d Stück',
            'product_not_found' => 'Produkt %s nicht gefunden',
            'insufficient_stock' => 'Nicht genügend %s auf Lager. Verfügbar: %d',
            'error' => 'Fehler: %s',
            'help' => 'Verfügbare Befehle: Verkauf [Anzahl] [Produkt], Auffüllen [Anzahl] [Produkt], Zeige Bestand',
            'command_not_understood' => 'Befehl nicht verstanden. Sage "Hilfe" für verfügbare Befehle.',
            'listening' => 'Ich höre zu...',
            'processing' => 'Verarbeite Befehl...'
        ],
        'en-US' => [
            'sale_success' => 'Sold %d %s',
            'refill_success' => 'Refilled %d %s',
            'stock_info' => 'Stock of %s: %d units',
            'product_not_found' => 'Product %s not found',
            'insufficient_stock' => 'Insufficient %s in stock. Available: %d',
            'error' => 'Error: %s',
            'help' => 'Available commands: Sale [quantity] [product], Refill [quantity] [product], Show stock',
            'command_not_understood' => 'Command not understood. Say "Help" for available commands.',
            'listening' => 'Listening...',
            'processing' => 'Processing command...'
        ]
    ];
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize
     */
    private function init() {
        // Set language based on WordPress locale
        $locale = get_locale();
        if (strpos($locale, 'de_') === 0) {
            $this->current_language = 'de-DE';
        } else {
            $this->current_language = 'en-US';
        }
        
        // Register AJAX handlers
        add_action('wp_ajax_snack_process_voice_command', [$this, 'ajax_process_command']);
        add_action('wp_ajax_snack_get_voice_settings', [$this, 'ajax_get_settings']);
        add_action('wp_ajax_snack_update_voice_language', [$this, 'ajax_update_language']);
        
        // Enqueue scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'snack-manager-pro') === false) {
            return;
        }
        
        wp_enqueue_script(
            'snack-voice-controller',
            SNACK_MANAGER_PRO_URL . 'assets/js/voice-controller.js',
            ['jquery'],
            SNACK_MANAGER_PRO_VERSION,
            true
        );
        
        wp_localize_script('snack-voice-controller', 'snackVoice', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('snack_voice_nonce'),
            'language' => $this->current_language,
            'strings' => $this->responses[$this->current_language],
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        ]);
        
        wp_enqueue_style(
            'snack-voice-styles',
            SNACK_MANAGER_PRO_URL . 'assets/css/voice-controller.css',
            [],
            SNACK_MANAGER_PRO_VERSION
        );
    }
    
    /**
     * Process voice command
     */
    public function process_command($command) {
        $command = trim($command);
        $patterns = $this->command_patterns[$this->current_language];
        $response = [
            'success' => false,
            'message' => '',
            'action' => '',
            'data' => []
        ];
        
        // Check each command pattern
        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $command, $matches)) {
                switch ($type) {
                    case 'sale':
                        $response = $this->process_sale_command($matches);
                        break;
                        
                    case 'refill':
                        $response = $this->process_refill_command($matches);
                        break;
                        
                    case 'stock':
                        $response = $this->process_stock_command($matches);
                        break;
                        
                    case 'help':
                        $response = [
                            'success' => true,
                            'message' => $this->responses[$this->current_language]['help'],
                            'action' => 'help',
                            'speak' => true
                        ];
                        break;
                        
                    case 'statistics':
                        $response = $this->process_statistics_command();
                        break;
                        
                    case 'cancel':
                        $response = [
                            'success' => true,
                            'message' => 'OK',
                            'action' => 'cancel',
                            'speak' => false
                        ];
                        break;
                }
                
                return $response;
            }
        }
        
        // Command not understood
        $response['message'] = $this->responses[$this->current_language]['command_not_understood'];
        $response['speak'] = true;
        
        return $response;
    }
    
    /**
     * Process sale command
     */
    private function process_sale_command($matches) {
        $quantity = !empty($matches[2]) ? intval($matches[2]) : 1;
        $product_name = trim($matches[3]);
        
        // Get product
        $product = $this->find_product_by_name($product_name);
        
        if (!$product) {
            return [
                'success' => false,
                'message' => sprintf($this->responses[$this->current_language]['product_not_found'], $product_name),
                'action' => 'sale',
                'speak' => true
            ];
        }
        
        // Check stock
        if ($product->stock < $quantity) {
            return [
                'success' => false,
                'message' => sprintf($this->responses[$this->current_language]['insufficient_stock'], $product->name, $product->stock),
                'action' => 'sale',
                'speak' => true
            ];
        }
        
        // Process sale
        $transaction = TransactionModel::get_instance();
        $result = $transaction->add_transaction([
            'product_id' => $product->id,
            'type' => 'sale',
            'quantity' => $quantity,
            'price' => $product->price * $quantity,
            'user_id' => get_current_user_id()
        ]);
        
        if ($result) {
            // Update stock
            $product_model = ProductModel::get_instance();
            $product_model->update_stock($product->id, -$quantity);
            
            return [
                'success' => true,
                'message' => sprintf($this->responses[$this->current_language]['sale_success'], $quantity, $product->name),
                'action' => 'sale',
                'data' => [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'new_stock' => $product->stock - $quantity
                ],
                'speak' => true,
                'refresh' => true
            ];
        }
        
        return [
            'success' => false,
            'message' => sprintf($this->responses[$this->current_language]['error'], 'Transaction failed'),
            'action' => 'sale',
            'speak' => true
        ];
    }
    
    /**
     * Process refill command
     */
    private function process_refill_command($matches) {
        $quantity = !empty($matches[2]) ? intval($matches[2]) : 1;
        $product_name = trim($matches[3]);
        
        // Get product
        $product = $this->find_product_by_name($product_name);
        
        if (!$product) {
            return [
                'success' => false,
                'message' => sprintf($this->responses[$this->current_language]['product_not_found'], $product_name),
                'action' => 'refill',
                'speak' => true
            ];
        }
        
        // Process refill
        $transaction = TransactionModel::get_instance();
        $result = $transaction->add_transaction([
            'product_id' => $product->id,
            'type' => 'refill',
            'quantity' => $quantity,
            'price' => 0,
            'user_id' => get_current_user_id()
        ]);
        
        if ($result) {
            // Update stock
            $product_model = ProductModel::get_instance();
            $product_model->update_stock($product->id, $quantity);
            
            return [
                'success' => true,
                'message' => sprintf($this->responses[$this->current_language]['refill_success'], $quantity, $product->name),
                'action' => 'refill',
                'data' => [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'new_stock' => $product->stock + $quantity
                ],
                'speak' => true,
                'refresh' => true
            ];
        }
        
        return [
            'success' => false,
            'message' => sprintf($this->responses[$this->current_language]['error'], 'Transaction failed'),
            'action' => 'refill',
            'speak' => true
        ];
    }
    
    /**
     * Process stock command
     */
    private function process_stock_command($matches) {
        $product_name = isset($matches[3]) ? trim($matches[3]) : null;
        
        if ($product_name) {
            // Show stock for specific product
            $product = $this->find_product_by_name($product_name);
            
            if (!$product) {
                return [
                    'success' => false,
                    'message' => sprintf($this->responses[$this->current_language]['product_not_found'], $product_name),
                    'action' => 'stock',
                    'speak' => true
                ];
            }
            
            return [
                'success' => true,
                'message' => sprintf($this->responses[$this->current_language]['stock_info'], $product->name, $product->stock),
                'action' => 'stock',
                'data' => [
                    'product_id' => $product->id,
                    'stock' => $product->stock
                ],
                'speak' => true
            ];
        } else {
            // Show all stock
            $product_model = ProductModel::get_instance();
            $products = $product_model->get_all_products();
            
            $stock_info = [];
            foreach ($products as $product) {
                $stock_info[] = sprintf('%s: %d', $product->name, $product->stock);
            }
            
            return [
                'success' => true,
                'message' => implode(', ', $stock_info),
                'action' => 'stock',
                'data' => $products,
                'speak' => true
            ];
        }
    }
    
    /**
     * Process statistics command
     */
    private function process_statistics_command() {
        $stats = DatabaseModel::get_instance()->get_dashboard_stats();
        
        $message = sprintf(
            $this->current_language === 'de-DE' ? 
                'Heutiger Umsatz: %.2f Euro, Transaktionen: %d, Niedriger Bestand: %d Produkte' :
                'Today\'s revenue: %.2f Euro, Transactions: %d, Low stock: %d products',
            $stats['today_revenue'],
            $stats['today_sales'],
            $stats['low_stock_count']
        );
        
        return [
            'success' => true,
            'message' => $message,
            'action' => 'statistics',
            'data' => $stats,
            'speak' => true
        ];
    }
    
    /**
     * Find product by name (fuzzy search)
     */
    private function find_product_by_name($name) {
        $product_model = ProductModel::get_instance();
        $products = $product_model->get_all_products();
        
        $name_lower = strtolower($name);
        $best_match = null;
        $best_score = 0;
        
        foreach ($products as $product) {
            $product_name_lower = strtolower($product->name);
            
            // Exact match
            if ($product_name_lower === $name_lower) {
                return $product;
            }
            
            // Partial match
            if (strpos($product_name_lower, $name_lower) !== false || strpos($name_lower, $product_name_lower) !== false) {
                $score = similar_text($product_name_lower, $name_lower);
                if ($score > $best_score) {
                    $best_score = $score;
                    $best_match = $product;
                }
            }
        }
        
        // Return best match if score is high enough
        if ($best_score > 50) {
            return $best_match;
        }
        
        return null;
    }
    
    /**
     * AJAX handler for processing voice commands
     */
    public function ajax_process_command() {
        check_ajax_referer('snack_voice_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $command = isset($_POST['command']) ? sanitize_text_field($_POST['command']) : '';
        
        if (empty($command)) {
            wp_send_json_error(['message' => 'No command provided']);
        }
        
        $response = $this->process_command($command);
        
        if ($response['success']) {
            wp_send_json_success($response);
        } else {
            wp_send_json_error($response);
        }
    }
    
    /**
     * AJAX handler for getting voice settings
     */
    public function ajax_get_settings() {
        check_ajax_referer('snack_voice_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        wp_send_json_success([
            'language' => $this->current_language,
            'languages' => $this->languages,
            'strings' => $this->responses[$this->current_language]
        ]);
    }
    
    /**
     * AJAX handler for updating language
     */
    public function ajax_update_language() {
        check_ajax_referer('snack_voice_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $language = isset($_POST['language']) ? sanitize_text_field($_POST['language']) : '';
        
        if (!in_array($language, $this->languages)) {
            wp_send_json_error(['message' => 'Invalid language']);
        }
        
        $this->current_language = $language;
        update_option('snack_voice_language', $language);
        
        wp_send_json_success([
            'language' => $this->current_language,
            'strings' => $this->responses[$this->current_language]
        ]);
    }
    
    /**
     * Render voice control interface
     */
    public function render_voice_control() {
        ?>
        <div class="voice-control-widget">
            <div class="voice-status">
                <div class="voice-icon">
                    <svg class="voice-wave" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M12 1v22M17 4v16M7 8v8M22 7v10M2 7v10" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
                <span class="voice-status-text"><?php echo esc_html($this->responses[$this->current_language]['listening']); ?></span>
            </div>
            
            <button type="button" class="voice-toggle-btn" id="voice-toggle">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
                    <path d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v4M8 23h8"/>
                </svg>
            </button>
            
            <div class="voice-transcript" id="voice-transcript"></div>
            
            <div class="voice-settings">
                <label><?php _e('Language:', 'snack-manager-pro'); ?></label>
                <select id="voice-language" class="voice-language-select">
                    <?php foreach ($this->languages as $lang): ?>
                        <option value="<?php echo esc_attr($lang); ?>" <?php selected($this->current_language, $lang); ?>>
                            <?php echo esc_html($lang); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php
    }
}