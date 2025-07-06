<?php
/**
 * Snack Manager Pro - Settings Page
 * 
 * @package SnackManagerPro
 * @version 1.0.0
 */

// Sicherheitscheck
if (!defined('ABSPATH')) {
    exit;
}

// Hole aktuelle Einstellungen
$options = get_option('smp_settings', array());

// Standard-Werte setzen
$defaults = array(
    // Allgemeine Einstellungen
    'shop_name' => get_bloginfo('name'),
    'currency' => 'EUR',
    'currency_symbol' => '€',
    'tax_rate' => '19',
    'low_stock_threshold' => '10',
    'session_timeout' => '8',
    
    // Scanner Einstellungen
    'scanner_enabled' => '1',
    'scanner_sound' => '1',
    'auto_complete_sale' => '0',
    'camera_selection' => 'auto',
    
    // Voice Control Einstellungen
    'voice_enabled' => '1',
    'voice_language' => 'de-DE',
    'voice_feedback' => '1',
    
    // Benachrichtigungen
    'email_notifications' => '0',
    'notification_email' => get_option('admin_email'),
    'low_stock_alert' => '1',
    'daily_report' => '0',
    
    // Erweiterte Einstellungen
    'debug_mode' => '0',
    'data_retention' => '365',
    'api_access' => '0',
    'export_format' => 'csv',
    'dark_mode_default' => '0'
);

// Merge mit Defaults
$options = wp_parse_args($options, $defaults);

// Verarbeite Formular-Übermittlung
if (isset($_POST['smp_save_settings']) && wp_verify_nonce($_POST['smp_settings_nonce'], 'smp_settings_action')) {
    
    // Sammle alle Einstellungen
    $new_options = array();
    
    // Allgemeine Einstellungen
    $new_options['shop_name'] = sanitize_text_field($_POST['shop_name'] ?? '');
    $new_options['currency'] = sanitize_text_field($_POST['currency'] ?? 'EUR');
    $new_options['currency_symbol'] = sanitize_text_field($_POST['currency_symbol'] ?? '€');
    $new_options['tax_rate'] = floatval($_POST['tax_rate'] ?? 19);
    $new_options['low_stock_threshold'] = intval($_POST['low_stock_threshold'] ?? 10);
    $new_options['session_timeout'] = intval($_POST['session_timeout'] ?? 8);
    
    // Scanner Einstellungen
    $new_options['scanner_enabled'] = isset($_POST['scanner_enabled']) ? '1' : '0';
    $new_options['scanner_sound'] = isset($_POST['scanner_sound']) ? '1' : '0';
    $new_options['auto_complete_sale'] = isset($_POST['auto_complete_sale']) ? '1' : '0';
    $new_options['camera_selection'] = sanitize_text_field($_POST['camera_selection'] ?? 'auto');
    
    // Voice Control
    $new_options['voice_enabled'] = isset($_POST['voice_enabled']) ? '1' : '0';
    $new_options['voice_language'] = sanitize_text_field($_POST['voice_language'] ?? 'de-DE');
    $new_options['voice_feedback'] = isset($_POST['voice_feedback']) ? '1' : '0';
    
    // Benachrichtigungen
    $new_options['email_notifications'] = isset($_POST['email_notifications']) ? '1' : '0';
    $new_options['notification_email'] = sanitize_email($_POST['notification_email'] ?? '');
    $new_options['low_stock_alert'] = isset($_POST['low_stock_alert']) ? '1' : '0';
    $new_options['daily_report'] = isset($_POST['daily_report']) ? '1' : '0';
    
    // Erweiterte Einstellungen
    $new_options['debug_mode'] = isset($_POST['debug_mode']) ? '1' : '0';
    $new_options['data_retention'] = intval($_POST['data_retention'] ?? 365);
    $new_options['api_access'] = isset($_POST['api_access']) ? '1' : '0';
    $new_options['export_format'] = sanitize_text_field($_POST['export_format'] ?? 'csv');
    $new_options['dark_mode_default'] = isset($_POST['dark_mode_default']) ? '1' : '0';
    
    // Speichere Einstellungen
    update_option('smp_settings', $new_options);
    
    // Erfolgs-Nachricht
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Einstellungen erfolgreich gespeichert!', 'snack-manager-pro') . '</p></div>';
    
    // Aktualisiere $options Variable
    $options = $new_options;
}

