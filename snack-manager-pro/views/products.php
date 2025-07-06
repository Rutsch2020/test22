<?php
/**
 * Snack Manager Pro - Ultra-moderne Produktverwaltung
 * 
 * @package SnackManagerPro
 * @version 1.0.0
 */

// Sicherheitscheck
if (!defined('ABSPATH')) {
    exit;
}

// Hole Produkt-Model
require_once plugin_dir_path(dirname(__FILE__)) . 'includes/product.php';
$product_model = new SMP_Product();

// Verarbeite AJAX-Actions
if (isset($_GET['action']) && $_GET['action'] === 'get_products' && wp_doing_ajax()) {
    wp_send_json($product_model->get_all());
    exit;
}

// Hole alle Produkte für initiale Anzeige
$products = $product_model->get_all();

// Hole eindeutige Kategorien aus den vorhandenen Produkten
$categories = array();
foreach ($products as $product) {
    if (!empty($product->category) && !in_array($product->category, $categories)) {
        $categories[] = $product->category;
    }
}
sort($categories);
?>

<div class="wrap smp-products-wrap">
    <!-- Header mit Actions -->
    <div class="smp-header">
        <div class="smp-header-left">
            <h1 class="smp-page-title">
                <i class="fas fa-box"></i> 
                <span>Produkte</span>
                <span class="smp-badge"><?php echo count($products); ?></span>
            </h1>
        </div>
        <div class="smp-header-right">
            <button class="smp-btn smp-btn-outline" onclick="smpOpenImportModal()">
                <i class="fas fa-file-import"></i> Importieren
            </button>
            <button class="smp-btn smp-btn-outline" onclick="smpExportProducts()">
                <i class="fas fa-file-export"></i> Exportieren
            </button>
            <button class="smp-btn smp-btn-primary" onclick="smpOpenProductModal()">
                <i class="fas fa-plus"></i> Neues Produkt
            </button>
        </div>
    </div>

    <!-- Toolbar mit Suche und Filter -->
    <div class="smp-toolbar">
        <div class="smp-search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="smp-product-search" placeholder="Produkte suchen..." />
            <button class="smp-search-clear" onclick="smpClearSearch()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="smp-filters">
            <select id="smp-category-filter" class="smp-select">
                <option value="">Alle Kategorien</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></option>
                <?php endforeach; ?>
            </select>
            
            <select id="smp-stock-filter" class="smp-select">
                <option value="">Alle Bestände</option>
                <option value="in_stock">Auf Lager</option>
                <option value="low_stock">Niedriger Bestand</option>
                <option value="out_of_stock">Ausverkauft</option>
            </select>
            
            <select id="smp-sort-filter" class="smp-select">
                <option value="name_asc">Name (A-Z)</option>
                <option value="name_desc">Name (Z-A)</option>
                <option value="price_asc">Preis (niedrig-hoch)</option>
                <option value="price_desc">Preis (hoch-niedrig)</option>
                <option value="stock_asc">Bestand (niedrig-hoch)</option>
                <option value="stock_desc">Bestand (hoch-niedrig)</option>
            </select>
        </div>
        
        <div class="smp-view-toggles">
            <button class="smp-view-toggle active" data-view="grid" onclick="smpChangeView('grid')">
                <i class="fas fa-th"></i>
            </button>
            <button class="smp-view-toggle" data-view="list" onclick="smpChangeView('list')">
                <i class="fas fa-list"></i>
            </button>
        </div>
    </div>

    <!-- Bulk Actions Bar (hidden by default) -->
    <div class="smp-bulk-actions" id="smp-bulk-actions" style="display: none;">
        <div class="smp-bulk-info">
            <span class="smp-bulk-count">0</span> Produkte ausgewählt
        </div>
        <div class="smp-bulk-buttons">
            <button class="smp-btn smp-btn-sm" onclick="smpBulkEdit()">
                <i class="fas fa-edit"></i> Bearbeiten
            </button>
            <button class="smp-btn smp-btn-sm" onclick="smpBulkUpdateStock()">
                <i class="fas fa-boxes"></i> Bestand anpassen
            </button>
            <button class="smp-btn smp-btn-sm smp-btn-danger" onclick="smpBulkDelete()">
                <i class="fas fa-trash"></i> Löschen
            </button>
            <button class="smp-btn smp-btn-sm smp-btn-outline" onclick="smpClearSelection()">
                Auswahl aufheben
            </button>
        </div>
    </div>

    <!-- Produkte Container -->
    <div class="smp-products-container smp-view-grid" id="smp-products-container">
        <!-- Loading Skeleton -->
        <div class="smp-loading-skeleton" id="smp-loading" style="display: none;">
            <?php for ($i = 0; $i < 8; $i++): ?>
                <div class="smp-skeleton-card">
                    <div class="smp-skeleton-image"></div>
                    <div class="smp-skeleton-content">
                        <div class="smp-skeleton-line"></div>
                        <div class="smp-skeleton-line short"></div>
                    </div>
                </div>
            <?php endfor; ?>
        </div>

        <!-- Produkt-Grid -->
        <div class="smp-products-grid" id="smp-products-grid">
            <?php foreach ($products as $product): ?>
                <div class="smp-product-card" data-product-id="<?php echo $product->id; ?>" 
                     data-category="<?php echo esc_attr($product->category); ?>"
                     data-stock="<?php echo $product->stock_quantity; ?>"
                     data-name="<?php echo esc_attr(strtolower($product->name)); ?>">
                    
                    <!-- Checkbox für Bulk Actions -->
                    <div class="smp-card-checkbox">
                        <input type="checkbox" class="smp-bulk-checkbox" value="<?php echo $product->id; ?>" />
                    </div>
                    
                    <!-- Produkt-Bild -->
                    <div class="smp-card-image">
                        <?php if ($product->image_url): ?>
                            <img src="<?php echo esc_url($product->image_url); ?>" alt="<?php echo esc_attr($product->name); ?>" />
                        <?php else: ?>
                            <div class="smp-no-image">
                                <i class="fas fa-image"></i>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Quick Actions Overlay -->
                        <div class="smp-card-actions">
                            <button class="smp-action-btn" onclick="smpQuickEdit(<?php echo $product->id; ?>)" title="Schnellbearbeitung">
                                <i class="fas fa-pencil"></i>
                            </button>
                            <button class="smp-action-btn" onclick="smpDuplicateProduct(<?php echo $product->id; ?>)" title="Duplizieren">
                                <i class="fas fa-copy"></i>
                            </button>
                            <button class="smp-action-btn" onclick="smpViewStats(<?php echo $product->id; ?>)" title="Statistiken">
                                <i class="fas fa-chart-line"></i>
                            </button>
                        </div>
                        
                        <!-- Status Badge -->
                        <?php if ($product->stock_quantity <= 0): ?>
                            <span class="smp-badge smp-badge-danger">Ausverkauft</span>
                        <?php elseif ($product->stock_quantity <= $product->stock_min_quantity): ?>
                            <span class="smp-badge smp-badge-warning">Niedriger Bestand</span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Produkt-Info -->
                    <div class="smp-card-content">
                        <div class="smp-card-header">
                            <h3 class="smp-card-title"><?php echo esc_html($product->name); ?></h3>
                            <div class="smp-card-category">
                                <i class="fas fa-tag"></i> <?php echo esc_html($product->category ?: 'Unkategorisiert'); ?>
                            </div>
                        </div>
                        
                        <div class="smp-card-details">
                            <div class="smp-detail-item">
                                <span class="smp-detail-label">Preis</span>
                                <span class="smp-detail-value smp-price"><?php echo number_format($product->price, 2, ',', '.'); ?> €</span>
                            </div>
                            <div class="smp-detail-item">
                                <span class="smp-detail-label">Bestand</span>
                                <span class="smp-detail-value">
                                    <span class="smp-stock-value"><?php echo $product->stock_quantity; ?></span>
                                    <button class="smp-stock-adjust" onclick="smpAdjustStock(<?php echo $product->id; ?>, <?php echo $product->stock_quantity; ?>)">
                                        <i class="fas fa-plus-minus"></i>
                                    </button>
                                </span>
                            </div>
                        </div>
                        
                        <div class="smp-card-meta">
                            <div class="smp-barcode">
                                <i class="fas fa-barcode"></i> <?php echo esc_html($product->barcode ?: 'Kein Barcode'); ?>
                            </div>
                        </div>
                        
                        <div class="smp-card-footer">
                            <button class="smp-btn smp-btn-sm smp-btn-outline" onclick="smpEditProduct(<?php echo $product->id; ?>)">
                                <i class="fas fa-edit"></i> Bearbeiten
                            </button>
                            <button class="smp-btn smp-btn-sm smp-btn-danger-outline" onclick="smpDeleteProduct(<?php echo $product->id; ?>, '<?php echo esc_js($product->name); ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Empty State -->
        <div class="smp-empty-state" id="smp-empty-state" style="<?php echo empty($products) ? '' : 'display: none;'; ?>">
            <div class="smp-empty-icon">
                <i class="fas fa-box-open"></i>
            </div>
            <h2>Keine Produkte gefunden</h2>
            <p>Starten Sie mit dem Hinzufügen Ihres ersten Produkts</p>
            <button class="smp-btn smp-btn-primary" onclick="smpOpenProductModal()">
                <i class="fas fa-plus"></i> Erstes Produkt anlegen
            </button>
        </div>
    </div>
