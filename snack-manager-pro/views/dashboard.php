<?php
/**
 * Dashboard View - Snack Manager Pro
 */

// Direktzugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

// Aktuelle Session-Daten abrufen
global $wpdb;
$session_table = $wpdb->prefix . 'smp_sessions';
$current_session = $wpdb->get_row("SELECT * FROM $session_table WHERE status = 'active' ORDER BY id DESC LIMIT 1");

// Statistiken abrufen
$products_table = $wpdb->prefix . 'smp_products';
$transactions_table = $wpdb->prefix . 'smp_transactions';

$total_products = $wpdb->get_var("SELECT COUNT(*) FROM $products_table WHERE status = 'active'");
$low_stock_products = $wpdb->get_var("SELECT COUNT(*) FROM $products_table WHERE stock_quantity < 10 AND status = 'active'");
$today_sales = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $transactions_table WHERE DATE(created_at) = %s AND transaction_type = 'sale'",
    current_time('Y-m-d')
));
$today_revenue = $wpdb->get_var($wpdb->prepare(
    "SELECT SUM(amount) FROM $transactions_table WHERE DATE(created_at) = %s AND transaction_type = 'sale'",
    current_time('Y-m-d')
)) ?: 0;

// Top-Produkte heute
$top_products = $wpdb->get_results($wpdb->prepare("
    SELECT p.name, p.id, SUM(t.quantity) as total_sold, SUM(t.amount) as revenue
    FROM $transactions_table t
    JOIN $products_table p ON t.product_id = p.id
    WHERE DATE(t.created_at) = %s AND t.transaction_type = 'sale'
    GROUP BY t.product_id
    ORDER BY total_sold DESC
    LIMIT 5
", current_time('Y-m-d')));

// Letzte Transaktionen
$recent_transactions = $wpdb->get_results("
    SELECT t.*, p.name as product_name
    FROM $transactions_table t
    JOIN $products_table p ON t.product_id = p.id
    ORDER BY t.created_at DESC
    LIMIT 10
");
?>

<div class="smp-container">
    <!-- Header -->
    <div class="smp-header">
        <div class="smp-header-content smp-glass-container">
            <div>
                <h1><?php _e('Snack Manager Dashboard', 'snack-manager-pro'); ?></h1>
                <p style="margin: 0; opacity: 0.8;">
                    <?php echo sprintf(__('Willkommen zurück! Heute ist %s', 'snack-manager-pro'), date_i18n('l, j. F Y')); ?>
                </p>
            </div>
            <div class="smp-header-actions">
                <?php if ($current_session): ?>
                    <div class="smp-session-badge">
                        <span class="smp-pulse"></span>
                        <?php echo sprintf(__('Session #%d aktiv', 'snack-manager-pro'), $current_session->id); ?>
                    </div>
                <?php endif; ?>
                <button class="smp-btn smp-btn-primary" onclick="location.href='<?php echo admin_url('admin.php?page=smp-products'); ?>'">
                    <?php _e('Neues Produkt', 'snack-manager-pro'); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="smp-quick-stats">
        <div class="smp-stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <span class="smp-stat-icon">💰</span>
            <div class="smp-stat-value">€<?php echo number_format($today_revenue, 2, ',', '.'); ?></div>
            <div class="smp-stat-label"><?php _e('Heutiger Umsatz', 'snack-manager-pro'); ?></div>
        </div>
        
        <div class="smp-stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <span class="smp-stat-icon">🛒</span>
            <div class="smp-stat-value"><?php echo $today_sales; ?></div>
            <div class="smp-stat-label"><?php _e('Verkäufe heute', 'snack-manager-pro'); ?></div>
        </div>
        
        <div class="smp-stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
            <span class="smp-stat-icon">📦</span>
            <div class="smp-stat-value"><?php echo $total_products; ?></div>
            <div class="smp-stat-label"><?php _e('Aktive Produkte', 'snack-manager-pro'); ?></div>
        </div>
        
        <div class="smp-stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
            <span class="smp-stat-icon">⚠️</span>
            <div class="smp-stat-value"><?php echo $low_stock_products; ?></div>
            <div class="smp-stat-label"><?php _e('Niedriger Lagerbestand', 'snack-manager-pro'); ?></div>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="smp-dashboard-grid">
        <!-- Umsatz-Chart -->
        <div class="smp-card">
            <div class="smp-card-header">
                <h3><?php _e('Umsatzverlauf (letzte 7 Tage)', 'snack-manager-pro'); ?></h3>
            </div>
            <div class="smp-card-body">
                <div class="smp-chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Top Produkte -->
        <div class="smp-card">
            <div class="smp-card-header">
                <h3><?php _e('Top Produkte heute', 'snack-manager-pro'); ?></h3>
            </div>
            <div class="smp-card-body">
                <?php if ($top_products): ?>
                    <div class="smp-table-container">
                        <table class="smp-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Produkt', 'snack-manager-pro'); ?></th>
                                    <th><?php _e('Verkäufe', 'snack-manager-pro'); ?></th>
                                    <th><?php _e('Umsatz', 'snack-manager-pro'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_products as $product): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo admin_url('admin.php?page=smp-products&product_id=' . $product->id); ?>" 
                                               style="color: var(--smp-primary); text-decoration: none; font-weight: 600;">
                                                <?php echo esc_html($product->name); ?>
                                            </a>
                                        </td>
                                        <td><?php echo $product->total_sold; ?></td>
                                        <td>€<?php echo number_format($product->revenue, 2, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; opacity: 0.6; padding: 2rem;">
                        <?php _e('Heute wurden noch keine Produkte verkauft.', 'snack-manager-pro'); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Letzte Aktivitäten -->
    <div class="smp-card smp-mt-4">
        <div class="smp-card-header">
            <h3><?php _e('Letzte Aktivitäten', 'snack-manager-pro'); ?></h3>
        </div>
        <div class="smp-card-body">
            <?php if ($recent_transactions): ?>
                <div class="smp-activity-timeline">
                    <?php foreach ($recent_transactions as $transaction): ?>
                        <div class="smp-activity-item">
                            <div class="smp-activity-icon <?php echo $transaction->transaction_type === 'sale' ? 'sale' : 'refill'; ?>">
                                <?php echo $transaction->transaction_type === 'sale' ? '🛒' : '📦'; ?>
                            </div>
                            <div class="smp-activity-content">
                                <div class="smp-activity-title">
                                    <?php if ($transaction->transaction_type === 'sale'): ?>
                                        <?php echo sprintf(
                                            __('%d × %s verkauft', 'snack-manager-pro'),
                                            $transaction->quantity,
                                            esc_html($transaction->product_name)
                                        ); ?>
                                    <?php else: ?>
                                        <?php echo sprintf(
                                            __('%d × %s aufgefüllt', 'snack-manager-pro'),
                                            $transaction->quantity,
                                            esc_html($transaction->product_name)
                                        ); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="smp-activity-meta">
                                    <?php echo human_time_diff(strtotime($transaction->created_at), current_time('timestamp')); ?> <?php _e('vor', 'snack-manager-pro'); ?>
                                    <?php if ($transaction->transaction_type === 'sale'): ?>
                                        • €<?php echo number_format($transaction->amount, 2, ',', '.'); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="text-align: center; opacity: 0.6; padding: 2rem;">
                    <?php _e('Noch keine Aktivitäten vorhanden.', 'snack-manager-pro'); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Voice Control -->
<div class="smp-voice-control">
    <button class="smp-voice-btn" id="voiceControlBtn" title="<?php _e('Sprachsteuerung', 'snack-manager-pro'); ?>">
        🎤
    </button>
    <div class="smp-voice-feedback" id="voiceFeedback">
        <div class="smp-voice-status"><?php _e('Höre zu...', 'snack-manager-pro'); ?></div>
        <div class="smp-voice-transcript"></div>
    </div>
</div>

<!-- Floating Action Button -->
<button class="smp-fab" onclick="location.href='<?php echo admin_url('admin.php?page=smp-scanner'); ?>'" title="<?php _e('Scanner öffnen', 'snack-manager-pro'); ?>">
    📷
</button>

<style>
/* Activity Timeline */
.smp-activity-timeline {
    position: relative;
    padding-left: 40px;
}

.smp-activity-timeline::before {
    content: '';
    position: absolute;
    left: 19px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: var(--smp-gray-200);
}

.smp-dark-mode .smp-activity-timeline::before {
    background: var(--smp-gray-700);
}

.smp-activity-item {
    position: relative;
    padding-bottom: 1.5rem;
    display: flex;
    gap: 1rem;
}

.smp-activity-item:last-child {
    padding-bottom: 0;
}

.smp-activity-icon {
    position: absolute;
    left: -40px;
    width: 40px;
    height: 40px;
    background: white;
    border: 2px solid var(--smp-gray-200);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.smp-dark-mode .smp-activity-icon {
    background: var(--smp-gray-800);
    border-color: var(--smp-gray-600);
}

.smp-activity-icon.sale {
    border-color: var(--smp-success);
    background: rgba(16, 185, 129, 0.1);
}

.smp-activity-icon.refill {
    border-color: var(--smp-info);
    background: rgba(59, 130, 246, 0.1);
}

.smp-activity-content {
    flex: 1;
}

.smp-activity-title {
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.smp-activity-meta {
    font-size: 0.875rem;
    color: var(--smp-gray-500);
}

.smp-dark-mode .smp-activity-meta {
    color: var(--smp-gray-400);
}
</style>

<script>
// Chart.js Initialisierung
document.addEventListener('DOMContentLoaded', function() {
    // Umsatzdaten für die letzten 7 Tage vorbereiten
    <?php
    $revenue_data = array();
    $labels = array();
    
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $day_revenue = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM $transactions_table 
             WHERE DATE(created_at) = %s AND transaction_type = 'sale'",
            $date
        )) ?: 0;
        
        $revenue_data[] = $day_revenue;
        $labels[] = date_i18n('D', strtotime($date));
    }
    ?>
    
    const ctx = document.getElementById('revenueChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Umsatz in €',
                    data: <?php echo json_encode($revenue_data); ?>,
                    borderColor: 'rgb(99, 102, 241)',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 5,
                    pointHoverRadius: 8,
                    pointBackgroundColor: 'rgb(99, 102, 241)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        cornerRadius: 8,
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 13
                        },
                        callbacks: {
                            label: function(context) {
                                return 'Umsatz: €' + context.parsed.y.toFixed(2).replace('.', ',');
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            callback: function(value) {
                                return '€' + value.toFixed(0);
                            },
                            color: '#6b7280',
                            font: {
                                size: 11
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            color: '#6b7280',
                            font: {
                                size: 11
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>