// Teste Datenbankverbindung
$db_test = false;
if (isset($_POST['test_db_connection'])) {
    global $wpdb;
    $tables = array(
        $wpdb->prefix . 'smp_sessions',
        $wpdb->prefix . 'smp_products',
        $wpdb->prefix . 'smp_transactions'
    );
    
    $missing_tables = array();
    foreach ($tables as $table) {
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            $missing_tables[] = $table;
        }
    }
    
    if (empty($missing_tables)) {
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Alle Datenbanktabellen sind vorhanden!', 'snack-manager-pro') . '</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>' . __('Fehlende Tabellen: ', 'snack-manager-pro') . implode(', ', $missing_tables) . '</p></div>';
    }
    $db_test = true;
}
?>

<div class="wrap smp-settings-wrap">
    <h1 class="wp-heading-inline">
        <i class="fas fa-cog"></i> <?php _e('Snack Manager Pro - Einstellungen', 'snack-manager-pro'); ?>
    </h1>
    
    <div class="smp-settings-container">
        <form method="post" action="" class="smp-settings-form">
            <?php wp_nonce_field('smp_settings_action', 'smp_settings_nonce'); ?>
            
            <!-- Tab Navigation -->
            <div class="smp-settings-tabs">
                <ul class="smp-tab-nav">
                    <li class="active" data-tab="general"><i class="fas fa-store"></i> Allgemein</li>
                    <li data-tab="scanner"><i class="fas fa-barcode"></i> Scanner</li>
                    <li data-tab="voice"><i class="fas fa-microphone"></i> Sprachsteuerung</li>
                    <li data-tab="notifications"><i class="fas fa-bell"></i> Benachrichtigungen</li>
                    <li data-tab="advanced"><i class="fas fa-tools"></i> Erweitert</li>
                    <li data-tab="system"><i class="fas fa-server"></i> System</li>
                </ul>
            </div>
            
            <!-- Tab Content -->
            <div class="smp-tab-content">
                
                <!-- Allgemeine Einstellungen -->
                <div class="smp-tab-pane active" id="general">
                    <h2><?php _e('Allgemeine Einstellungen', 'snack-manager-pro'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="shop_name"><?php _e('Shop Name', 'snack-manager-pro'); ?></label></th>
                            <td>
                                <input type="text" id="shop_name" name="shop_name" value="<?php echo esc_attr($options['shop_name']); ?>" class="regular-text" />
                                <p class="description"><?php _e('Der Name Ihres Snack-Shops', 'snack-manager-pro'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="currency"><?php _e('Währung', 'snack-manager-pro'); ?></label></th>
                            <td>
                                <select id="currency" name="currency">
                                    <option value="EUR" <?php selected($options['currency'], 'EUR'); ?>>EUR (€)</option>
                                    <option value="USD" <?php selected($options['currency'], 'USD'); ?>>USD ($)</option>
                                    <option value="GBP" <?php selected($options['currency'], 'GBP'); ?>>GBP (£)</option>
                                    <option value="CHF" <?php selected($options['currency'], 'CHF'); ?>>CHF (Fr.)</option>
                                </select>
                                <input type="text" id="currency_symbol" name="currency_symbol" value="<?php echo esc_attr($options['currency_symbol']); ?>" class="small-text" />
                                <p class="description"><?php _e('Währung und Symbol für Preisanzeigen', 'snack-manager-pro'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="tax_rate"><?php _e('Steuersatz (%)', 'snack-manager-pro'); ?></label></th>
                            <td>
                                <input type="number" id="tax_rate" name="tax_rate" value="<?php echo esc_attr($options['tax_rate']); ?>" class="small-text" step="0.1" min="0" max="100" />
                                <p class="description"><?php _e('Standard-Steuersatz für Produkte', 'snack-manager-pro'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="low_stock_threshold"><?php _e('Niedrigbestand-Schwelle', 'snack-manager-pro'); ?></label></th>
                            <td>
                                <input type="number" id="low_stock_threshold" name="low_stock_threshold" value="<?php echo esc_attr($options['low_stock_threshold']); ?>" class="small-text" min="1" />
                                <p class="description"><?php _e('Warnung wenn Bestand unter diese Menge fällt', 'snack-manager-pro'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="session_timeout"><?php _e('Session-Timeout (Stunden)', 'snack-manager-pro'); ?></label></th>
                            <td>
                                <input type="number" id="session_timeout" name="session_timeout" value="<?php echo esc_attr($options['session_timeout']); ?>" class="small-text" min="1" max="24" />
                                <p class="description"><?php _e('Automatisches Schließen inaktiver Sessions', 'snack-manager-pro'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Scanner Einstellungen -->
                <div class="smp-tab-pane" id="scanner">
                    <h2><?php _e('Scanner Einstellungen', 'snack-manager-pro'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Scanner aktivieren', 'snack-manager-pro'); ?></th>
                            <td>
                                <label for="scanner_enabled">
                                    <input type="checkbox" id="scanner_enabled" name="scanner_enabled" value="1" <?php checked($options['scanner_enabled'], '1'); ?> />
                                    <?php _e('Barcode-Scanner-Funktion aktivieren', 'snack-manager-pro'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Scanner-Ton', 'snack-manager-pro'); ?></th>
                            <td>
                                <label for="scanner_sound">
                                    <input type="checkbox" id="scanner_sound" name="scanner_sound" value="1" <?php checked($options['scanner_sound'], '1'); ?> />
                                    <?php _e('Ton bei erfolgreichem Scan abspielen', 'snack-manager-pro'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Auto-Verkauf', 'snack-manager-pro'); ?></th>
                            <td>
                                <label for="auto_complete_sale">
                                    <input type="checkbox" id="auto_complete_sale" name="auto_complete_sale" value="1" <?php checked($options['auto_complete_sale'], '1'); ?> />
                                    <?php _e('Verkauf automatisch nach Scan abschließen', 'snack-manager-pro'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="camera_selection"><?php _e('Kamera-Auswahl', 'snack-manager-pro'); ?></label></th>
                            <td>
                                <select id="camera_selection" name="camera_selection">
                                    <option value="auto" <?php selected($options['camera_selection'], 'auto'); ?>><?php _e('Automatisch', 'snack-manager-pro'); ?></option>
                                    <option value="back" <?php selected($options['camera_selection'], 'back'); ?>><?php _e('Rückkamera', 'snack-manager-pro'); ?></option>
                                    <option value="front" <?php selected($options['camera_selection'], 'front'); ?>><?php _e('Frontkamera', 'snack-manager-pro'); ?></option>
                                </select>
                                <p class="description"><?php _e('Bevorzugte Kamera für Scanner', 'snack-manager-pro'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Voice Control Einstellungen -->
                <div class="smp-tab-pane" id="voice">
                    <h2><?php _e('Sprachsteuerung', 'snack-manager-pro'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Sprachsteuerung aktivieren', 'snack-manager-pro'); ?></th>
                            <td>
                                <label for="voice_enabled">
                                    <input type="checkbox" id="voice_enabled" name="voice_enabled" value="1" <?php checked($options['voice_enabled'], '1'); ?> />
                                    <?php _e('Sprachbefehle erlauben', 'snack-manager-pro'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="voice_language"><?php _e('Sprache', 'snack-manager-pro'); ?></label></th>
                            <td>
                                <select id="voice_language" name="voice_language">
                                    <option value="de-DE" <?php selected($options['voice_language'], 'de-DE'); ?>><?php _e('Deutsch', 'snack-manager-pro'); ?></option>
                                    <option value="en-US" <?php selected($options['voice_language'], 'en-US'); ?>><?php _e('English (US)', 'snack-manager-pro'); ?></option>
                                    <option value="en-GB" <?php selected($options['voice_language'], 'en-GB'); ?>><?php _e('English (UK)', 'snack-manager-pro'); ?></option>
                                    <option value="fr-FR" <?php selected($options['voice_language'], 'fr-FR'); ?>><?php _e('Français', 'snack-manager-pro'); ?></option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Sprachfeedback', 'snack-manager-pro'); ?></th>
                            <td>
                                <label for="voice_feedback">
                                    <input type="checkbox" id="voice_feedback" name="voice_feedback" value="1" <?php checked($options['voice_feedback'], '1'); ?> />
                                    <?php _e('Sprachausgabe für Bestätigungen', 'snack-manager-pro'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="smp-info-box">
                        <h3><?php _e('Verfügbare Sprachbefehle:', 'snack-manager-pro'); ?></h3>
                        <ul>
                            <li><strong>"Verkauf [Anzahl] [Produkt]"</strong> - z.B. "Verkauf 2 Cola"</li>
                            <li><strong>"Auffüllen [Anzahl] [Produkt]"</strong> - z.B. "Auffüllen 10 Chips"</li>
                            <li><strong>"Zeige Bestand"</strong> - Zeigt aktuellen Lagerbestand</li>
                            <li><strong>"Statistik"</strong> - Zeigt Tagesstatistik</li>
                            <li><strong>"Hilfe"</strong> - Zeigt verfügbare Befehle</li>
                        </ul>
                    </div>
                </div>
                
                <!-- Benachrichtigungen -->
                <div class="smp-tab-pane" id="notifications">
                    <h2><?php _e('Benachrichtigungen', 'snack-manager-pro'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('E-Mail-Benachrichtigungen', 'snack-manager-pro'); ?></th>
                            <td>
                                <label for="email_notifications">
                                    <input type="checkbox" id="email_notifications" name="email_notifications" value="1" <?php checked($options['email_notifications'], '1'); ?> />
                                    <?php _e('E-Mail-Benachrichtigungen aktivieren', 'snack-manager-pro'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="notification_email"><?php _e('Benachrichtigungs-E-Mail', 'snack-manager-pro'); ?></label></th>
                            <td>
                                <input type="email" id="notification_email" name="notification_email" value="<?php echo esc_attr($options['notification_email']); ?>" class="regular-text" />
                                <p class="description"><?php _e('E-Mail-Adresse für Benachrichtigungen', 'snack-manager-pro'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Niedrigbestand-Warnung', 'snack-manager-pro'); ?></th>
                            <td>
                                <label for="low_stock_alert">
                                    <input type="checkbox" id="low_stock_alert" name="low_stock_alert" value="1" <?php checked($options['low_stock_alert'], '1'); ?> />
                                    <?php _e('Bei niedrigem Bestand benachrichtigen', 'snack-manager-pro'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Täglicher Report', 'snack-manager-pro'); ?></th>
                            <td>
                                <label for="daily_report">
                                    <input type="checkbox" id="daily_report" name="daily_report" value="1" <?php checked($options['daily_report'], '1'); ?> />
                                    <?php _e('Täglichen Verkaufsbericht per E-Mail senden', 'snack-manager-pro'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Erweiterte Einstellungen -->
                <div class="smp-tab-pane" id="advanced">
                    <h2><?php _e('Erweiterte Einstellungen', 'snack-manager-pro'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Debug-Modus', 'snack-manager-pro'); ?></th>
                            <td>
                                <label for="debug_mode">
                                    <input type="checkbox" id="debug_mode" name="debug_mode" value="1" <?php checked($options['debug_mode'], '1'); ?> />
                                    <?php _e('Erweiterte Fehlerausgabe aktivieren', 'snack-manager-pro'); ?>
                                </label>
                                <p class="description"><?php _e('Nur für Entwicklung und Fehlersuche', 'snack-manager-pro'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="data_retention"><?php _e('Datenspeicherung (Tage)', 'snack-manager-pro'); ?></label></th>
                            <td>
                                <input type="number" id="data_retention" name="data_retention" value="<?php echo esc_attr($options['data_retention']); ?>" class="small-text" min="30" max="3650" />
                                <p class="description"><?php _e('Wie lange sollen Transaktionsdaten gespeichert werden?', 'snack-manager-pro'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('API-Zugriff', 'snack-manager-pro'); ?></th>
                            <td>
                                <label for="api_access">
                                    <input type="checkbox" id="api_access" name="api_access" value="1" <?php checked($options['api_access'], '1'); ?> />
                                    <?php _e('REST API für externe Anwendungen aktivieren', 'snack-manager-pro'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><label for="export_format"><?php _e('Standard Export-Format', 'snack-manager-pro'); ?></label></th>
                            <td>
                                <select id="export_format" name="export_format">
                                    <option value="csv" <?php selected($options['export_format'], 'csv'); ?>>CSV</option>
                                    <option value="excel" <?php selected($options['export_format'], 'excel'); ?>>Excel (XLSX)</option>
                                    <option value="pdf" <?php selected($options['export_format'], 'pdf'); ?>>PDF</option>
                                    <option value="json" <?php selected($options['export_format'], 'json'); ?>>JSON</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Dark Mode Standard', 'snack-manager-pro'); ?></th>
                            <td>
                                <label for="dark_mode_default">
                                    <input type="checkbox" id="dark_mode_default" name="dark_mode_default" value="1" <?php checked($options['dark_mode_default'], '1'); ?> />
                                    <?php _e('Dark Mode als Standard aktivieren', 'snack-manager-pro'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- System-Informationen -->
                <div class="smp-tab-pane" id="system">
                    <h2><?php _e('System-Informationen', 'snack-manager-pro'); ?></h2>
                    
                    <div class="smp-system-info">
                        <table class="widefat striped">
                            <tbody>
                                <tr>
                                    <td><strong><?php _e('Plugin Version:', 'snack-manager-pro'); ?></strong></td>
                                    <td>1.0.0</td>
                                </tr>
                                <tr>
                                    <td><strong><?php _e('WordPress Version:', 'snack-manager-pro'); ?></strong></td>
                                    <td><?php echo get_bloginfo('version'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php _e('PHP Version:', 'snack-manager-pro'); ?></strong></td>
                                    <td><?php echo PHP_VERSION; ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php _e('MySQL Version:', 'snack-manager-pro'); ?></strong></td>
                                    <td><?php global $wpdb; echo $wpdb->db_version(); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php _e('Server Software:', 'snack-manager-pro'); ?></strong></td>
                                    <td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php _e('Max Upload Size:', 'snack-manager-pro'); ?></strong></td>
                                    <td><?php echo size_format(wp_max_upload_size()); ?></td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <h3><?php _e('Datenbank-Status', 'snack-manager-pro'); ?></h3>
                        <form method="post" style="margin-top: 20px;">
                            <button type="submit" name="test_db_connection" class="button button-secondary">
                                <i class="fas fa-database"></i> <?php _e('Datenbank-Tabellen prüfen', 'snack-manager-pro'); ?>
                            </button>
                        </form>
                        
                        <?php
                        // Zeige Tabellen-Status wenn getestet
                        if ($db_test) {
                            global $wpdb;
                            $tables = array(
                                'smp_sessions' => __('Sessions-Tabelle', 'snack-manager-pro'),
                                'smp_products' => __('Produkte-Tabelle', 'snack-manager-pro'),
                                'smp_transactions' => __('Transaktionen-Tabelle', 'snack-manager-pro')
                            );
                            
                            echo '<table class="widefat striped" style="margin-top: 20px;">';
                            echo '<thead><tr><th>' . __('Tabelle', 'snack-manager-pro') . '</th><th>' . __('Status', 'snack-manager-pro') . '</th><th>' . __('Einträge', 'snack-manager-pro') . '</th></tr></thead>';
                            echo '<tbody>';
                            
                            foreach ($tables as $table => $name) {
                                $full_table = $wpdb->prefix . $table;
                                $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table'") == $full_table;
                                $count = $exists ? $wpdb->get_var("SELECT COUNT(*) FROM $full_table") : 0;
                                
                                echo '<tr>';
                                echo '<td>' . $name . '</td>';
                                echo '<td>' . ($exists ? '<span style="color: green;"><i class="fas fa-check-circle"></i> ' . __('Vorhanden', 'snack-manager-pro') . '</span>' : '<span style="color: red;"><i class="fas fa-times-circle"></i> ' . __('Fehlt', 'snack-manager-pro') . '</span>') . '</td>';
                                echo '<td>' . ($exists ? number_format_i18n($count) : '-') . '</td>';
                                echo '</tr>';
                            }
                            
                            echo '</tbody></table>';
                        }
                        ?>
                        
                        <h3><?php _e('Wartung', 'snack-manager-pro'); ?></h3>
                        <div class="smp-maintenance-actions">
                            <button type="button" class="button button-secondary" onclick="if(confirm('<?php _e('Wirklich alle Transaktionsdaten löschen?', 'snack-manager-pro'); ?>')) { alert('<?php _e('Funktion noch nicht implementiert', 'snack-manager-pro'); ?>'); }">
                                <i class="fas fa-trash"></i> <?php _e('Transaktionsdaten löschen', 'snack-manager-pro'); ?>
                            </button>
                            <button type="button" class="button button-secondary" onclick="alert('<?php _e('Funktion noch nicht implementiert', 'snack-manager-pro'); ?>');">
                                <i class="fas fa-download"></i> <?php _e('Backup erstellen', 'snack-manager-pro'); ?>
                            </button>
                            <button type="button" class="button button-secondary" onclick="if(confirm('<?php _e('Plugin wirklich zurücksetzen?', 'snack-manager-pro'); ?>')) { alert('<?php _e('Funktion noch nicht implementiert', 'snack-manager-pro'); ?>'); }">
                                <i class="fas fa-undo"></i> <?php _e('Plugin zurücksetzen', 'snack-manager-pro'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <p class="submit">
                <button type="submit" name="smp_save_settings" class="button button-primary">
                    <i class="fas fa-save"></i> <?php _e('Einstellungen speichern', 'snack-manager-pro'); ?>
                </button>
            </p>
        </form>
    </div>
</div>

<style>
/* Settings Page Styles */
.smp-settings-wrap {
    max-width: 1200px;
    margin: 20px auto;
}

.smp-settings-container {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    margin-top: 20px;
}

/* Tab Navigation */
.smp-settings-tabs {
    background: #f1f1f1;
    border-bottom: 1px solid #ccd0d4;
}

.smp-tab-nav {
    margin: 0;
    padding: 0;
    list-style: none;
    display: flex;
}

.smp-tab-nav li {
    margin: 0;
    padding: 15px 20px;
    cursor: pointer;
    border-right: 1px solid #ccd0d4;
    transition: all 0.3s;
}

.smp-tab-nav li:hover {
    background: #e5e5e5;
}

.smp-tab-nav li.active {
    background: #fff;
    border-bottom: 1px solid #fff;
    font-weight: 600;
}

.smp-tab-nav li i {
    margin-right: 5px;
}

/* Tab Content */
.smp-tab-content {
    padding: 20px;
}

.smp-tab-pane {
    display: none;
}

.smp-tab-pane.active {
    display: block;
}

.smp-tab-pane h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

/* Form Table */
.form-table th {
    width: 250px;
}

/* Info Box */
.smp-info-box {
    background: #f0f8ff;
    border: 1px solid #b8d4f1;
    border-radius: 4px;
    padding: 15px;
    margin: 20px 0;
}

.smp-info-box h3 {
    margin-top: 0;
    color: #0073aa;
}

.smp-info-box ul {
    margin-left: 20px;
}

/* System Info */
.smp-system-info table {
    margin-top: 10px;
}

.smp-system-info td:first-child {
    width: 200px;
}

/* Maintenance Actions */
.smp-maintenance-actions {
    margin-top: 20px;
}

.smp-maintenance-actions button {
    margin-right: 10px;
    margin-bottom: 10px;
}

/* Dark Mode Support */
body.smp-dark-mode .smp-settings-container {
    background: #1e1e1e;
    border-color: #444;
}

body.smp-dark-mode .smp-settings-tabs {
    background: #2a2a2a;
    border-color: #444;
}

body.smp-dark-mode .smp-tab-nav li {
    border-color: #444;
    color: #fff;
}

body.smp-dark-mode .smp-tab-nav li:hover {
    background: #333;
}

body.smp-dark-mode .smp-tab-nav li.active {
    background: #1e1e1e;
}

body.smp-dark-mode .form-table th {
    color: #fff;
}

body.smp-dark-mode .description {
    color: #aaa;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Tab-Wechsel
    $('.smp-tab-nav li').on('click', function() {
        var tabId = $(this).data('tab');
        
        // Aktive Klassen wechseln
        $('.smp-tab-nav li').removeClass('active');
        $(this).addClass('active');
        
        // Tab-Inhalte wechseln
        $('.smp-tab-pane').removeClass('active');
        $('#' + tabId).addClass('active');
    });
    
    // Abhängige Felder
    $('#email_notifications').on('change', function() {
        if ($(this).is(':checked')) {
            $('#notification_email').closest('tr').show();
            $('#low_stock_alert').closest('tr').show();
            $('#daily_report').closest('tr').show();
        } else {
            $('#notification_email').closest('tr').hide();
            $('#low_stock_alert').closest('tr').hide();
            $('#daily_report').closest('tr').hide();
        }
    }).trigger('change');
    
    // Währungssymbol automatisch setzen
    $('#currency').on('change', function() {
        var symbols = {
            'EUR': '€',
            'USD': '$',
            'GBP': '£',
            'CHF': 'Fr.'
        };
        $('#currency_symbol').val(symbols[$(this).val()] || '');
    });
});
</script>