</div>

<!-- Produkt Modal -->
<div class="smp-modal" id="smp-product-modal">
    <div class="smp-modal-content smp-modal-lg">
        <div class="smp-modal-header">
            <h2 id="smp-modal-title">Neues Produkt</h2>
            <button class="smp-modal-close" onclick="smpCloseProductModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="smp-product-form" class="smp-form">
            <input type="hidden" id="product_id" name="product_id" value="" />
            
            <div class="smp-modal-body">
                <div class="smp-form-grid">
                    <!-- Linke Spalte -->
                    <div class="smp-form-column">
                        <!-- Produktbild Upload -->
                        <div class="smp-form-group">
                            <label>Produktbild</label>
                            <div class="smp-image-upload" id="smp-image-upload">
                                <input type="hidden" id="product_image" name="product_image" />
                                <div class="smp-upload-area" onclick="smpSelectImage()">
                                    <div class="smp-upload-placeholder" id="smp-upload-placeholder">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <p>Klicken zum Hochladen oder Drag & Drop</p>
                                        <span>JPG, PNG oder GIF (max. 2MB)</span>
                                    </div>
                                    <img class="smp-upload-preview" id="smp-upload-preview" style="display: none;" />
                                    <button type="button" class="smp-remove-image" onclick="smpRemoveImage(event)" style="display: none;">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Produktname -->
                        <div class="smp-form-group">
                            <label for="product_name">Produktname *</label>
                            <input type="text" id="product_name" name="product_name" class="smp-input" required 
                                   placeholder="z.B. Coca Cola 0,5l" />
                            <span class="smp-field-error"></span>
                        </div>

                        <!-- Kategorie -->
                        <div class="smp-form-group">
                            <label for="product_category">Kategorie</label>
                            <div class="smp-select-wrapper">
                                <select id="product_category" name="product_category" class="smp-select">
                                    <option value="">Kategorie wählen...</option>
                                    <option value="Getränke">Getränke</option>
                                    <option value="Snacks">Snacks</option>
                                    <option value="Süßigkeiten">Süßigkeiten</option>
                                    <option value="Eis">Eis</option>
                                    <option value="Sonstiges">Sonstiges</option>
                                </select>
                                <button type="button" class="smp-add-category" onclick="smpAddNewCategory()">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Beschreibung -->
                        <div class="smp-form-group">
                            <label for="product_description">Beschreibung</label>
                            <textarea id="product_description" name="product_description" class="smp-textarea" rows="3"
                                      placeholder="Optionale Produktbeschreibung..."></textarea>
                        </div>
                    </div>

                    <!-- Rechte Spalte -->
                    <div class="smp-form-column">
                        <!-- Barcode -->
                        <div class="smp-form-group">
                            <label for="product_barcode">Barcode</label>
                            <div class="smp-input-group">
                                <input type="text" id="product_barcode" name="product_barcode" class="smp-input" 
                                       placeholder="EAN/Barcode eingeben" />
                                <button type="button" class="smp-input-addon" onclick="smpScanBarcode()">
                                    <i class="fas fa-barcode"></i> Scannen
                                </button>
                            </div>
                            <button type="button" class="smp-link-btn" onclick="smpGenerateBarcode()">
                                <i class="fas fa-magic"></i> Barcode generieren
                            </button>
                        </div>

                        <!-- Preis -->
                        <div class="smp-form-group">
                            <label for="product_price">Verkaufspreis * <span class="smp-label-hint">(inkl. MwSt.)</span></label>
                            <div class="smp-input-group">
                                <input type="number" id="product_price" name="product_price" class="smp-input" 
                                       step="0.01" min="0" required placeholder="0,00" />
                                <span class="smp-input-addon">€</span>
                            </div>
                            <div class="smp-price-calculator">
                                <span>Netto: <strong id="price-netto">0,00 €</strong></span>
                                <span>MwSt: <strong id="price-tax">0,00 €</strong></span>
                            </div>
                        </div>

                        <!-- Bestand -->
                        <div class="smp-form-row">
                            <div class="smp-form-group">
                                <label for="product_stock">Aktueller Bestand *</label>
                                <input type="number" id="product_stock" name="product_stock" class="smp-input" 
                                       min="0" required value="0" />
                            </div>
                            <div class="smp-form-group">
                                <label for="product_min_stock">Min. Bestand</label>
                                <input type="number" id="product_min_stock" name="product_min_stock" class="smp-input" 
                                       min="0" value="10" />
                                <span class="smp-field-hint">Warnung bei Unterschreitung</span>
                            </div>
                        </div>

                        <!-- Status -->
                        <div class="smp-form-group">
                            <label>Status</label>
                            <div class="smp-radio-group">
                                <label class="smp-radio">
                                    <input type="radio" name="product_status" value="active" checked />
                                    <span class="smp-radio-label">
                                        <i class="fas fa-check-circle"></i> Aktiv
                                    </span>
                                </label>
                                <label class="smp-radio">
                                    <input type="radio" name="product_status" value="inactive" />
                                    <span class="smp-radio-label">
                                        <i class="fas fa-pause-circle"></i> Inaktiv
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="smp-modal-footer">
                <button type="button" class="smp-btn smp-btn-outline" onclick="smpCloseProductModal()">
                    Abbrechen
                </button>
                <button type="submit" class="smp-btn smp-btn-primary" id="smp-save-btn">
                    <i class="fas fa-save"></i> <span>Produkt speichern</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Quick Edit Modal -->
