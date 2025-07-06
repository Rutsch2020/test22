/**
 * Snack Manager Pro - Admin JavaScript
 */

(function($) {
    'use strict';

    // Globale Variablen
    const ajaxUrl = smp_ajax.ajax_url;
    const nonce = smp_ajax.nonce;
    const strings = smp_ajax.strings;
    const currency = smp_ajax.currency;

    // Initialisierung
    $(document).ready(function() {
        initializeApp();
        initializeEventHandlers();
        initializeAnimations();
        initializeTooltips();
    });

    /**
     * App-Initialisierung
     */
    function initializeApp() {
        // Dark Mode Toggle
        const darkModeToggle = $('#darkModeToggle');
        if (darkModeToggle.length) {
            darkModeToggle.on('change', function() {
                $('body').toggleClass('smp-dark-mode');
                saveSettings({ theme: $(this).is(':checked') ? 'dark' : 'light' });
            });
        }

        // Alpine.js für reaktive Komponenten
        if (typeof Alpine !== 'undefined') {
            Alpine.start();
        }

        // Live-Uhr
        updateClock();
        setInterval(updateClock, 1000);
    }

    /**
     * Event Handler
     */
    function initializeEventHandlers() {
        // Produkt speichern
        $(document).on('submit', '#smpProductForm', handleProductSave);
        
        // Produkt löschen
        $(document).on('click', '.smp-delete-product', handleProductDelete);
        
        // Produkt bearbeiten
        $(document).on('click', '.smp-edit-product', handleProductEdit);
        
        // Einstellungen speichern
        $(document).on('submit', '#smpSettingsForm', handleSettingsSave);
        
        // Modal schließen
        $(document).on('click', '.smp-modal-close, .smp-modal-overlay', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Suche
        $(document).on('input', '#productSearch', debounce(handleProductSearch, 300));
        
        // Kategorie-Filter
        $(document).on('change', '#categoryFilter', handleCategoryFilter);
    }

    /**
     * Produkt speichern
     */
    function handleProductSave(e) {
        e.preventDefault();
        
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        const originalText = submitBtn.text();
        
        // Button-Status ändern
        submitBtn.prop('disabled', true).html('<span class="smp-spinner-small"></span> Speichern...');
        
        // Formulardaten sammeln
        const formData = new FormData(form[0]);
        formData.append('action', 'smp_save_product');
        formData.append('nonce', nonce);
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showNotification('success', response.data.message);
                    
                    // Modal schließen
                    closeModal();
                    
                    // Tabelle aktualisieren
                    loadProducts();
                    
                    // Formular zurücksetzen
                    form[0].reset();
                } else {
                    showNotification('error', response.data.message);
                }
            },
            error: function() {
                showNotification('error', strings.error);
            },
            complete: function() {
                submitBtn.prop('disabled', false).text(originalText);
            }
        });
    }

    /**
     * Produkt löschen
     */
    function handleProductDelete(e) {
        e.preventDefault();
        
        const productId = $(this).data('product-id');
        const productName = $(this).data('product-name');
        
        // Bestätigung mit modernem Dialog
        if (!confirm(`${strings.confirm_delete}\n\nProdukt: ${productName}`)) {
            return;
        }
        
        const button = $(this);
        button.prop('disabled', true).html('<span class="smp-spinner-small"></span>');
        
        $.post(ajaxUrl, {
            action: 'smp_delete_product',
            product_id: productId,
            nonce: nonce
        })
        .done(function(response) {
            if (response.success) {
                showNotification('success', 'Produkt gelöscht');
                
                // Zeile animiert entfernen
                const row = button.closest('tr');
                row.fadeOut(300, function() {
                    row.remove();
                    updateProductCount();
                });
            } else {
                showNotification('error', response.data.message);
                button.prop('disabled', false).html('🗑️');
            }
        })
        .fail(function() {
            showNotification('error', strings.error);
            button.prop('disabled', false).html('🗑️');
        });
    }

    /**
     * Produkt bearbeiten
     */
    function handleProductEdit(e) {
        e.preventDefault();
        
        const productId = $(this).data('product-id');
        
        // Produktdaten laden
        $.post(ajaxUrl, {
            action: 'smp_get_product',
            product_id: productId,
            nonce: nonce
        })
        .done(function(response) {
            if (response.success) {
                // Modal mit Daten füllen
                fillProductModal(response.data.product);
                openModal('productModal');
            } else {
                showNotification('error', response.data.message);
            }
        })
        .fail(function() {
            showNotification('error', strings.error);
        });
    }

    /**
     * Einstellungen speichern
     */
    function handleSettingsSave(e) {
        e.preventDefault();
        
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        const originalText = submitBtn.text();
        
        submitBtn.prop('disabled', true).html('<span class="smp-spinner-small"></span> Speichern...');
        
        const settings = {
            notification_email: $('#notificationEmail').val(),
            currency: $('#currency').val(),
            theme: $('#darkMode').is(':checked') ? 'dark' : 'light',
            animations: $('#animations').is(':checked')
        };
        
        saveSettings(settings, function() {
            submitBtn.prop('disabled', false).text(originalText);
        });
    }

    /**
     * Einstellungen speichern (AJAX)
     */
    function saveSettings(settings, callback) {
        $.post(ajaxUrl, {
            action: 'smp_update_settings',
            settings: settings,
            nonce: nonce
        })
        .done(function(response) {
            if (response.success) {
                showNotification('success', strings.saved);
            } else {
                showNotification('error', response.data.message);
            }
        })
        .fail(function() {
            showNotification('error', strings.error);
        })
        .always(function() {
            if (typeof callback === 'function') {
                callback();
            }
        });
    }

    /**
     * Produkte laden
     */
    function loadProducts() {
        const container = $('#productsTableBody');
        if (!container.length) return;
        
        container.html('<tr><td colspan="6" class="smp-text-center"><div class="smp-spinner"></div></td></tr>');
        
        $.post(ajaxUrl, {
            action: 'smp_get_products',
            nonce: nonce
        })
        .done(function(response) {
            if (response.success) {
                renderProductsTable(response.data.products);
            } else {
                container.html('<tr><td colspan="6" class="smp-text-center">' + response.data.message + '</td></tr>');
            }
        })
        .fail(function() {
            container.html('<tr><td colspan="6" class="smp-text-center">' + strings.error + '</td></tr>');
        });
    }

    /**
     * Produkttabelle rendern
     */
    function renderProductsTable(products) {
        const container = $('#productsTableBody');
        container.empty();
        
        if (products.length === 0) {
            container.html('<tr><td colspan="6" class="smp-text-center">Keine Produkte gefunden</td></tr>');
            return;
        }
        
        products.forEach(function(product) {
            const row = $(`
                <tr data-product-id="${product.id}">
                    <td>${product.id}</td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            ${product.image_url ? `<img src="${product.image_url}" alt="" style="width: 40px; height: 40px; border-radius: 8px; object-fit: cover;">` : '<div style="width: 40px; height: 40px; background: var(--smp-gray-200); border-radius: 8px;"></div>'}
                            <div>
                                <div style="font-weight: 600;">${escapeHtml(product.name)}</div>
                                ${product.barcode ? `<div style="font-size: 0.75rem; opacity: 0.6;">${product.barcode}</div>` : ''}
                            </div>
                        </div>
                    </td>
                    <td><span class="smp-badge smp-badge-${getCategoryClass(product.category)}">${product.category || 'Uncategorized'}</span></td>
                    <td style="font-weight: 600;">€${parseFloat(product.price).toFixed(2).replace('.', ',')}</td>
                    <td>
                        <div class="smp-stock-indicator ${product.stock < 10 ? 'low' : 'good'}">
                            <span class="smp-stock-dot"></span>
                            ${product.stock} Stück
                        </div>
                    </td>
                    <td>
                        <div class="smp-product-actions">
                            <button class="smp-action-btn smp-edit-product" data-product-id="${product.id}" title="Bearbeiten">
                                ✏️
                            </button>
                            <button class="smp-action-btn delete smp-delete-product" 
                                    data-product-id="${product.id}" 
                                    data-product-name="${escapeHtml(product.name)}"
                                    title="Löschen">
                                🗑️
                            </button>
                        </div>
                    </td>
                </tr>
            `);
            
            container.append(row);
        });
        
        updateProductCount();
    }

    /**
     * Modal-Funktionen
     */
    function openModal(modalId) {
        const modal = $('#' + modalId);
        if (modal.length) {
            modal.addClass('active');
            $('body').addClass('smp-modal-open');
        }
    }

    function closeModal() {
        $('.smp-modal-overlay').removeClass('active');
        $('body').removeClass('smp-modal-open');
    }

    function fillProductModal(product) {
        $('#productId').val(product.id || '');
        $('#productName').val(product.name || '');
        $('#productPrice').val(product.price || '');
        $('#productCategory').val(product.category || '');
        $('#productStock').val(product.stock || '');
        $('#productBarcode').val(product.barcode || '');
        
        $('#productModalTitle').text(product.id ? 'Produkt bearbeiten' : 'Neues Produkt');
    }

    /**
     * Benachrichtigungen
     */
    function showNotification(type, message) {
        const notification = $(`
            <div class="smp-notification smp-notification-${type}">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="font-size: 1.5rem;">
                        ${type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️'}
                    </span>
                    <div>
                        <div style="font-weight: 600;">
                            ${type === 'success' ? 'Erfolgreich' : type === 'error' ? 'Fehler' : 'Information'}
                        </div>
                        <div style="font-size: 0.875rem; opacity: 0.8;">${message}</div>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(notification);
        
        // Animation
        setTimeout(function() {
            notification.css('transform', 'translateX(0)');
        }, 100);
        
        // Auto-Remove
        setTimeout(function() {
            notification.css('transform', 'translateX(400px)');
            setTimeout(function() {
                notification.remove();
            }, 300);
        }, 3000);
    }

    /**
     * Hilfsfunktionen
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    function getCategoryClass(category) {
        const categoryClasses = {
            'Getränke': 'primary',
            'Snacks': 'secondary',
            'Süßwaren': 'success',
            'Gebäck': 'warning',
            'Sonstiges': 'info'
        };
        return categoryClasses[category] || 'default';
    }

    function updateProductCount() {
        const count = $('#productsTableBody tr').not(':has(.smp-spinner)').length;
        $('#productCount').text(count);
    }

    function updateClock() {
        const now = new Date();
        const time = now.toLocaleTimeString('de-DE', { 
            hour: '2-digit', 
            minute: '2-digit',
            second: '2-digit'
        });
        $('#liveClock').text(time);
    }

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    function handleProductSearch() {
        const searchTerm = $('#productSearch').val().toLowerCase();
        
        $('#productsTableBody tr').each(function() {
            const row = $(this);
            const text = row.text().toLowerCase();
            row.toggle(text.includes(searchTerm));
        });
    }

    function handleCategoryFilter() {
        const category = $('#categoryFilter').val();
        
        $('#productsTableBody tr').each(function() {
            const row = $(this);
            if (category === '') {
                row.show();
            } else {
                const rowCategory = row.find('.smp-badge').text();
                row.toggle(rowCategory === category);
            }
        });
    }

    /**
     * Animationen
     */
    function initializeAnimations() {
        // Intersection Observer für Scroll-Animationen
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('smp-animated');
                }
            });
        }, {
            threshold: 0.1
        });

        // Elemente beobachten
        document.querySelectorAll('.smp-card, .smp-stat-card').forEach(el => {
            observer.observe(el);
        });
    }

    /**
     * Tooltips
     */
    function initializeTooltips() {
        $('[title]').each(function() {
            const $this = $(this);
            const title = $this.attr('title');
            
            $this.removeAttr('title').attr('data-tooltip', title);
            
            $this.on('mouseenter', function() {
                const tooltip = $('<div class="smp-tooltip">' + title + '</div>');
                $('body').append(tooltip);
                
                const pos = $this.offset();
                tooltip.css({
                    top: pos.top - tooltip.outerHeight() - 10,
                    left: pos.left + ($this.outerWidth() / 2) - (tooltip.outerWidth() / 2)
                });
            }).on('mouseleave', function() {
                $('.smp-tooltip').remove();
            });
        });
    }

})(jQuery);