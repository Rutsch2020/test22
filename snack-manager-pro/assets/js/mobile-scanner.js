// Mobile Scanner Pro - Korrigierte Version mit Fehlerbehandlung
class MobileScanner {
    constructor() {
        // Überprüfe AJAX-Konfiguration
        this.ajaxUrl = this.getAjaxUrl();
        this.nonce = this.getNonce();
        
        if (!this.ajaxUrl) {
            console.error('AJAX URL nicht gefunden!');
            this.showError('Konfigurationsfehler: AJAX URL fehlt');
            return;
        }
        
        this.video = null;
        this.canvas = null;
        this.context = null;
        this.stream = null;
        this.scanning = false;
        this.currentCamera = 'environment';
        this.zoomLevel = 1;
        this.torchEnabled = false;
        
        // ZXing Scanner
        this.codeReader = null;
        
        // Settings
        this.settings = {
            autoFlash: true,
            vibration: true,
            sound: true,
            continuousScan: false,
            scanDelay: 1500
        };
        
        // State
        this.lastScannedCode = null;
        this.scanTimeout = null;
        
        console.log('Scanner initialisiert mit AJAX URL:', this.ajaxUrl);
        this.init();
    }
    
    getAjaxUrl() {
        // Verschiedene Möglichkeiten für die AJAX URL
        if (typeof smp_ajax !== 'undefined' && smp_ajax.ajax_url) {
            return smp_ajax.ajax_url;
        }
        if (typeof ajaxurl !== 'undefined') {
            return ajaxurl;
        }
        if (typeof wp !== 'undefined' && wp.ajax && wp.ajax.settings && wp.ajax.settings.url) {
            return wp.ajax.settings.url;
        }
        // Fallback - versuche es aus der URL zu konstruieren
        const adminUrl = window.location.origin + '/wp-admin/admin-ajax.php';
        console.warn('AJAX URL nicht gefunden, verwende Fallback:', adminUrl);
        return adminUrl;
    }
    
    getNonce() {
        if (typeof smp_ajax !== 'undefined' && smp_ajax.nonce) {
            return smp_ajax.nonce;
        }
        // Versuche Nonce aus Meta-Tag zu lesen (falls vorhanden)
        const metaNonce = document.querySelector('meta[name="smp-nonce"]');
        if (metaNonce) {
            return metaNonce.content;
        }
        console.warn('Nonce nicht gefunden!');
        return '';
    }
    