<div class="smp-modal" id="smp-quick-edit-modal">
    <div class="smp-modal-content">
        <div class="smp-modal-header">
            <h2>Schnellbearbeitung</h2>
            <button class="smp-modal-close" onclick="smpCloseQuickEdit()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="smp-modal-body">
            <form id="smp-quick-edit-form">
                <input type="hidden" id="quick_edit_id" />
                <div class="smp-form-group">
                    <label>Produktname</label>
                    <input type="text" id="quick_edit_name" class="smp-input" />
                </div>
                <div class="smp-form-row">
                    <div class="smp-form-group">
                        <label>Preis</label>
                        <input type="number" id="quick_edit_price" class="smp-input" step="0.01" />
                    </div>
                    <div class="smp-form-group">
                        <label>Bestand</label>
                        <input type="number" id="quick_edit_stock" class="smp-input" />
                    </div>
                </div>
            </form>
        </div>
        <div class="smp-modal-footer">
            <button type="button" class="smp-btn smp-btn-outline" onclick="smpCloseQuickEdit()">
                Abbrechen
            </button>
            <button type="button" class="smp-btn smp-btn-primary" onclick="smpSaveQuickEdit()">
                <i class="fas fa-save"></i> Speichern
            </button>
        </div>
    </div>
</div>

<!-- Stock Adjust Modal -->
<div class="smp-modal" id="smp-stock-modal">
    <div class="smp-modal-content smp-modal-sm">
        <div class="smp-modal-header">
            <h2>Bestand anpassen</h2>
            <button class="smp-modal-close" onclick="smpCloseStockModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="smp-modal-body">
            <div class="smp-stock-current">
                Aktueller Bestand: <strong id="current-stock">0</strong>
            </div>
            <div class="smp-form-group">
                <label>Anpassung</label>
                <div class="smp-stock-buttons">
                    <button type="button" class="smp-stock-btn" onclick="smpStockChange(-10)">-10</button>
                    <button type="button" class="smp-stock-btn" onclick="smpStockChange(-5)">-5</button>
                    <button type="button" class="smp-stock-btn" onclick="smpStockChange(-1)">-1</button>
                    <input type="number" id="stock-adjust-value" class="smp-input" value="0" />
                    <button type="button" class="smp-stock-btn" onclick="smpStockChange(1)">+1</button>
                    <button type="button" class="smp-stock-btn" onclick="smpStockChange(5)">+5</button>
                    <button type="button" class="smp-stock-btn" onclick="smpStockChange(10)">+10</button>
                </div>
            </div>
            <div class="smp-stock-new">
                Neuer Bestand: <strong id="new-stock">0</strong>
            </div>
        </div>
        <div class="smp-modal-footer">
            <button type="button" class="smp-btn smp-btn-outline" onclick="smpCloseStockModal()">
                Abbrechen
            </button>
            <button type="button" class="smp-btn smp-btn-primary" onclick="smpSaveStockAdjust()">
                <i class="fas fa-save"></i> Bestand aktualisieren
            </button>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="smp-modal" id="smp-import-modal">
    <div class="smp-modal-content">
        <div class="smp-modal-header">
            <h2>Produkte importieren</h2>
            <button class="smp-modal-close" onclick="smpCloseImportModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="smp-modal-body">
            <div class="smp-import-options">
                <label class="smp-import-option">
                    <input type="radio" name="import_type" value="csv" checked />
                    <div class="smp-import-card">
                        <i class="fas fa-file-csv"></i>
                        <h3>CSV Import</h3>
                        <p>Importieren Sie Produkte aus einer CSV-Datei</p>
                    </div>
                </label>
                <label class="smp-import-option">
                    <input type="radio" name="import_type" value="excel" />
                    <div class="smp-import-card">
                        <i class="fas fa-file-excel"></i>
                        <h3>Excel Import</h3>
                        <p>Importieren Sie Produkte aus einer Excel-Datei</p>
                    </div>
                </label>
            </div>
            
            <div class="smp-upload-zone" id="import-upload-zone">
                <i class="fas fa-cloud-upload-alt"></i>
                <p>Datei hier ablegen oder klicken zum Auswählen</p>
                <input type="file" id="import-file" accept=".csv,.xlsx,.xls" style="display: none;" />
            </div>
            
            <div class="smp-import-preview" id="import-preview" style="display: none;">
                <h3>Vorschau</h3>
                <div id="import-preview-content"></div>
            </div>
        </div>
        <div class="smp-modal-footer">
            <button type="button" class="smp-btn smp-btn-outline" onclick="smpCloseImportModal()">
                Abbrechen
            </button>
            <button type="button" class="smp-btn smp-btn-primary" onclick="smpStartImport()" disabled id="start-import-btn">
                <i class="fas fa-upload"></i> Import starten
            </button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="smp-toast-container" id="smp-toast-container"></div>

<!-- Styles -->
<style>
/* Reset & Base */
.smp-products-wrap * {
    box-sizing: border-box;
}

.smp-products-wrap {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    color: #1a1a1a;
    background: #f8f9fa;
    padding: 20px;
    margin: -20px -20px -20px -2px;
    min-height: 100vh;
}

/* Header */
.smp-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding: 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.smp-header-left {
    display: flex;
    align-items: center;
    gap: 15px;
}

.smp-page-title {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0;
    font-size: 28px;
    font-weight: 700;
    color: #1a1a1a;
}

.smp-badge {
    background: #e3f2fd;
    color: #1976d2;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
}

