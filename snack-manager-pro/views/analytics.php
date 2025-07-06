<?php
/**
 * Analytics View für Snack Manager Pro
 * 
 * @package SnackManagerPro
 * @subpackage Views
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="smp-analytics-container">
    <!-- Header Section -->
    <div class="smp-analytics-header">
        <div class="header-content">
            <h1 class="page-title">
                <i class="fas fa-chart-line"></i>
                <?php _e('Analytics Dashboard', 'snack-manager-pro'); ?>
            </h1>
            <div class="header-actions">
                <button class="btn-glass btn-refresh" id="refresh-analytics">
                    <i class="fas fa-sync-alt"></i>
                    <span><?php _e('Aktualisieren', 'snack-manager-pro'); ?></span>
                </button>
                <div class="export-dropdown">
                    <button class="btn-glass btn-export" id="export-toggle">
                        <i class="fas fa-download"></i>
                        <span><?php _e('Exportieren', 'snack-manager-pro'); ?></span>
                    </button>
                    <div class="export-menu" id="export-menu">
                        <a href="#" class="export-item" data-type="revenue" data-format="csv">
                            <i class="fas fa-file-csv"></i>
                            <?php _e('Umsatz (CSV)', 'snack-manager-pro'); ?>
                        </a>
                        <a href="#" class="export-item" data-type="products" data-format="csv">
                            <i class="fas fa-file-csv"></i>
                            <?php _e('Produkte (CSV)', 'snack-manager-pro'); ?>
                        </a>
                        <a href="#" class="export-item" data-type="transactions" data-format="csv">
                            <i class="fas fa-file-csv"></i>
                            <?php _e('Transaktionen (CSV)', 'snack-manager-pro'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Date Range Selector -->
        <div class="date-range-selector">
            <div class="date-input-group">
                <label><?php _e('Von:', 'snack-manager-pro'); ?></label>
                <input type="date" id="date-start" class="date-input" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
            </div>
            <div class="date-input-group">
                <label><?php _e('Bis:', 'snack-manager-pro'); ?></label>
                <input type="date" id="date-end" class="date-input" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="quick-select">
                <button class="btn-period" data-period="7"><?php _e('7 Tage', 'snack-manager-pro'); ?></button>
                <button class="btn-period active" data-period="30"><?php _e('30 Tage', 'snack-manager-pro'); ?></button>
                <button class="btn-period" data-period="90"><?php _e('90 Tage', 'snack-manager-pro'); ?></button>
                <button class="btn-period" data-period="365"><?php _e('1 Jahr', 'snack-manager-pro'); ?></button>
            </div>
        </div>
    </div>

    <!-- Live Stats Cards -->
    <div class="stats-grid" id="live-stats">
        <div class="stat-card revenue-card">
            <div class="stat-icon">
                <i class="fas fa-euro-sign"></i>
            </div>
            <div class="stat-content">
                <h3><?php _e('Heutiger Umsatz', 'snack-manager-pro'); ?></h3>
                <div class="stat-value">
                    <span class="amount" id="today-revenue">0,00</span>
                    <span class="currency">€</span>
                </div>
                <div class="stat-change positive" id="today-change">
                    <i class="fas fa-arrow-up"></i>
                    <span>0%</span>
                </div>
            </div>
            <div class="stat-sparkline">
                <canvas id="revenue-sparkline"></canvas>
            </div>
        </div>

        <div class="stat-card transactions-card">
            <div class="stat-icon">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="stat-content">
                <h3><?php _e('Transaktionen heute', 'snack-manager-pro'); ?></h3>
                <div class="stat-value">
                    <span class="amount" id="today-transactions">0</span>
                </div>
                <div class="stat-change" id="transactions-change">
                    <i class="fas fa-arrow-up"></i>
                    <span>0%</span>
                </div>
            </div>
            <div class="stat-sparkline">
                <canvas id="transactions-sparkline"></canvas>
            </div>
        </div>

        <div class="stat-card avg-card">
            <div class="stat-icon">
                <i class="fas fa-chart-bar"></i>
            </div>
            <div class="stat-content">
                <h3><?php _e('Ø Transaktionswert', 'snack-manager-pro'); ?></h3>
                <div class="stat-value">
                    <span class="amount" id="avg-transaction">0,00</span>
                    <span class="currency">€</span>
                </div>
                <div class="stat-trend" id="avg-trend">
                    <i class="fas fa-equals"></i>
                    <span><?php _e('Stabil', 'snack-manager-pro'); ?></span>
                </div>
            </div>
        </div>

        <div class="stat-card products-card">
            <div class="stat-icon">
                <i class="fas fa-box"></i>
            </div>
            <div class="stat-content">
                <h3><?php _e('Produkte', 'snack-manager-pro'); ?></h3>
                <div class="stat-value">
                    <span class="amount" id="total-products">0</span>
                </div>
                <div class="product-status">
                    <span class="status-item in-stock">
                        <i class="fas fa-check-circle"></i>
                        <span id="in-stock">0</span>
                    </span>
                    <span class="status-item low-stock">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span id="low-stock">0</span>
                    </span>
                    <span class="status-item out-stock">
                        <i class="fas fa-times-circle"></i>
                        <span id="out-stock">0</span>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Charts Section -->
    <div class="charts-section">
        <!-- Revenue Chart -->
        <div class="chart-container revenue-chart">
            <div class="chart-header">
                <h2><?php _e('Umsatzentwicklung', 'snack-manager-pro'); ?></h2>
                <div class="chart-controls">
                    <div class="period-selector">
                        <button class="period-btn active" data-period="daily"><?php _e('Täglich', 'snack-manager-pro'); ?></button>
                        <button class="period-btn" data-period="weekly"><?php _e('Wöchentlich', 'snack-manager-pro'); ?></button>
                        <button class="period-btn" data-period="monthly"><?php _e('Monatlich', 'snack-manager-pro'); ?></button>
                    </div>
                </div>
            </div>
            <div class="chart-body">
                <canvas id="revenue-chart"></canvas>
            </div>
            <div class="chart-summary">
                <div class="summary-item">
                    <span class="label"><?php _e('Gesamtumsatz:', 'snack-manager-pro'); ?></span>
                    <span class="value" id="total-revenue">0,00 €</span>
                </div>
                <div class="summary-item">
                    <span class="label"><?php _e('Transaktionen:', 'snack-manager-pro'); ?></span>
                    <span class="value" id="total-transactions">0</span>
                </div>
                <div class="summary-item">
                    <span class="label"><?php _e('Ø Wert:', 'snack-manager-pro'); ?></span>
                    <span class="value" id="avg-value">0,00 €</span>
                </div>
            </div>
        </div>

        <!-- Product Performance -->
        <div class="chart-container products-performance">
            <div class="chart-header">
                <h2><?php _e('Top Produkte', 'snack-manager-pro'); ?></h2>
                <div class="chart-controls">
                    <select id="product-limit" class="select-glass">
                        <option value="5">Top 5</option>
                        <option value="10" selected>Top 10</option>
                        <option value="20">Top 20</option>
                    </select>
                </div>
            </div>
            <div class="products-list" id="top-products">
                <!-- Products will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Trends and Insights -->
    <div class="insights-section">
        <!-- Sales Trends -->
        <div class="insight-card trends-card">
            <h3><i class="fas fa-chart-line"></i> <?php _e('Verkaufstrends', 'snack-manager-pro'); ?></h3>
            <div class="trends-content">
                <div class="trend-item">
                    <h4><?php _e('Beste Verkaufszeiten', 'snack-manager-pro'); ?></h4>
                    <div class="peak-hours" id="peak-hours">
                        <!-- Peak hours will be loaded here -->
                    </div>
                </div>
                <div class="trend-item">
                    <h4><?php _e('Stärkste Wochentage', 'snack-manager-pro'); ?></h4>
                    <div class="peak-days" id="peak-days">
                        <!-- Peak days will be loaded here -->
                    </div>
                </div>
                <div class="trend-item">
                    <h4><?php _e('Wachstum', 'snack-manager-pro'); ?></h4>
                    <div class="growth-indicator" id="growth-trend">
                        <i class="fas fa-arrow-up"></i>
                        <span class="growth-value">0%</span>
                        <span class="growth-label"><?php _e('im Zeitraum', 'snack-manager-pro'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hourly Heatmap -->
        <div class="insight-card heatmap-card">
            <h3><i class="fas fa-fire"></i> <?php _e('Aktivitäts-Heatmap', 'snack-manager-pro'); ?></h3>
            <div class="heatmap-container">
                <canvas id="hourly-heatmap"></canvas>
            </div>
        </div>

        <!-- Forecast -->
        <div class="insight-card forecast-card">
            <h3><i class="fas fa-crystal-ball"></i> <?php _e('Umsatzprognose', 'snack-manager-pro'); ?></h3>
            <div class="forecast-controls">
                <label><?php _e('Tage voraus:', 'snack-manager-pro'); ?></label>
                <select id="forecast-days" class="select-glass">
                    <option value="7" selected>7 Tage</option>
                    <option value="14">14 Tage</option>
                    <option value="30">30 Tage</option>
                </select>
            </div>
            <div class="forecast-chart">
                <canvas id="forecast-chart"></canvas>
            </div>
            <div class="forecast-accuracy">
                <i class="fas fa-info-circle"></i>
                <span><?php _e('Genauigkeit:', 'snack-manager-pro'); ?></span>
                <span class="accuracy-value" id="forecast-accuracy">0%</span>
            </div>
        </div>
    </div>

    <!-- Comparison Tool -->
    <div class="comparison-section">
        <div class="comparison-header">
            <h2><i class="fas fa-balance-scale"></i> <?php _e('Zeitraum-Vergleich', 'snack-manager-pro'); ?></h2>
            <button class="btn-glass" id="toggle-comparison">
                <i class="fas fa-exchange-alt"></i>
                <?php _e('Vergleich aktivieren', 'snack-manager-pro'); ?>
            </button>
        </div>
        
        <div class="comparison-content" id="comparison-content" style="display: none;">
            <div class="period-selectors">
                <div class="period-group">
                    <h4><?php _e('Zeitraum 1', 'snack-manager-pro'); ?></h4>
                    <input type="date" id="compare-start-1" class="date-input">
                    <span><?php _e('bis', 'snack-manager-pro'); ?></span>
                    <input type="date" id="compare-end-1" class="date-input">
                </div>
                <div class="vs-separator">VS</div>
                <div class="period-group">
                    <h4><?php _e('Zeitraum 2', 'snack-manager-pro'); ?></h4>
                    <input type="date" id="compare-start-2" class="date-input">
                    <span><?php _e('bis', 'snack-manager-pro'); ?></span>
                    <input type="date" id="compare-end-2" class="date-input">
                </div>
            </div>
            <button class="btn-primary" id="run-comparison">
                <i class="fas fa-play"></i>
                <?php _e('Vergleich starten', 'snack-manager-pro'); ?>
            </button>
            
            <div class="comparison-results" id="comparison-results">
                <!-- Comparison results will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Live Updates Indicator -->
    <div class="live-indicator" id="live-indicator">
        <span class="pulse"></span>
        <span class="text"><?php _e('Live', 'snack-manager-pro'); ?></span>
    </div>

    <!-- Loading Overlay -->
    <div class="analytics-loading" id="analytics-loading">
        <div class="loading-spinner">
            <i class="fas fa-spinner fa-spin"></i>
        </div>
        <p><?php _e('Daten werden geladen...', 'snack-manager-pro'); ?></p>
    </div>
</div>

<script>
// Pass localized data to JavaScript
var smpAnalytics = {
    ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
    nonce: '<?php echo wp_create_nonce('smp_nonce'); ?>',
    currency: '€',
    dateFormat: 'DD.MM.YYYY',
    thousands: '.',
    decimal: ',',
    labels: {
        revenue: '<?php _e('Umsatz', 'snack-manager-pro'); ?>',
        transactions: '<?php _e('Transaktionen', 'snack-manager-pro'); ?>',
        average: '<?php _e('Durchschnitt', 'snack-manager-pro'); ?>',
        growth: '<?php _e('Wachstum', 'snack-manager-pro'); ?>',
        decline: '<?php _e('Rückgang', 'snack-manager-pro'); ?>',
        stable: '<?php _e('Stabil', 'snack-manager-pro'); ?>',
        loading: '<?php _e('Wird geladen...', 'snack-manager-pro'); ?>',
        noData: '<?php _e('Keine Daten verfügbar', 'snack-manager-pro'); ?>',
        error: '<?php _e('Fehler beim Laden der Daten', 'snack-manager-pro'); ?>',
        days: ['<?php _e('So', 'snack-manager-pro'); ?>', '<?php _e('Mo', 'snack-manager-pro'); ?>', '<?php _e('Di', 'snack-manager-pro'); ?>', '<?php _e('Mi', 'snack-manager-pro'); ?>', '<?php _e('Do', 'snack-manager-pro'); ?>', '<?php _e('Fr', 'snack-manager-pro'); ?>', '<?php _e('Sa', 'snack-manager-pro'); ?>'],
        months: [
            '<?php _e('Januar', 'snack-manager-pro'); ?>',
            '<?php _e('Februar', 'snack-manager-pro'); ?>',
            '<?php _e('März', 'snack-manager-pro'); ?>',
            '<?php _e('April', 'snack-manager-pro'); ?>',
            '<?php _e('Mai', 'snack-manager-pro'); ?>',
            '<?php _e('Juni', 'snack-manager-pro'); ?>',
            '<?php _e('Juli', 'snack-manager-pro'); ?>',
            '<?php _e('August', 'snack-manager-pro'); ?>',
            '<?php _e('September', 'snack-manager-pro'); ?>',
            '<?php _e('Oktober', 'snack-manager-pro'); ?>',
            '<?php _e('November', 'snack-manager-pro'); ?>',
            '<?php _e('Dezember', 'snack-manager-pro'); ?>'
        ]
    }
};
</script>