    async init() {
        try {
            // Check for camera support
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                this.showError('Kamera wird nicht unterstützt');
                return;
            }
            
            // Load ZXing library
            await this.loadZXing();
            
            // Setup DOM elements
            this.setupElements();
            
            // Setup event listeners
            this.setupEventListeners();
            
            // Load settings
            this.loadSettings();
            
            // Load quick products
            this.loadQuickProducts();
            
            // Initialize status
            this.updateStatus('Bereit zum Scannen', 'ready');
            
        } catch (error) {
            console.error('Initialisierungsfehler:', error);
            this.showError('Scanner konnte nicht initialisiert werden');
        }
    }
    
    async loadZXing() {
        return new Promise((resolve, reject) => {
            // Prüfe ob ZXing bereits geladen ist
            if (typeof ZXing !== 'undefined') {
                this.codeReader = new ZXing.BrowserMultiFormatReader();
                resolve();
                return;
            }
            
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/@zxing/library@latest/umd/index.min.js';
            script.onload = () => {
                if (typeof ZXing !== 'undefined') {
                    this.codeReader = new ZXing.BrowserMultiFormatReader();
                    console.log('ZXing Library geladen');
                    resolve();
                } else {
                    reject(new Error('ZXing konnte nicht geladen werden'));
                }
            };
            script.onerror = () => {
                reject(new Error('ZXing Script konnte nicht geladen werden'));
            };
            document.head.appendChild(script);
        });
    }
    
    setupElements() {
        this.video = document.getElementById('camera-stream');
        this.canvas = document.getElementById('camera-canvas');
        
        if (!this.video || !this.canvas) {
            throw new Error('Erforderliche DOM-Elemente nicht gefunden');
        }
        
        this.context = this.canvas.getContext('2d');
        
        // Set canvas size
        this.resizeCanvas();
        window.addEventListener('resize', () => this.resizeCanvas());
    }
    
    resizeCanvas() {
        if (this.video && this.canvas) {
            this.canvas.width = this.video.videoWidth || window.innerWidth;
            this.canvas.height = this.video.videoHeight || window.innerHeight;
        }
    }
    
    setupEventListeners() {
        // Start button
        const startBtn = document.getElementById('start-scanner');
        if (startBtn) {
            startBtn.addEventListener('click', () => this.startScanner());
        }
        
        // Camera controls
        const torchBtn = document.getElementById('torch-toggle');
        if (torchBtn) {
            torchBtn.addEventListener('click', () => this.toggleTorch());
        }
        
        const cameraBtn = document.getElementById('camera-switch');
        if (cameraBtn) {
            cameraBtn.addEventListener('click', () => this.switchCamera());
        }
        
        const zoomBtn = document.getElementById('zoom-control');
        if (zoomBtn) {
            zoomBtn.addEventListener('click', () => this.cycleZoom());
        }
        
        // Manual input
        const barcodeInput = document.getElementById('barcode-input');
        const manualSubmit = document.getElementById('manual-submit');
        
        if (barcodeInput) {
            barcodeInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.handleManualInput(barcodeInput.value);
                }
            });
        }
        
        if (manualSubmit) {
            manualSubmit.addEventListener('click', () => {
                this.handleManualInput(barcodeInput.value);
            });
        }
        
        // Result actions
        const confirmBtn = document.getElementById('confirm-sale');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', () => this.confirmSale());
        }
        
        const cancelBtn = document.getElementById('cancel-scan');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => this.cancelScan());
        }
        
        // Settings
        const settingsBtn = document.getElementById('scanner-settings');
        if (settingsBtn) {
            settingsBtn.addEventListener('click', () => this.openSettings());
        }
        
        const closeModalBtn = document.querySelector('.close-modal');
        if (closeModalBtn) {
            closeModalBtn.addEventListener('click', () => this.closeSettings());
        }
        
        // Settings inputs
        this.setupSettingsListeners();
    }
    
    setupSettingsListeners() {
        const settingsMap = {
            'auto-flash': 'autoFlash',
            'vibration': 'vibration',
            'sound': 'sound',
            'continuous-scan': 'continuousScan'
        };
        
        Object.entries(settingsMap).forEach(([id, setting]) => {
            const element = document.getElementById(id);
            if (element) {
                element.addEventListener('change', (e) => {
                    this.settings[setting] = e.target.checked;
                    this.saveSettings();
                });
            }
        });
        
        const scanDelay = document.getElementById('scan-delay');
        if (scanDelay) {
            scanDelay.addEventListener('input', (e) => {
                this.settings.scanDelay = parseInt(e.target.value);
                const delayValue = document.getElementById('delay-value');
                if (delayValue) {
                    delayValue.textContent = e.target.value + 'ms';
                }
                this.saveSettings();
            });
        }
    }
    
    async startScanner() {
        try {
            console.log('Starte Scanner...');
            
            // Request camera permission
            const constraints = {
                video: {
                    facingMode: this.currentCamera,
                    width: { ideal: 1920 },
                    height: { ideal: 1080 }
                }
            };
            
            this.stream = await navigator.mediaDevices.getUserMedia(constraints);
            this.video.srcObject = this.stream;
            
            // Wait for video to be ready
            await new Promise((resolve) => {
                this.video.onloadedmetadata = resolve;
            });
            
            console.log('Video bereit');
            
            // Update UI
            const startScreen = document.getElementById('start-screen');
            const cameraView = document.getElementById('camera-view');
            
            if (startScreen) startScreen.classList.remove('active');
            if (cameraView) cameraView.classList.add('active');
            
            // Start scanning
            this.scanning = true;
            this.scanBarcode();
            
            // Check for torch support
            this.checkTorchSupport();
            
            // Auto-enable torch in dark environments
            if (this.settings.autoFlash) {
                setTimeout(() => this.checkLightLevel(), 1000);
            }
            
            this.updateStatus('Scanner aktiv', 'ready');
            
        } catch (error) {
            console.error('Camera error:', error);
            this.handleCameraError(error);
        }
    }
    
    async scanBarcode() {
        if (!this.scanning || !this.codeReader) return;
        
        try {
            // Use ZXing to decode from video stream
            const result = await this.codeReader.decodeOnceFromVideoDevice(undefined, this.video);
            
            if (result && result.text) {
                console.log('Barcode erkannt:', result.text);
                this.handleScanResult(result.text);
            }
        } catch (error) {
            // Continue scanning if not cancelled
            if (this.scanning && error.name !== 'NotFoundException') {
                // Retry after a short delay
                setTimeout(() => {
                    if (this.scanning) {
                        this.scanBarcode();
                    }
                }, 100);
            } else if (this.scanning) {
                // Not found, continue scanning
                requestAnimationFrame(() => this.scanBarcode());
            }
        }
    }
    
    async handleScanResult(barcode) {
        // Avoid duplicate scans
        if (this.lastScannedCode === barcode && this.scanTimeout) {
            return;
        }
        
        console.log('Verarbeite Barcode:', barcode);
        this.lastScannedCode = barcode;
        
        // Feedback
        if (this.settings.vibration && navigator.vibrate) {
            navigator.vibrate([100, 50, 100]);
        }
        
        if (this.settings.sound) {
            this.playSound();
        }
        
        // Show loading
        this.updateStatus('Barcode wird verarbeitet...', 'loading');
        
        // Process barcode
        try {
            console.log('Sende AJAX Request an:', this.ajaxUrl);
            
            const formData = new URLSearchParams({
                action: 'smp_handle_scan',
                nonce: this.nonce,
                barcode: barcode
            });
            
            console.log('Request Data:', formData.toString());
            
            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: formData
            });
            
            console.log('Response Status:', response.status);
            
            const responseText = await response.text();
            console.log('Response Text:', responseText);
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                console.error('JSON Parse Error:', e);
                throw new Error('Server-Antwort konnte nicht verarbeitet werden');
            }
            
            if (data.success) {
                this.showResult(data.data);
            } else {
                this.showError(data.data || 'Produkt nicht gefunden');
            }
            
        } catch (error) {
            console.error('AJAX Error:', error);
            this.showError('Netzwerkfehler: ' + error.message);
        }
        
        // Set timeout for next scan
        if (this.settings.continuousScan) {
            this.scanTimeout = setTimeout(() => {
                this.lastScannedCode = null;
                this.scanTimeout = null;
                this.cancelScan();
            }, this.settings.scanDelay);
        }
    }
    
    showResult(data) {
        if (!data || !data.product) {
            this.showError('Ungültige Produktdaten');
            return;
        }
        
        const { product } = data;
        
        // Update result display
        const nameElement = document.getElementById('product-name');
        const priceElement = document.getElementById('product-price');
        
        if (nameElement) nameElement.textContent = product.name;
        if (priceElement) priceElement.textContent = product.price + ' €';
        
        // Store current product
        this.currentProduct = product;
        
        // Show result
        const resultElement = document.getElementById('scan-result');
        if (resultElement) {
            resultElement.classList.add('active');
        }
        
        // Pause scanning
        this.scanning = false;
        
        this.updateStatus('Produkt gefunden', 'success');
    }
    
    async confirmSale() {
        if (!this.currentProduct) {
            this.showError('Kein Produkt ausgewählt');
            return;
        }
        
        this.updateStatus('Verkauf wird verarbeitet...', 'loading');
        
        try {
            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'smp_quick_sale',
                    nonce: this.nonce,
                    product_id: this.currentProduct.id
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.updateStatus('Verkauf erfolgreich!', 'success');
                
                // Close result after delay
                setTimeout(() => {
                    this.cancelScan();
                }, 1500);
                
            } else {
                this.showError(data.data || 'Verkauf fehlgeschlagen');
            }
            
        } catch (error) {
            console.error('Sale Error:', error);
            this.showError('Netzwerkfehler beim Verkauf');
        }
    }
    
    cancelScan() {
        // Hide result
        const resultElement = document.getElementById('scan-result');
        if (resultElement) {
            resultElement.classList.remove('active');
        }
        
        // Clear input
        const barcodeInput = document.getElementById('barcode-input');
        if (barcodeInput) {
            barcodeInput.value = '';
        }
        
        // Resume scanning
        if (this.stream) {
            this.scanning = true;
            this.scanBarcode();
        }
        
        this.updateStatus('Scanner aktiv', 'ready');
    }
    
    async toggleTorch() {
        if (!this.stream) return;
        
        const track = this.stream.getVideoTracks()[0];
        if (!track) return;
        
        const capabilities = track.getCapabilities();
        
        if (!capabilities.torch) {
            this.showError('Taschenlampe nicht verfügbar');
            return;
        }
        
        try {
            this.torchEnabled = !this.torchEnabled;
            await track.applyConstraints({
                advanced: [{ torch: this.torchEnabled }]
            });
            
            const torchBtn = document.getElementById('torch-toggle');
            if (torchBtn) {
                torchBtn.classList.toggle('active', this.torchEnabled);
            }
            
        } catch (error) {
            console.error('Torch Error:', error);
            this.showError('Taschenlampe konnte nicht aktiviert werden');
        }
    }
    
    async switchCamera() {
        // Stop current stream
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
        }
        
        // Switch camera
        this.currentCamera = this.currentCamera === 'environment' ? 'user' : 'environment';
        
        // Restart scanner
        await this.startScanner();
    }
    
    cycleZoom() {
        if (!this.stream) return;
        
        const track = this.stream.getVideoTracks()[0];
        if (!track) return;
        
        const capabilities = track.getCapabilities();
        
        if (!capabilities.zoom) {
            this.showError('Zoom nicht verfügbar');
            return;
        }
        
        // Cycle through zoom levels
        const zoomLevels = [1, 2, 3];
        const currentIndex = zoomLevels.indexOf(this.zoomLevel);
        const nextIndex = (currentIndex + 1) % zoomLevels.length;
        this.zoomLevel = zoomLevels[nextIndex];
        
        track.applyConstraints({
            advanced: [{ zoom: this.zoomLevel }]
        });
        
        // Update button text
        const zoomBtn = document.getElementById('zoom-control');
        if (zoomBtn) {
            const span = zoomBtn.querySelector('span');
            if (span) span.textContent = `${this.zoomLevel}x`;
        }
    }
    
    async checkTorchSupport() {
        if (!this.stream) return;
        
        const track = this.stream.getVideoTracks()[0];
        if (!track) return;
        
        const capabilities = track.getCapabilities();
        
        const torchBtn = document.getElementById('torch-toggle');
        if (torchBtn && !capabilities.torch) {
            torchBtn.style.display = 'none';
        }
    }
    
    async checkLightLevel() {
        if (!this.video || !this.settings.autoFlash) return;
        
        // Create temporary canvas for light detection
        const tempCanvas = document.createElement('canvas');
        const tempContext = tempCanvas.getContext('2d');
        
        tempCanvas.width = 100;
        tempCanvas.height = 100;
        
        try {
            // Draw video frame
            tempContext.drawImage(this.video, 0, 0, 100, 100);
            
            // Get image data
            const imageData = tempContext.getImageData(0, 0, 100, 100);
            const data = imageData.data;
            
            // Calculate average brightness
            let brightness = 0;
            for (let i = 0; i < data.length; i += 4) {
                brightness += (data[i] + data[i + 1] + data[i + 2]) / 3;
            }
            brightness = brightness / (data.length / 4);
            
            console.log('Helligkeit:', brightness);
            
            // Enable torch if too dark
            if (brightness < 50 && !this.torchEnabled) {
                this.toggleTorch();
            }
        } catch (error) {
            console.error('Light detection error:', error);
        }
    }
    
    handleManualInput(barcode) {
        if (!barcode || barcode.length < 3) {
            this.showError('Bitte gültigen Barcode eingeben');
            return;
        }
        
        console.log('Manuelle Eingabe:', barcode);
        this.handleScanResult(barcode);
    }
    
    handleCameraError(error) {
        let message = 'Kamera konnte nicht gestartet werden';
        
        if (error.name === 'NotAllowedError') {
            message = 'Kamera-Zugriff wurde verweigert. Bitte erlauben Sie den Zugriff in den Browser-Einstellungen.';
        } else if (error.name === 'NotFoundError') {
            message = 'Keine Kamera gefunden';
        } else if (error.name === 'NotReadableError') {
            message = 'Kamera wird bereits verwendet';
        } else if (error.name === 'OverconstrainedError') {
            message = 'Kamera unterstützt die angeforderten Einstellungen nicht';
        }
        
        this.showError(message);
        this.updateStatus(message, 'error');
    }
    
    showError(message) {
        console.error('Error:', message);
        this.updateStatus(message, 'error');
        
        if (this.settings.vibration && navigator.vibrate) {
            navigator.vibrate([200, 100, 200]);
        }
    }
    
    updateStatus(message, type = 'ready') {
        const statusBar = document.getElementById('status-bar');
        if (!statusBar) return;
        
        const statusText = statusBar.querySelector('.status-text');
        if (statusText) {
            statusText.textContent = message;
        }
        
        // Update class
        statusBar.className = 'status-bar';
        if (type === 'error') {
            statusBar.classList.add('error');
        } else if (type === 'warning') {
            statusBar.classList.add('warning');
        }
    }
    
    playSound() {
        try {
            const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBCl+zPLTijMGHm7A7+OZURE');
            audio.volume = 0.5;
            audio.play().catch(() => {});
        } catch (error) {
            console.error('Sound playback error:', error);
        }
    }
    
    openSettings() {
        const modal = document.getElementById('settings-modal');
        if (modal) {
            modal.classList.add('active');
        }
    }
    
    closeSettings() {
        const modal = document.getElementById('settings-modal');
        if (modal) {
            modal.classList.remove('active');
        }
    }
    
    loadSettings() {
        try {
            const saved = localStorage.getItem('smp_scanner_settings');
            if (saved) {
                this.settings = { ...this.settings, ...JSON.parse(saved) };
                
                // Update UI
                Object.entries({
                    'auto-flash': this.settings.autoFlash,
                    'vibration': this.settings.vibration,
                    'sound': this.settings.sound,
                    'continuous-scan': this.settings.continuousScan
                }).forEach(([id, value]) => {
                    const element = document.getElementById(id);
                    if (element) element.checked = value;
                });
                
                const scanDelay = document.getElementById('scan-delay');
                if (scanDelay) {
                    scanDelay.value = this.settings.scanDelay;
                    const delayValue = document.getElementById('delay-value');
                    if (delayValue) {
                        delayValue.textContent = this.settings.scanDelay + 'ms';
                    }
                }
            }
        } catch (error) {
            console.error('Settings load error:', error);
        }
    }
    
    saveSettings() {
        try {
            localStorage.setItem('smp_scanner_settings', JSON.stringify(this.settings));
        } catch (error) {
            console.error('Settings save error:', error);
        }
    }
    
    async loadQuickProducts() {
        try {
            console.log('Lade Quick Products...');
            
            const url = `${this.ajaxUrl}?action=smp_get_quick_products&_wpnonce=${this.nonce}`;
            const response = await fetch(url);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success && data.data) {
                const container = document.getElementById('quick-products');
                if (!container) return;
                
                container.innerHTML = '';
                
                data.data.forEach(product => {
                    const item = document.createElement('div');
                    item.className = 'quick-product';
                    item.innerHTML = `
                        <div class="name">${this.escapeHtml(product.name)}</div>
                        <div class="price">${product.price}</div>
                    `;
                    item.addEventListener('click', () => {
                        this.quickSale(product.id);
                    });
                    container.appendChild(item);
                });
            }
        } catch (error) {
            console.error('Failed to load quick products:', error);
        }
    }
    
    async quickSale(productId) {
        if (!productId) return;
        
        this.updateStatus('Schnellverkauf...', 'loading');
        
        try {
            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'smp_quick_sale',
                    nonce: this.nonce,
                    product_id: productId
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.updateStatus('Verkauf erfolgreich!', 'success');
                
                if (this.settings.vibration && navigator.vibrate) {
                    navigator.vibrate(100);
                }
                
                if (this.settings.sound) {
                    this.playSound();
                }
                
                setTimeout(() => {
                    this.updateStatus('Bereit', 'ready');
                }, 2000);
                
            } else {
                this.showError(data.data || 'Verkauf fehlgeschlagen');
            }
            
        } catch (error) {
            console.error('Quick sale error:', error);
            this.showError('Netzwerkfehler beim Verkauf');
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

// Initialize scanner when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM geladen, prüfe Scanner-Element...');
    
    if (document.querySelector('.smp-mobile-scanner')) {
        console.log('Scanner-Element gefunden, initialisiere...');
        
        // Debug-Ausgabe
        console.log('smp_ajax:', typeof smp_ajax !== 'undefined' ? smp_ajax : 'nicht definiert');
        console.log('ajaxurl:', typeof ajaxurl !== 'undefined' ? ajaxurl : 'nicht definiert');
        
        try {
            window.mobileScanner = new MobileScanner();
        } catch (error) {
            console.error('Scanner konnte nicht initialisiert werden:', error);
            alert('Scanner-Initialisierung fehlgeschlagen: ' + error.message);
        }
    } else {
        console.log('Scanner-Element nicht gefunden');
    }
});