.smp-badge-danger {
    background: #ffebee;
    color: #d32f2f;
}

.smp-badge-warning {
    background: #fff3e0;
    color: #f57c00;
}

.smp-header-right {
    display: flex;
    gap: 10px;
}

/* Buttons */
.smp-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    outline: none;
}

.smp-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.smp-btn:active {
    transform: translateY(0);
}

.smp-btn-primary {
    background: #1976d2;
    color: white;
}

.smp-btn-primary:hover {
    background: #1565c0;
}

.smp-btn-outline {
    background: white;
    color: #666;
    border: 1px solid #e0e0e0;
}

.smp-btn-outline:hover {
    border-color: #1976d2;
    color: #1976d2;
}

.smp-btn-danger-outline {
    background: white;
    color: #d32f2f;
    border: 1px solid #ffcdd2;
}

.smp-btn-danger-outline:hover {
    background: #ffebee;
    border-color: #d32f2f;
}

.smp-btn-sm {
    padding: 6px 12px;
    font-size: 13px;
}

/* Toolbar */
.smp-toolbar {
    display: flex;
    gap: 20px;
    align-items: center;
    margin-bottom: 20px;
    padding: 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    flex-wrap: wrap;
}

.smp-search-box {
    position: relative;
    flex: 1;
    min-width: 300px;
}

.smp-search-box i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
}

.smp-search-box input {
    width: 100%;
    padding: 12px 40px 12px 40px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s;
}

.smp-search-box input:focus {
    outline: none;
    border-color: #1976d2;
    box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
}

.smp-search-clear {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #999;
    cursor: pointer;
    padding: 5px;
    display: none;
}

.smp-search-box input:not(:placeholder-shown) + .smp-search-clear {
    display: block;
}

.smp-filters {
    display: flex;
    gap: 10px;
}

.smp-select {
    padding: 10px 15px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    background: white;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
}

.smp-select:hover {
    border-color: #1976d2;
}

.smp-select:focus {
    outline: none;
    border-color: #1976d2;
    box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
}

.smp-view-toggles {
    display: flex;
    gap: 5px;
    background: #f5f5f5;
    padding: 4px;
    border-radius: 8px;
}

.smp-view-toggle {
    padding: 8px 12px;
    background: none;
    border: none;
    color: #666;
    cursor: pointer;
    border-radius: 6px;
    transition: all 0.2s;
}

.smp-view-toggle.active {
    background: white;
    color: #1976d2;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Bulk Actions */
.smp-bulk-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: #1976d2;
    color: white;
    border-radius: 12px;
    margin-bottom: 20px;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.smp-bulk-info {
    font-weight: 600;
}

.smp-bulk-count {
    font-size: 18px;
}

.smp-bulk-buttons {
    display: flex;
    gap: 10px;
}

/* Products Grid */
.smp-products-container {
    min-height: 400px;
}

.smp-products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    animation: fadeIn 0.5s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

/* List View */
.smp-view-list .smp-products-grid {
    grid-template-columns: 1fr;
}

.smp-view-list .smp-product-card {
    display: flex;
    align-items: center;
    padding: 20px;
}

.smp-view-list .smp-card-image {
    width: 80px;
    height: 80px;
    margin-right: 20px;
    margin-bottom: 0;
}

.smp-view-list .smp-card-content {
    flex: 1;
}

.smp-view-list .smp-card-header,
.smp-view-list .smp-card-details,
.smp-view-list .smp-card-footer {
    display: flex;
    align-items: center;
    gap: 20px;
}

/* Product Card */
.smp-product-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    transition: all 0.3s;
    position: relative;
}

.smp-product-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
}

.smp-card-checkbox {
    position: absolute;
    top: 15px;
    left: 15px;
    z-index: 2;
    opacity: 0;
    transition: opacity 0.2s;
}

.smp-product-card:hover .smp-card-checkbox,
.smp-card-checkbox input:checked {
    opacity: 1;
}

