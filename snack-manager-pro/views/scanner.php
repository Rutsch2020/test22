<?php
/**
 * Scanner View
 * 
 * @package SnackManagerPro
 */

// Sicherheit
if (!defined('ABSPATH')) {
    exit;
}

// Session-Informationen abrufen
$session = new SMP_Session();
$active_session = $session->get_active_session();

if (!$active_session) {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Scanner', 'snack-manager-pro'); ?></h1>
        <div class="notice notice-error">
            <p><?php echo esc_html__('Keine aktive Session. Bitte starten Sie zuerst eine neue Session im Dashboard.', 'snack-manager-pro'); ?></p>
            <p><a href="<?php echo admin_url('admin.php?page=snack-manager-pro'); ?>" class="button button-primary"><?php echo esc_html__('Zum Dashboard', 'snack-manager-pro'); ?></a></p>
        </div>
    </div>
    <?php
    return;
}

// Session-Statistiken
$session_revenue = $session->get_session_revenue($active_session->id);
$session_count = $session->get_session_transaction_count($active_session->id);
?>

<div class="wrap" id="smp-scanner-page">
    <h1><?php echo esc_html__('Barcode Scanner', 'snack-manager-pro'); ?></h1>
    
    <!-- Session Info -->
    <div class="smp-session-info">
        <div class="session-stats">
            <span class="stat-item">
                <i class="fas fa-clock"></i>
                <?php echo esc_html__('Session gestartet:', 'snack-manager-pro'); ?> 
                <?php echo date_i18n('H:i', strtotime($active_session->start_time)); ?>
            </span>
            <span class="stat-item">
                <i class="fas fa-euro-sign"></i>
                <?php echo esc_html__('Umsatz:', 'snack-manager-pro'); ?> 
                <span id="session-revenue"><?php echo number_format($session_revenue, 2, ',', '.'); ?> €</span>
            </span>
            <span class="stat-item">
                <i class="fas fa-shopping-cart"></i>
                <?php echo esc_html__('Verkäufe:', 'snack-manager-pro'); ?> 
                <span id="session-count"><?php echo $session_count; ?></span>
            </span>
        </div>
    </div>
    
    <!-- Scanner Status -->
    <div id="scanner-status" class="alert alert-info" style="display: none;"></div>
    
    <div class="smp-scanner-container">
        <div class="row">
            <!-- Scanner Bereich -->
            <div class="col-md-8">
                <div class="scanner-panel">
                    <h2><?php echo esc_html__('Scanner', 'snack-manager-pro'); ?></h2>
                    
                    <!-- Scanner Container -->
                    <div id="scanner-container" class="scanner-viewport">
                        <!-- Quagga wird hier die Kamera anzeigen -->
                    </div>
                    
                    <!-- Scanner Controls -->
                    <div class="scanner-controls">
                        <button id="start-scanner-btn" class="button button-primary">
                            <i class="fas fa-camera"></i> <?php echo esc_html__('Scanner starten', 'snack-manager-pro'); ?>
                        </button>
                        <button id="stop-scanner-btn" class="button button-secondary" style="display: none;">
                            <i class="fas fa-stop"></i> <?php echo esc_html__('Scanner stoppen', 'snack-manager-pro'); ?>
                        </button>
                    </div>
                    
                    <!-- Manuelle Eingabe -->
                    <div id="manual-input-section" class="manual-input-section">
                        <h3><?php echo esc_html__('Manuelle Eingabe', 'snack-manager-pro'); ?></h3>
                        <div class="input-group">
                            <input type="text" id="manual-barcode-input" class="form-control" 
                                   placeholder="<?php echo esc_attr__('Barcode eingeben...', 'snack-manager-pro'); ?>" 
                                   autocomplete="off">
                            <button id="submit-barcode-btn" class="button button-primary">
                                <i class="fas fa-search"></i> <?php echo esc_html__('Suchen', 'snack-manager-pro'); ?>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Scan Ergebnis -->
                    <div id="scan-result" class="scan-result d-none">
                        <h3><?php echo esc_html__('Produkt gefunden', 'snack-manager-pro'); ?></h3>
                        <div class="product-details">
                            <img id="product-image" src="" alt="" style="display: none;">
                            <div class="product-info">
                                <h4 id="product-name"></h4>
                                <div class="product-meta">
                                    <span class="price">
                                        <i class="fas fa-euro-sign"></i> 
                                        <span id="product-price"></span>
                                    </span>
                                    <span class="stock">
                                        <i class="fas fa-box"></i> 
                                        <?php echo esc_html__('Lager:', 'snack-manager-pro'); ?> 
                                        <span id="product-stock"></span>
                                    </span>
                                    <span class="category">
                                        <i class="fas fa-tag"></i> 
                                        <span id="product-category"></span>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="action-buttons">
                            <button id="confirm-sale-btn" class="button button-primary button-large">
                                <i class="fas fa-check"></i> <?php echo esc_html__('Verkauf bestätigen', 'snack-manager-pro'); ?>
                            </button>
                            <button id="cancel-scan-btn" class="button button-secondary">
                                <i class="fas fa-times"></i> <?php echo esc_html__('Abbrechen', 'snack-manager-pro'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Products -->
            <div class="col-md-4">
                <div class="quick-products-panel">
                    <h2><?php echo esc_html__('Schnellauswahl', 'snack-manager-pro'); ?></h2>
                    <div id="quick-products-grid" class="quick-products-grid">
                        <!-- Wird per AJAX geladen -->
                    </div>
                </div>
                
                <!-- Letzte Verkäufe -->
                <div class="recent-sales-panel">
                    <h3><?php echo esc_html__('Letzte Verkäufe', 'snack-manager-pro'); ?></h3>
                    <div id="recent-sales-list">
                        <?php
                        $transaction = new SMP_Transaction();
                        $recent = $transaction->get_recent_by_session($active_session->id, 5);
                        
                        if ($recent) {
                            echo '<ul class="recent-sales">';
                            foreach ($recent as $sale) {
                                echo '<li>';
                                echo '<span class="product-name">' . esc_html($sale->product_name) . '</span>';
                                echo '<span class="sale-time">' . date_i18n('H:i', strtotime($sale->created_at)) . '</span>';
                                echo '<span class="sale-price">' . number_format($sale->amount, 2, ',', '.') . ' €</span>';
                                echo '</li>';
                            }
                            echo '</ul>';
                        } else {
                            echo '<p class="no-sales">' . esc_html__('Noch keine Verkäufe', 'snack-manager-pro') . '</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Scanner Styles */
.smp-scanner-container {
    margin-top: 20px;
}

.smp-session-info {
    background: #f0f0f1;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.session-stats {
    display: flex;
    gap: 30px;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.scanner-panel,
.quick-products-panel,
.recent-sales-panel {
    background: #fff;
    padding: 20px;
    border: 1px solid #c3c4c7;
    border-radius: 5px;
    margin-bottom: 20px;
}

.scanner-viewport {
    position: relative;
    width: 100%;
    height: 400px;
    background: #000;
    border-radius: 5px;
    overflow: hidden;
    margin-bottom: 20px;
}

.scanner-viewport.active {
    border: 2px solid #2271b1;
}

.scanner-viewport video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.scanner-controls {
    text-align: center;
    margin-bottom: 20px;
}

.manual-input-section {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.input-group {
    display: flex;
    gap: 10px;
}

.input-group input {
    flex: 1;
    padding: 8px 12px;
    font-size: 16px;
}

.scan-result {
    margin-top: 20px;
    padding: 20px;
    background: #f6f7f7;
    border-radius: 5px;
}

.product-details {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

.product-details img {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 5px;
}

.product-info h4 {
    margin: 0 0 10px 0;
    font-size: 20px;
}

.product-meta {
    display: flex;
    gap: 20px;
    color: #666;
}

.product-meta span {
    display: flex;
    align-items: center;
    gap: 5px;
}

.action-buttons {
    display: flex;
    gap: 10px;
}

.quick-products-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

.quick-product-btn {
    padding: 15px;
    background: #f0f0f1;
    border: 1px solid #c3c4c7;
    border-radius: 5px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: left;
}

.quick-product-btn:hover {
    background: #2271b1;
    color: #fff;
    transform: translateY(-2px);
}

.quick-product-btn .product-name {
    font-weight: 500;
    margin-bottom: 5px;
}

.quick-product-btn .product-price {
    font-size: 18px;
    font-weight: 600;
}

.quick-product-btn .product-stock {
    font-size: 12px;
    opacity: 0.8;
}

.recent-sales {
    list-style: none;
    margin: 0;
    padding: 0;
}

.recent-sales li {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.recent-sales .product-name {
    flex: 1;
}

.recent-sales .sale-time {
    color: #666;
    font-size: 12px;
    margin: 0 10px;
}

.recent-sales .sale-price {
    font-weight: 600;
    color: #2271b1;
}

.no-sales {
    text-align: center;
    color: #666;
    padding: 20px;
}

.d-none {
    display: none !important;
}

.alert {
    padding: 12px 20px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.alert-info {
    background: #e7f3ff;
    color: #0073aa;
    border-left: 4px solid #0073aa;
}

.alert-success {
    background: #e7f9e7;
    color: #00a32a;
    border-left: 4px solid #00a32a;
}

.alert-danger {
    background: #fcebea;
    color: #d63638;
    border-left: 4px solid #d63638;
}

/* Responsive */
@media (max-width: 768px) {
    .row {
        display: block;
    }
    
    .col-md-8,
    .col-md-4 {
        width: 100%;
    }
    
    .quick-products-grid {
        grid-template-columns: 1fr;
    }
    
    .session-stats {
        flex-direction: column;
        gap: 10px;
    }
}
</style>