// Mobile-optimierter Scanner für Snack Manager Pro
(function($) {
    'use strict';
    
    // Warte bis jQuery bereit ist
    $(document).ready(function() {
        // Überprüfe ob wir auf der Scanner-Seite sind
        if ($('#smp-scanner-page').length === 0) {
            return;
        }
        
        console.log('Scanner-Seite erkannt, initialisiere...');
        
        // Scanner-Klasse
        class BarcodeScanner {
            constructor() {
                this.isScanning = false;
                this.currentProduct = null;
                this.scanSound = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBCl+zPLTijMGHm7A7+OZURE');
                this.lastScannedCode = null;
                this.scanTimeout = null;
                
                this.init();
            }
            
            init() {
                console.log('Initialisiere Scanner...');
                console.log('AJAX URL:', smp_ajax.ajax_url);
                console.log('Nonce:', smp_ajax.nonce);
                
                // Quagga konfigurieren
                this.configureQuagga();
                
                // Event Listener
                this.bindEvents();
                
                // Quick Products laden
                this.loadQuickProducts();
                
                // Recent Transactions laden
                this.loadRecentTransactions();
                
                // Auto-focus auf Input
                $('#manual-barcode-input').focus();
            }
            
            configureQuagga() {
                if (typeof Quagga === 'undefined') {
                    console.error('Quagga ist nicht geladen!');
                    this.showError('Scanner-Bibliothek konnte nicht geladen werden');
                    return;
                }
                
                // Mobile-optimierte Konfiguration
                this.quaggaConfig = {
                    inputStream: {
                        name: "Live",
                        type: "LiveStream",
                        target: document.querySelector('#scanner-container'),
                        constraints: {
                            width: { min: 640, ideal: 1280, max: 1920 },
                            height: { min: 480, ideal: 720, max: 1080 },
                            facingMode: "environment", // Rückkamera
                            aspectRatio: { ideal: 1.7777778 }
                        }
                    },
                    decoder: {
                        readers: [
                            "ean_reader",
                            "ean_8_reader",
                            "code_128_reader",
                            "code_39_reader",
                            "upc_reader",
                            "upc_e_reader",
                            "codabar_reader"
                        ],
                        multiple: false
                    },
                    locate: true,
                    locator: {
                        halfSample: true,
                        patchSize: "medium", // small, medium, large
                        debug: {
                            showCanvas: false,
                            showPatches: false,
                            showFoundPatches: false,
                            showSkeleton: false,
                            showLabels: false,
                            showPatchLabels: false,
                            showRemainingPatchLabels: false,
                            boxFromPatches: {
                                showTransformed: false,
                                showTransformedBox: false,
                                showBB: false
                            }
                        }
                    },
                    numOfWorkers: navigator.hardwareConcurrency || 4,
                    frequency: 10,
                    debug: false
                };
            }
            
            bindEvents() {
                const self = this;
                
                // Start Scanner Button
                $('#start-scanner-btn').on('click', function() {
                    self.startScanner();
                });
                
                // Stop Scanner Button
                $('#stop-scanner-btn').on('click', function() {
                    self.stopScanner();
                });
                
                // Manueller Barcode Input - Enter-Taste
                $('#manual-barcode-input').on('keypress', function(e) {
                    if (e.which === 13) { // Enter
                        e.preventDefault();
                        const barcode = $(this).val().trim();
                        if (barcode) {
                            self.processBarcode(barcode);
                        }
                    }
                });
                
                // Submit Button für manuelle Eingabe
                $('#submit-barcode-btn').on('click', function() {
                    const barcode = $('#manual-barcode-input').val().trim();
                    if (barcode) {
                        self.processBarcode(barcode);
                    }
                });
                
                // Verkauf bestätigen
                $('#confirm-sale-btn').on('click', function() {
                    if (self.currentProduct) {
                        self.confirmSale();
                    }
                });
                
                // Abbrechen
                $('#cancel-scan-btn').on('click', function() {
                    self.resetScanResult();
                });
                
                // Quick Product Buttons (Event Delegation)
                $('#quick-products-grid').on('click', '.quick-product-btn', function() {
                    const productId = $(this).data('product-id');
                    if (productId) {
                        self.quickSale(productId);
                    }
                });
                
                // Prevent form submission on mobile
                $('form').on('submit', function(e) {
                    e.preventDefault();
                    return false;
                });
            }
            
            startScanner() {
                if (this.isScanning) {
                    return;
                }
                
                console.log('Starte Scanner...');
                this.showStatus('Scanner wird gestartet...', 'info');
                
                // Entferne Platzhalter
                $('.smp-scanner-placeholder').hide();
                
                Quagga.init(this.quaggaConfig, (err) => {
                    if (err) {
                        console.error('Scanner-Fehler:', err);
                        this.showError('Kamera konnte nicht gestartet werden. Bitte prüfen Sie die Berechtigungen.');
                        $('.smp-scanner-placeholder').show();
                        return;
                    }
                    
                    console.log('Scanner erfolgreich gestartet');
                    Quagga.start();
                    this.isScanning = true;
                    
                    $('#start-scanner-btn').hide();
                    $('#stop-scanner-btn').show();
                    $('#scanner-container').addClass('active');
                    
                    this.showStatus('Scanner aktiv - Barcode vor die Kamera halten', 'success');
                });
                
                // Barcode erkannt
                Quagga.onDetected((result) => {
                    if (result && result.codeResult && result.codeResult.code) {
                        const barcode = result.codeResult.code;
                        
                        // Vermeide doppelte Scans
                        if (this.lastScannedCode === barcode && this.scanTimeout) {
                            return;
                        }
                        
                        console.log('Barcode erkannt:', barcode);
                        
                        // Sound abspielen
                        this.scanSound.play().catch(() => {});
                        
                        // Vibration (falls unterstützt)
                        if (navigator.vibrate) {
                            navigator.vibrate(200);
                        }
                        
                        // Barcode verarbeiten
                        this.processBarcode(barcode);
                        
                        // Timeout setzen
                        this.lastScannedCode = barcode;
                        this.scanTimeout = setTimeout(() => {
                            this.lastScannedCode = null;
                            this.scanTimeout = null;
                        }, 2000);
                    }
                });
            }
            
            stopScanner() {
                if (!this.isScanning) {
                    return;
                }
                
                console.log('Stoppe Scanner...');
                Quagga.stop();
                this.isScanning = false;
                
                $('#start-scanner-btn').show();
                $('#stop-scanner-btn').hide();
                $('#scanner-container').removeClass('active');
                $('.smp-scanner-placeholder').show();
                
                this.showStatus('Scanner gestoppt', 'info');
            }
            
            processBarcode(barcode) {
                console.log('Verarbeite Barcode:', barcode);
                
                // Leere das Input-Feld
                $('#manual-barcode-input').val('');
                
                // Scanner pausieren während der Verarbeitung
                if (this.isScanning) {
                    Quagga.pause();
                }
                
                this.showStatus('Suche Produkt...', 'info');
                
                // AJAX Request
                $.ajax({
                    url: smp_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'smp_handle_scan',
                        nonce: smp_ajax.nonce,
                        barcode: barcode
                    },
                    success: (response) => {
                        console.log('Server-Antwort:', response);
                        
                        if (response.success) {
                            if (response.data.action === 'found') {
                                this.showProduct(response.data.product);
                            } else if (response.data.action === 'not_found') {
                                this.showError('Produkt nicht gefunden: ' + barcode);
                                this.resetAfterDelay();
                            }
                        } else {
                            this.showError(response.data || 'Fehler beim Verarbeiten des Barcodes');
                            this.resetAfterDelay();
                        }
                    },
                    error: (xhr, status, error) => {
                        console.error('AJAX-Fehler:', status, error);
                        console.error('Response:', xhr.responseText);
                        this.showError('Netzwerkfehler: ' + error);
                        this.resetAfterDelay();
                    }
                });
            }
            
            showProduct(product) {
                console.log('Zeige Produkt:', product);
                
                this.currentProduct = product;
                
                // Produkt-Details anzeigen
                $('#product-name').text(product.name);
                $('#product-price').text(product.price + ' €');
                $('#product-stock').text(product.stock + ' Stück');
                $('#product-category').text(product.category || '-');
                $('#product-barcode').text(product.barcode);
                
                // Bild anzeigen (falls vorhanden)
                if (product.image_url) {
                    $('#product-image').attr('src', product.image_url).show();
                } else {
                    $('#product-image').hide();
                }
                
                // Scan-Ergebnis anzeigen
                $('#scan-result').removeClass('d-none');
                $('#manual-input-section').addClass('d-none');
                
                this.showStatus('Produkt gefunden', 'success');
            }
            
            confirmSale() {
                if (!this.currentProduct) {
                    return;
                }
                
                console.log('Bestätige Verkauf für:', this.currentProduct);
                
                this.showStatus('Verkauf wird verarbeitet...', 'info');
                $('#confirm-sale-btn').prop('disabled', true);
                
                $.ajax({
                    url: smp_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'smp_quick_sale',
                        nonce: smp_ajax.nonce,
                        product_id: this.currentProduct.id
                    },
                    success: (response) => {
                        $('#confirm-sale-btn').prop('disabled', false);
                        
                        if (response.success) {
                            this.showStatus('Verkauf erfolgreich!', 'success');
                            
                            // Sound
                            this.scanSound.play().catch(() => {});
                            
                            // Vibration
                            if (navigator.vibrate) {
                                navigator.vibrate([50, 100, 50]);
                            }
                            
                            // Session-Statistiken aktualisieren
                            if (response.data.session) {
                                $('#session-revenue').text(response.data.session.total_revenue + ' €');
                                $('#session-count').text(response.data.session.transaction_count);
                            }
                            
                            // Nach 2 Sekunden zurücksetzen
                            setTimeout(() => {
                                this.resetScanResult();
                                this.loadRecentTransactions();
                            }, 2000);
                        } else {
                            this.showError(response.data || 'Verkauf fehlgeschlagen');
                        }
                    },
                    error: (xhr, status, error) => {
                        $('#confirm-sale-btn').prop('disabled', false);
                        console.error('Verkauf-Fehler:', error);
                        this.showError('Netzwerkfehler beim Verkauf');
                    }
                });
            }
            
            quickSale(productId) {
                console.log('Quick Sale für Produkt:', productId);
                
                this.showStatus('Schnellverkauf...', 'info');
                
                $.ajax({
                    url: smp_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'smp_quick_sale',
                        nonce: smp_ajax.nonce,
                        product_id: productId
                    },
                    success: (response) => {
                        if (response.success) {
                            this.showStatus('Verkauf erfolgreich!', 'success');
                            
                            // Sound
                            this.scanSound.play().catch(() => {});
                            
                            // Vibration
                            if (navigator.vibrate) {
                                navigator.vibrate(100);
                            }
                            
                            // Session-Statistiken aktualisieren
                            if (response.data.session) {
                                $('#session-revenue').text(response.data.session.total_revenue + ' €');
                                $('#session-count').text(response.data.session.transaction_count);
                            }
                            
                            // Quick Products und Recent Transactions neu laden
                            setTimeout(() => {
                                this.loadQuickProducts();
                                this.loadRecentTransactions();
                            }, 1000);
                        } else {
                            this.showError(response.data || 'Verkauf fehlgeschlagen');
                        }
                    },
                    error: (xhr, status, error) => {
                        console.error('Quick Sale Fehler:', error);
                        this.showError('Netzwerkfehler beim Schnellverkauf');
                    }
                });
            }
            
            loadQuickProducts() {
                console.log('Lade Quick Products...');
                
                $.ajax({
                    url: smp_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'smp_get_quick_products',
                        nonce: smp_ajax.nonce
                    },
                    success: (response) => {
                        if (response.success && response.data) {
                            let html = '';
                            response.data.forEach(product => {
                                const stockClass = product.stock < 10 ? 'low-stock' : '';
                                html += `
                                    <button class="quick-product-btn ${stockClass}" data-product-id="${product.id}">
                                        <div class="product-name">${this.escapeHtml(product.name)}</div>
                                        <div class="product-price">${product.price}</div>
                                        <div class="product-stock">Lager: ${product.stock}</div>
                                    </button>
                                `;
                            });
                            $('#quick-products-grid').html(html);
                        }
                    },
                    error: (xhr, status, error) => {
                        console.error('Fehler beim Laden der Quick Products:', error);
                    }
                });
            }
            
            loadRecentTransactions() {
                console.log('Lade Recent Transactions...');
                
                $.ajax({
                    url: smp_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'smp_get_recent_transactions',
                        nonce: smp_ajax.nonce,
                        limit: 5
                    },
                    success: (response) => {
                        if (response.success && response.data) {
                            let html = '';
                            if (response.data.length === 0) {
                                html = '<p class="no-transactions">Noch keine Verkäufe heute</p>';
                            } else {
                                html = '<div class="transactions-list">';
                                response.data.forEach(transaction => {
                                    const time = new Date(transaction.created_at).toLocaleTimeString('de-DE', {
                                        hour: '2-digit',
                                        minute: '2-digit'
                                    });
                                    html += `
                                        <div class="transaction-item">
                                            <div class="transaction-info">
                                                <div class="transaction-product">${this.escapeHtml(transaction.product_name)}</div>
                                                <div class="transaction-time">${time}</div>
                                            </div>
                                            <div class="transaction-amount">${transaction.amount} €</div>
                                        </div>
                                    `;
                                });
                                html += '</div>';
                            }
                            $('.smp-transactions-list').html(html);
                        }
                    },
                    error: (xhr, status, error) => {
                        console.error('Fehler beim Laden der Transaktionen:', error);
                    }
                });
            }
            
            resetScanResult() {
                this.currentProduct = null;
                $('#scan-result').addClass('d-none');
                $('#manual-input-section').removeClass('d-none');
                $('#manual-barcode-input').val('').focus();
                
                // Scanner wieder starten
                if (this.isScanning) {
                    Quagga.resume();
                }
            }
            
            resetAfterDelay() {
                setTimeout(() => {
                    this.resetScanResult();
                }, 3000);
            }
            
            showStatus(message, type = 'info') {
                const $status = $('#scanner-status');
                
                // Remove all status classes
                $status.removeClass('error warning success info');
                
                // Add appropriate class
                if (type === 'error') {
                    $status.addClass('error');
                } else if (type === 'warning') {
                    $status.addClass('warning');
                } else if (type === 'success') {
                    $status.addClass('success');
                }
                
                // Update text
                $status.find('span').text(message);
                
                // Update icon
                let icon = 'fa-info-circle';
                if (type === 'error') icon = 'fa-exclamation-triangle';
                if (type === 'success') icon = 'fa-check-circle';
                if (type === 'warning') icon = 'fa-exclamation-circle';
                
                $status.find('i').removeClass().addClass('fas ' + icon);
            }
            
            showError(message) {
                this.showStatus(message, 'error');
                
                // Vibration bei Fehler
                if (navigator.vibrate) {
                    navigator.vibrate([100, 50, 100, 50, 100]);
                }
            }
            
            escapeHtml(text) {
                const map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return text.replace(/[&<>"']/g, m => map[m]);
            }
        }
        
        // Scanner initialisieren
        window.smpScanner = new BarcodeScanner();
    });
    
})(jQuery);