.smp-card-checkbox input {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.smp-card-image {
    position: relative;
    height: 200px;
    background: #f5f5f5;
    overflow: hidden;
}

.smp-card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.smp-no-image {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #bbb;
    font-size: 48px;
}

.smp-card-actions {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    background: rgba(0,0,0,0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    opacity: 0;
    transition: opacity 0.2s;
}

.smp-product-card:hover .smp-card-actions {
    opacity: 1;
}

.smp-action-btn {
    width: 40px;
    height: 40px;
    background: white;
    border: none;
    border-radius: 50%;
    color: #333;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.smp-action-btn:hover {
    transform: scale(1.1);
    background: #1976d2;
    color: white;
}

.smp-card-content {
    padding: 20px;
}

.smp-card-header {
    margin-bottom: 15px;
}

.smp-card-title {
    margin: 0 0 5px 0;
    font-size: 18px;
    font-weight: 600;
    color: #1a1a1a;
}

.smp-card-category {
    color: #999;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 5px;
}

.smp-card-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}

.smp-detail-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.smp-detail-label {
    font-size: 12px;
    color: #999;
    text-transform: uppercase;
}

.smp-detail-value {
    font-size: 16px;
    font-weight: 600;
    color: #333;
    display: flex;
    align-items: center;
    gap: 8px;
}

.smp-price {
    color: #1976d2;
}

.smp-stock-adjust {
    background: none;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    padding: 2px 6px;
    cursor: pointer;
    color: #666;
    font-size: 12px;
    transition: all 0.2s;
}

.smp-stock-adjust:hover {
    border-color: #1976d2;
    color: #1976d2;
}

.smp-card-meta {
    padding-top: 15px;
    border-top: 1px solid #f0f0f0;
    margin-bottom: 15px;
}

.smp-barcode {
    color: #666;
    font-size: 13px;
    font-family: monospace;
    display: flex;
    align-items: center;
    gap: 5px;
}

.smp-card-footer {
    display: flex;
    gap: 10px;
}

/* Loading Skeleton */
.smp-loading-skeleton {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.smp-skeleton-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.smp-skeleton-image {
    height: 200px;
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
}

.smp-skeleton-content {
    padding: 20px;
}

.smp-skeleton-line {
    height: 20px;
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
    border-radius: 4px;
    margin-bottom: 10px;
}

.smp-skeleton-line.short {
    width: 60%;
}

@keyframes loading {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}

/* Empty State */
.smp-empty-state {
    text-align: center;
    padding: 80px 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.smp-empty-icon {
    font-size: 80px;
    color: #e0e0e0;
    margin-bottom: 20px;
}

.smp-empty-state h2 {
    margin: 0 0 10px 0;
    font-size: 24px;
    color: #333;
}

.smp-empty-state p {
    color: #999;
    margin-bottom: 30px;
}

/* Modal */
.smp-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    animation: fadeIn 0.2s;
}

.smp-modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.smp-modal-content {
    background: white;
    border-radius: 16px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow: hidden;
    animation: slideUp 0.3s;
}

.smp-modal-lg {
    max-width: 900px;
}

.smp-modal-sm {
    max-width: 400px;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.smp-modal-header {
    padding: 24px;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.smp-modal-header h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
}

.smp-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    color: #999;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    transition: all 0.2s;
}

.smp-modal-close:hover {
    background: #f5f5f5;
    color: #333;
}

.smp-modal-body {
    padding: 24px;
    overflow-y: auto;
    max-height: calc(90vh - 180px);
}

.smp-modal-footer {
    padding: 20px 24px;
    border-top: 1px solid #f0f0f0;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Form Styles */
.smp-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

@media (max-width: 768px) {
    .smp-form-grid {
        grid-template-columns: 1fr;
    }
}

.smp-form-group {
    margin-bottom: 20px;
}

.smp-form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
    font-size: 14px;
}

.smp-label-hint {
    font-weight: normal;
    color: #999;
    font-size: 13px;
}

.smp-input,
.smp-textarea,
.smp-select {
    width: 100%;
    padding: 10px 15px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s;
    background: white;
}

.smp-input:focus,
.smp-textarea:focus,
.smp-select:focus {
    outline: none;
    border-color: #1976d2;
    box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
}

.smp-textarea {
    resize: vertical;
    min-height: 80px;
}

.smp-input-group {
    display: flex;
    align-items: center;
}

.smp-input-group .smp-input {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
}

.smp-input-addon {
    padding: 10px 15px;
    background: #f5f5f5;
    border: 1px solid #e0e0e0;
    border-left: none;
    border-radius: 0 8px 8px 0;
    color: #666;
    font-size: 14px;
}

button.smp-input-addon {
    cursor: pointer;
    transition: all 0.2s;
}

button.smp-input-addon:hover {
    background: #e0e0e0;
}

.smp-field-error {
    display: none;
    color: #d32f2f;
    font-size: 13px;
    margin-top: 5px;
}

.smp-field-hint {
    display: block;
    color: #999;
    font-size: 13px;
    margin-top: 5px;
}

/* Image Upload */
.smp-image-upload {
    border: 2px dashed #e0e0e0;
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.2s;
}

.smp-upload-area {
    position: relative;
    height: 200px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.smp-upload-placeholder {
    text-align: center;
    color: #999;
}

.smp-upload-placeholder i {
    font-size: 48px;
    margin-bottom: 10px;
    display: block;
}

.smp-upload-placeholder p {
    margin: 5px 0;
    font-weight: 600;
    color: #666;
}

.smp-upload-placeholder span {
    font-size: 13px;
    color: #999;
}

.smp-image-upload:hover {
    border-color: #1976d2;
}

.smp-image-upload:hover .smp-upload-placeholder {
    color: #1976d2;
}

.smp-upload-preview {
    width: 100%;
    height: 200px;
    object-fit: cover;
}

.smp-remove-image {
    position: absolute;
    top: 10px;
    right: 10px;
    background: rgba(0,0,0,0.7);
    color: white;
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.smp-remove-image:hover {
    background: #d32f2f;
}

/* Price Calculator */
.smp-price-calculator {
    display: flex;
    gap: 20px;
    margin-top: 10px;
    padding: 10px;
    background: #f5f5f5;
    border-radius: 6px;
    font-size: 13px;
}

/* Radio Group */
.smp-radio-group {
    display: flex;
    gap: 15px;
}

.smp-radio {
    display: flex;
    align-items: center;
    cursor: pointer;
}

.smp-radio input {
    display: none;
}

.smp-radio-label {
    padding: 8px 16px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 6px;
}

.smp-radio input:checked + .smp-radio-label {
    background: #e3f2fd;
    border-color: #1976d2;
    color: #1976d2;
}

/* Form Row */
.smp-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

/* Link Button */
.smp-link-btn {
    background: none;
    border: none;
    color: #1976d2;
    font-size: 13px;
    cursor: pointer;
    padding: 5px 0;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.2s;
}

.smp-link-btn:hover {
    text-decoration: underline;
}

/* Select with Add Button */
.smp-select-wrapper {
    display: flex;
    gap: 10px;
}

.smp-select-wrapper .smp-select {
    flex: 1;
}

.smp-add-category {
    padding: 10px 15px;
    background: #f5f5f5;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.smp-add-category:hover {
    background: #e0e0e0;
}

/* Stock Modal Specific */
.smp-stock-current,
.smp-stock-new {
    text-align: center;
    padding: 15px;
    background: #f5f5f5;
    border-radius: 8px;
    margin: 10px 0;
}

.smp-stock-current strong,
.smp-stock-new strong {
    font-size: 24px;
    color: #1976d2;
}

.smp-stock-buttons {
    display: flex;
    gap: 5px;
    align-items: center;
}

.smp-stock-btn {
    flex: 1;
    padding: 10px;
    background: #f5f5f5;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s;
}

.smp-stock-btn:hover {
    background: #e0e0e0;
}

#stock-adjust-value {
    max-width: 80px;
    text-align: center;
}

/* Import Modal */
.smp-import-options {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 20px;
}

.smp-import-option {
    cursor: pointer;
}

.smp-import-option input {
    display: none;
}

.smp-import-card {
    padding: 20px;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    text-align: center;
    transition: all 0.2s;
}

.smp-import-card i {
    font-size: 36px;
    color: #999;
    margin-bottom: 10px;
}

.smp-import-card h3 {
    margin: 10px 0 5px 0;
    font-size: 16px;
}

.smp-import-card p {
    margin: 0;
    color: #999;
    font-size: 13px;
}

.smp-import-option input:checked + .smp-import-card {
    background: #e3f2fd;
    border-color: #1976d2;
}

.smp-import-option input:checked + .smp-import-card i {
    color: #1976d2;
}

.smp-upload-zone {
    border: 2px dashed #e0e0e0;
    border-radius: 12px;
    padding: 40px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
}

.smp-upload-zone:hover {
    border-color: #1976d2;
    background: #f8f9fa;
}

.smp-upload-zone i {
    font-size: 48px;
    color: #999;
    margin-bottom: 10px;
    display: block;
}

.smp-upload-zone p {
    margin: 0;
    color: #666;
    font-weight: 600;
}

.smp-import-preview {
    margin-top: 20px;
    padding: 15px;
    background: #f5f5f5;
    border-radius: 8px;
}

.smp-import-preview h3 {
    margin: 0 0 10px 0;
    font-size: 16px;
}

/* Toast Notifications */
.smp-toast-container {
    position: fixed;
    top: 32px;
    right: 20px;
    z-index: 10000;
}

.smp-toast {
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    padding: 16px 20px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 300px;
    animation: slideInRight 0.3s ease;
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.smp-toast-success {
    border-left: 4px solid #4caf50;
}

.smp-toast-error {
    border-left: 4px solid #f44336;
}

.smp-toast-warning {
    border-left: 4px solid #ff9800;
}

.smp-toast-info {
    border-left: 4px solid #2196f3;
}

.smp-toast i {
    font-size: 20px;
}

.smp-toast-success i {
    color: #4caf50;
}

.smp-toast-error i {
    color: #f44336;
}

.smp-toast-warning i {
    color: #ff9800;
}

.smp-toast-info i {
    color: #2196f3;
}

.smp-toast-content {
    flex: 1;
}

.smp-toast-title {
    font-weight: 600;
    margin-bottom: 2px;
}

.smp-toast-message {
    font-size: 14px;
    color: #666;
}

/* Dark Mode */
body.smp-dark-mode .smp-products-wrap {
    background: #121212;
    color: #e0e0e0;
}

body.smp-dark-mode .smp-header,
body.smp-dark-mode .smp-toolbar,
body.smp-dark-mode .smp-product-card,
body.smp-dark-mode .smp-modal-content,
body.smp-dark-mode .smp-empty-state {
    background: #1e1e1e;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

body.smp-dark-mode .smp-page-title {
    color: #e0e0e0;
}

body.smp-dark-mode .smp-input,
body.smp-dark-mode .smp-textarea,
body.smp-dark-mode .smp-select {
    background: #2a2a2a;
    border-color: #444;
    color: #e0e0e0;
}

body.smp-dark-mode .smp-btn-outline {
    background: #2a2a2a;
    border-color: #444;
    color: #e0e0e0;
}

body.smp-dark-mode .smp-card-title,
body.smp-dark-mode .smp-modal-header h2 {
    color: #e0e0e0;
}

body.smp-dark-mode .smp-detail-value,
body.smp-dark-mode .smp-form-group label {
    color: #e0e0e0;
}

/* Responsive */
@media (max-width: 1024px) {
    .smp-products-grid {
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    }
}

@media (max-width: 768px) {
    .smp-header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .smp-toolbar {
        flex-direction: column;
        gap: 15px;
    }
    
    .smp-search-box {
        min-width: 100%;
    }
    
    .smp-filters {
        width: 100%;
        flex-wrap: wrap;
    }
    
    .smp-select {
        flex: 1;
        min-width: calc(50% - 5px);
    }
}

@media (max-width: 480px) {
    .smp-products-grid {
        grid-template-columns: 1fr;
    }
    
    .smp-bulk-actions {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .smp-bulk-buttons {
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .smp-modal-content {
        margin: 10px;
    }
}
</style>

<!-- JavaScript -->
<script>
jQuery(document).ready(function($) {
    // Globale Variablen
    let selectedProducts = [];
    let currentProductId = null;
    let currentStockProductId = null;
    let currentStock = 0;
    
    // AJAX URL und Nonce aus WordPress
    const ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    const smp_nonce = '<?php echo wp_create_nonce('smp_ajax_nonce'); ?>';
    
    // Toast Notification Funktion
    function showToast(title, message, type = 'success') {
        const toast = $(`
            <div class="smp-toast smp-toast-${type}">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
                <div class="smp-toast-content">
                    <div class="smp-toast-title">${title}</div>
                    <div class="smp-toast-message">${message}</div>
                </div>
            </div>
        `);
        
        $('#smp-toast-container').append(toast);
        
        setTimeout(() => {
            toast.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Live-Suche
    $('#smp-product-search').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        filterProducts();
    });
    
    // Filter-Funktionen
    $('#smp-category-filter, #smp-stock-filter, #smp-sort-filter').on('change', function() {
        filterProducts();
    });
    
    function filterProducts() {
        const searchTerm = $('#smp-product-search').val().toLowerCase();
        const category = $('#smp-category-filter').val();
        const stockFilter = $('#smp-stock-filter').val();
        const sortBy = $('#smp-sort-filter').val();
        
        let visibleCards = [];
        
        // Filtern
        $('.smp-product-card').each(function() {
            const $card = $(this);
            const name = $card.data('name');
            const cardCategory = $card.data('category');
            const stock = parseInt($card.data('stock'));
            
            let show = true;
            
            // Suchfilter
            if (searchTerm && !name.includes(searchTerm)) {
                show = false;
            }
            
            // Kategoriefilter
            if (category && cardCategory !== category) {
                show = false;
            }
            
            // Bestandsfilter
            if (stockFilter) {
                if (stockFilter === 'in_stock' && stock <= 0) show = false;
                if (stockFilter === 'low_stock' && (stock <= 0 || stock > 10)) show = false;
                if (stockFilter === 'out_of_stock' && stock > 0) show = false;
            }
            
            if (show) {
                $card.show();
                visibleCards.push($card);
            } else {
                $card.hide();
            }
        });
        
        // Sortieren
        if (sortBy && visibleCards.length > 0) {
            visibleCards.sort(function(a, b) {
                switch(sortBy) {
                    case 'name_asc':
                        return a.data('name').localeCompare(b.data('name'));
                    case 'name_desc':
                        return b.data('name').localeCompare(a.data('name'));
                    case 'price_asc':
                        return parseFloat(a.find('.smp-price').text()) - parseFloat(b.find('.smp-price').text());
                    case 'price_desc':
                        return parseFloat(b.find('.smp-price').text()) - parseFloat(a.find('.smp-price').text());
                    case 'stock_asc':
                        return a.data('stock') - b.data('stock');
                    case 'stock_desc':
                        return b.data('stock') - a.data('stock');
                }
                return 0;
            });
            
            // Neu anordnen
            const $container = $('#smp-products-grid');
            visibleCards.forEach(function($card) {
                $container.append($card);
            });
        }
        
        // Empty State anzeigen/verbergen
        if (visibleCards.length === 0) {
            $('#smp-empty-state').show();
        } else {
            $('#smp-empty-state').hide();
        }
    }
    
    // View Toggle
    window.smpChangeView = function(view) {
        $('.smp-view-toggle').removeClass('active');
        $(`.smp-view-toggle[data-view="${view}"]`).addClass('active');
        
        if (view === 'grid') {
            $('.smp-products-container').removeClass('smp-view-list').addClass('smp-view-grid');
        } else {
            $('.smp-products-container').removeClass('smp-view-grid').addClass('smp-view-list');
        }
    };
    
    // Bulk Actions
    $(document).on('change', '.smp-bulk-checkbox', function() {
        const productId = $(this).val();
        
        if ($(this).is(':checked')) {
            selectedProducts.push(productId);
        } else {
            selectedProducts = selectedProducts.filter(id => id !== productId);
        }
        
        updateBulkActions();
    });
    
    function updateBulkActions() {
        if (selectedProducts.length > 0) {
            $('#smp-bulk-actions').slideDown();
            $('.smp-bulk-count').text(selectedProducts.length);
        } else {
            $('#smp-bulk-actions').slideUp();
        }
    }
    
    window.smpClearSelection = function() {
        selectedProducts = [];
        $('.smp-bulk-checkbox').prop('checked', false);
        updateBulkActions();
    };
    
    window.smpBulkEdit = function() {
        showToast('Bulk Edit', `${selectedProducts.length} Produkte zum Bearbeiten ausgewählt`, 'info');
        // Hier würde die Bulk-Edit Funktionalität implementiert werden
    };
    
    window.smpBulkUpdateStock = function() {
        showToast('Bestand anpassen', `Bestand für ${selectedProducts.length} Produkte anpassen`, 'info');
        // Hier würde die Bulk-Stock-Update Funktionalität implementiert werden
    };
    
    window.smpBulkDelete = function() {
        if (confirm(`Wirklich ${selectedProducts.length} Produkte löschen?`)) {
            // AJAX-Call zum Löschen
            showToast('Produkte gelöscht', `${selectedProducts.length} Produkte wurden gelöscht`, 'success');
            smpClearSelection();
        }
    };
    
    // Produkt Modal
    window.smpOpenProductModal = function(productId = null) {
        currentProductId = productId;
        
        if (productId) {
            $('#smp-modal-title').text('Produkt bearbeiten');
            $('#smp-save-btn span').text('Änderungen speichern');
            // Hier würden die Produktdaten geladen werden
        } else {
            $('#smp-modal-title').text('Neues Produkt');
            $('#smp-save-btn span').text('Produkt speichern');
            $('#smp-product-form')[0].reset();
            $('#smp-upload-preview').hide();
            $('#smp-upload-placeholder').show();
            $('.smp-remove-image').hide();
        }
        
        $('#smp-product-modal').addClass('active');
    };
    
    window.smpCloseProductModal = function() {
        $('#smp-product-modal').removeClass('active');
        currentProductId = null;
    };
    
    window.smpEditProduct = function(productId) {
        smpOpenProductModal(productId);
        
        // Produkt-Daten laden
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'smp_get_product',
                product_id: productId,
                nonce: smp_nonce
            },
            success: function(response) {
                if (response.success) {
                    const product = response.data;
                    $('#product_id').val(product.id);
                    $('#product_name').val(product.name);
                    $('#product_category').val(product.category);
                    $('#product_description').val(product.description);
                    $('#product_barcode').val(product.barcode);
                    $('#product_price').val(product.price);
                    $('#product_stock').val(product.stock_quantity);
                    $('#product_min_stock').val(product.stock_min_quantity);
                    $(`input[name="product_status"][value="${product.status}"]`).prop('checked', true);
                    
                    if (product.image_url) {
                        $('#product_image').val(product.image_url);
                        $('#smp-upload-preview').attr('src', product.image_url).show();
                        $('#smp-upload-placeholder').hide();
                        $('.smp-remove-image').show();
                    }
                    
                    updatePriceCalculator();
                }
            }
        });
    };
    
    // Produkt speichern
    $('#smp-product-form').on('submit', function(e) {
        e.preventDefault();
        
        const $btn = $('#smp-save-btn');
        const originalText = $btn.html();
        $btn.html('<i class="fas fa-spinner fa-spin"></i> Speichern...').prop('disabled', true);
        
        const formData = {
            action: 'smp_save_product',
            nonce: smp_nonce,
            product_id: $('#product_id').val(),
            name: $('#product_name').val(),
            category: $('#product_category').val(),
            description: $('#product_description').val(),
            barcode: $('#product_barcode').val(),
            price: $('#product_price').val(),
            stock_quantity: $('#product_stock').val(),
            stock_min_quantity: $('#product_min_stock').val(),
            image_url: $('#product_image').val(),
            status: $('input[name="product_status"]:checked').val()
        };
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    showToast('Erfolg', 'Produkt wurde erfolgreich gespeichert', 'success');
                    smpCloseProductModal();
                    // Seite neu laden oder Produkt dynamisch hinzufügen/aktualisieren
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('Fehler', response.data || 'Produkt konnte nicht gespeichert werden', 'error');
                }
            },
            error: function() {
                showToast('Fehler', 'Verbindungsfehler. Bitte versuchen Sie es erneut.', 'error');
            },
            complete: function() {
                $btn.html(originalText).prop('disabled', false);
            }
        });
    });
    
    // Produkt löschen
    window.smpDeleteProduct = function(productId, productName) {
        if (confirm(`Wirklich "${productName}" löschen?`)) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'smp_delete_product',
                    product_id: productId,
                    nonce: smp_nonce
                },
                success: function(response) {
                    if (response.success) {
                        showToast('Gelöscht', 'Produkt wurde gelöscht', 'success');
                        $(`.smp-product-card[data-product-id="${productId}"]`).fadeOut(300, function() {
                            $(this).remove();
                            if ($('.smp-product-card:visible').length === 0) {
                                $('#smp-empty-state').show();
                            }
                        });
                    } else {
                        showToast('Fehler', 'Produkt konnte nicht gelöscht werden', 'error');
                    }
                }
            });
        }
    };
    
    // Quick Edit
    window.smpQuickEdit = function(productId) {
        $('#smp-quick-edit-modal').addClass('active');
        
        // Daten laden
        const $card = $(`.smp-product-card[data-product-id="${productId}"]`);
        $('#quick_edit_id').val(productId);
        $('#quick_edit_name').val($card.find('.smp-card-title').text());
        $('#quick_edit_price').val($card.find('.smp-price').text().replace(',', '.').replace(' €', ''));
        $('#quick_edit_stock').val($card.data('stock'));
    };
    
    window.smpCloseQuickEdit = function() {
        $('#smp-quick-edit-modal').removeClass('active');
    };
    
    window.smpSaveQuickEdit = function() {
        const productId = $('#quick_edit_id').val();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'smp_quick_update_product',
                product_id: productId,
                name: $('#quick_edit_name').val(),
                price: $('#quick_edit_price').val(),
                stock_quantity: $('#quick_edit_stock').val(),
                nonce: smp_nonce
            },
            success: function(response) {
                if (response.success) {
                    showToast('Erfolg', 'Produkt aktualisiert', 'success');
                    smpCloseQuickEdit();
                    // Karte aktualisieren
                    setTimeout(() => location.reload(), 500);
                }
            }
        });
    };
    
    // Bestand anpassen
    window.smpAdjustStock = function(productId, currentStockValue) {
        currentStockProductId = productId;
        currentStock = currentStockValue;
        $('#current-stock').text(currentStock);
        $('#stock-adjust-value').val(0);
        $('#new-stock').text(currentStock);
        $('#smp-stock-modal').addClass('active');
    };
    
    window.smpCloseStockModal = function() {
        $('#smp-stock-modal').removeClass('active');
    };
    
    window.smpStockChange = function(amount) {
        const currentAdjust = parseInt($('#stock-adjust-value').val()) || 0;
        $('#stock-adjust-value').val(currentAdjust + amount);
        updateNewStock();
    };
    
    $('#stock-adjust-value').on('input', updateNewStock);
    
    function updateNewStock() {
        const adjust = parseInt($('#stock-adjust-value').val()) || 0;
        const newStock = Math.max(0, currentStock + adjust);
        $('#new-stock').text(newStock);
    }
    
    window.smpSaveStockAdjust = function() {
        const adjust = parseInt($('#stock-adjust-value').val()) || 0;
        const newStock = Math.max(0, currentStock + adjust);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'smp_update_stock',
                product_id: currentStockProductId,
                stock_quantity: newStock,
                nonce: smp_nonce
            },
            success: function(response) {
                if (response.success) {
                    showToast('Erfolg', 'Bestand aktualisiert', 'success');
                    smpCloseStockModal();
                    
                    // Karte aktualisieren
                    const $card = $(`.smp-product-card[data-product-id="${currentStockProductId}"]`);
                    $card.data('stock', newStock);
                    $card.find('.smp-stock-value').text(newStock);
                    
                    // Badge aktualisieren
                    $card.find('.smp-badge').remove();
                    if (newStock <= 0) {
                        $card.find('.smp-card-image').append('<span class="smp-badge smp-badge-danger">Ausverkauft</span>');
                    } else if (newStock <= 10) {
                        $card.find('.smp-card-image').append('<span class="smp-badge smp-badge-warning">Niedriger Bestand</span>');
                    }
                }
            }
        });
    };
    
    // Bild-Upload
    window.smpSelectImage = function() {
        const frame = wp.media({
            title: 'Produktbild auswählen',
            button: {
                text: 'Bild verwenden'
            },
            multiple: false
        });
        
        frame.on('select', function() {
            const attachment = frame.state().get('selection').first().toJSON();
            $('#product_image').val(attachment.url);
            $('#smp-upload-preview').attr('src', attachment.url).show();
            $('#smp-upload-placeholder').hide();
            $('.smp-remove-image').show();
        });
        
        frame.open();
    };
    
    window.smpRemoveImage = function(e) {
        e.stopPropagation();
        $('#product_image').val('');
        $('#smp-upload-preview').hide();
        $('#smp-upload-placeholder').show();
        $('.smp-remove-image').hide();
    };
    
    // Preis-Kalkulator
    $('#product_price').on('input', updatePriceCalculator);
    
    function updatePriceCalculator() {
        const price = parseFloat($('#product_price').val()) || 0;
        const taxRate = 0.19; // 19% MwSt
        const netto = price / (1 + taxRate);
        const tax = price - netto;
        
        $('#price-netto').text(netto.toFixed(2).replace('.', ',') + ' €');
        $('#price-tax').text(tax.toFixed(2).replace('.', ',') + ' €');
    }
    
    // Barcode generieren
    window.smpGenerateBarcode = function() {
        const timestamp = Date.now();
        const random = Math.floor(Math.random() * 1000);
        const barcode = `${timestamp}${random}`;
        $('#product_barcode').val(barcode);
        showToast('Barcode generiert', 'Ein eindeutiger Barcode wurde erstellt', 'success');
    };
    
    // Barcode scannen
    window.smpScanBarcode = function() {
        showToast('Scanner', 'Barcode-Scanner wird geöffnet...', 'info');
        // Hier würde der Scanner geöffnet werden
    };
    
    // Neue Kategorie hinzufügen
    window.smpAddNewCategory = function() {
        const categoryName = prompt('Name der neuen Kategorie:');
        if (categoryName) {
            $('#product_category').append(`<option value="${categoryName}" selected>${categoryName}</option>`);
            showToast('Kategorie hinzugefügt', `"${categoryName}" wurde als Kategorie hinzugefügt`, 'success');
        }
    };
    
    // Produkt duplizieren
    window.smpDuplicateProduct = function(productId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'smp_duplicate_product',
                product_id: productId,
                nonce: smp_nonce
            },
            success: function(response) {
                if (response.success) {
                    showToast('Erfolg', 'Produkt wurde dupliziert', 'success');
                    setTimeout(() => location.reload(), 1000);
                }
            }
        });
    };
    
    // Statistiken anzeigen
    window.smpViewStats = function(productId) {
        showToast('Statistiken', 'Produktstatistiken werden geladen...', 'info');
        // Hier würden die Statistiken angezeigt werden
    };
    
    // Import/Export
    window.smpOpenImportModal = function() {
        $('#smp-import-modal').addClass('active');
    };
    
    window.smpCloseImportModal = function() {
        $('#smp-import-modal').removeClass('active');
    };
    
    window.smpExportProducts = function() {
        showToast('Export', 'Produkte werden exportiert...', 'info');
        window.location.href = ajaxurl + '?action=smp_export_products&nonce=' + smp_nonce;
    };
    
    // Import Upload Zone
    $('#import-upload-zone').on('click', function() {
        $('#import-file').click();
    });
    
    $('#import-file').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            $('#start-import-btn').prop('disabled', false);
            // Vorschau anzeigen
            $('#import-preview').show();
            $('#import-preview-content').html(`<p>Datei: ${file.name}<br>Größe: ${(file.size / 1024).toFixed(2)} KB</p>`);
        }
    });
    
    window.smpStartImport = function() {
        const file = $('#import-file')[0].files[0];
        if (!file) return;
        
        const formData = new FormData();
        formData.append('action', 'smp_import_products');
        formData.append('nonce', smp_nonce);
        formData.append('file', file);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showToast('Erfolg', `${response.data.count} Produkte importiert`, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('Fehler', response.data || 'Import fehlgeschlagen', 'error');
                }
            }
        });
    };
    
    // Keyboard Shortcuts
    $(document).on('keydown', function(e) {
        // Strg/Cmd + N = Neues Produkt
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            smpOpenProductModal();
        }
        
        // Escape = Modal schließen
        if (e.key === 'Escape') {
            $('.smp-modal.active').removeClass('active');
        }
        
        // Strg/Cmd + S = Speichern (in Modal)
        if ((e.ctrlKey || e.metaKey) && e.key === 's' && $('.smp-modal.active').length) {
            e.preventDefault();
            $('.smp-modal.active form').submit();
        }
    });
    
    // Clear Search
    window.smpClearSearch = function() {
        $('#smp-product-search').val('').trigger('input');
    };
    
    // Init
    updatePriceCalculator();
});
</script>