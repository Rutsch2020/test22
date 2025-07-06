/**
 * Voice Controller JavaScript
 * 
 * @package SnackManagerPro
 * @since 1.0.0
 */

(function($) {
    'use strict';

    const SnackVoiceController = {
        
        // Properties
        recognition: null,
        synthesis: window.speechSynthesis,
        isListening: false,
        isProcessing: false,
        language: 'de-DE',
        continuous: false,
        interimResults: true,
        
        // UI Elements
        elements: {
            toggleBtn: null,
            statusText: null,
            transcript: null,
            voiceIcon: null,
            languageSelect: null,
            widget: null
        },
        
        // Initialize
        init: function() {
            // Check browser support
            if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
                console.error('Speech recognition not supported');
                this.showNotification('error', 'Speech recognition is not supported in your browser');
                return;
            }
            
            // Set language from localized data
            this.language = snackVoice.language || 'de-DE';
            
            // Initialize speech recognition
            this.initSpeechRecognition();
            
            // Cache elements
            this.cacheElements();
            
            // Bind events
            this.bindEvents();
            
            // Add voice widget to page if not exists
            this.injectVoiceWidget();
        },
        
        // Initialize speech recognition
        initSpeechRecognition: function() {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            this.recognition = new SpeechRecognition();
            
            // Configure recognition
            this.recognition.lang = this.language;
            this.recognition.continuous = this.continuous;
            this.recognition.interimResults = this.interimResults;
            this.recognition.maxAlternatives = 3;
            
            // Recognition event handlers
            this.recognition.onstart = () => this.onRecognitionStart();
            this.recognition.onresult = (event) => this.onRecognitionResult(event);
            this.recognition.onerror = (event) => this.onRecognitionError(event);
            this.recognition.onend = () => this.onRecognitionEnd();
        },
        
        // Cache DOM elements
        cacheElements: function() {
            this.elements.toggleBtn = $('#voice-toggle');
            this.elements.statusText = $('.voice-status-text');
            this.elements.transcript = $('#voice-transcript');
            this.elements.voiceIcon = $('.voice-icon');
            this.elements.languageSelect = $('#voice-language');
            this.elements.widget = $('.voice-control-widget');
        },
        
        // Bind events
        bindEvents: function() {
            // Toggle button click
            $(document).on('click', '#voice-toggle', (e) => {
                e.preventDefault();
                this.toggleListening();
            });
            
            // Language change
            $(document).on('change', '#voice-language', (e) => {
                this.changeLanguage($(e.target).val());
            });
            
            // Keyboard shortcut (Ctrl/Cmd + Shift + V)
            $(document).on('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'V') {
                    e.preventDefault();
                    this.toggleListening();
                }
            });
            
            // Click outside to stop
            $(document).on('click', (e) => {
                if (this.isListening && !$(e.target).closest('.voice-control-widget').length) {
                    this.stopListening();
                }
            });
        },
        
        // Inject voice widget if not exists
        injectVoiceWidget: function() {
            if ($('.voice-control-widget').length === 0) {
                const widgetHtml = `
                    <div class="voice-control-widget" style="display: none;">
                        <div class="voice-status">
                            <div class="voice-icon">
                                <svg class="voice-wave" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M12 1v22M17 4v16M7 8v8M22 7v10M2 7v10" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </div>
                            <span class="voice-status-text">${snackVoice.strings.listening}</span>
                        </div>
                        
                        <button type="button" class="voice-toggle-btn" id="voice-toggle">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
                                <path d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v4M8 23h8"/>
                            </svg>
                        </button>
                        
                        <div class="voice-transcript" id="voice-transcript"></div>
                        
                        <div class="voice-settings">
                            <label>Language:</label>
                            <select id="voice-language" class="voice-language-select">
                                <option value="de-DE" ${this.language === 'de-DE' ? 'selected' : ''}>de-DE</option>
                                <option value="en-US" ${this.language === 'en-US' ? 'selected' : ''}>en-US</option>
                            </select>
                        </div>
                    </div>
                `;
                
                $('body').append(widgetHtml);
                this.cacheElements();
            }
            
            // Show widget
            this.elements.widget.fadeIn(300);
        },
        
        // Toggle listening
        toggleListening: function() {
            if (this.isListening) {
                this.stopListening();
            } else {
                this.startListening();
            }
        },
        
        // Start listening
        startListening: function() {
            if (this.isProcessing) return;
            
            try {
                this.recognition.start();
                this.isListening = true;
                this.updateUI('listening');
                
                // Add listening class
                this.elements.widget.addClass('listening');
                
                // Vibrate if supported
                if ('vibrate' in navigator) {
                    navigator.vibrate(50);
                }
                
                // Auto-stop after 30 seconds
                this.autoStopTimer = setTimeout(() => {
                    this.stopListening();
                }, 30000);
                
            } catch (error) {
                console.error('Error starting recognition:', error);
                this.showNotification('error', 'Could not start voice recognition');
            }
        },
        
        // Stop listening
        stopListening: function() {
            if (!this.isListening) return;
            
            try {
                this.recognition.stop();
                this.isListening = false;
                this.updateUI('idle');
                
                // Remove listening class
                this.elements.widget.removeClass('listening');
                
                // Clear auto-stop timer
                if (this.autoStopTimer) {
                    clearTimeout(this.autoStopTimer);
                }
                
            } catch (error) {
                console.error('Error stopping recognition:', error);
            }
        },
        
        // Recognition started
        onRecognitionStart: function() {
            if (snackVoice.debug) {
                console.log('Recognition started');
            }
            
            this.elements.transcript.text('').show();
            this.playSound('start');
        },
        
        // Recognition result
        onRecognitionResult: function(event) {
            let finalTranscript = '';
            let interimTranscript = '';
            
            for (let i = event.resultIndex; i < event.results.length; i++) {
                const result = event.results[i];
                
                if (result.isFinal) {
                    finalTranscript += result[0].transcript;
                } else {
                    interimTranscript += result[0].transcript;
                }
            }
            
            // Update transcript display
            this.elements.transcript.html(
                `<span class="final">${finalTranscript}</span>` +
                `<span class="interim">${interimTranscript}</span>`
            );
            
            // Process final command
            if (finalTranscript) {
                this.processCommand(finalTranscript.trim());
            }
        },
        
        // Recognition error
        onRecognitionError: function(event) {
            console.error('Recognition error:', event.error);
            
            let message = 'Voice recognition error';
            
            switch (event.error) {
                case 'no-speech':
                    message = this.language === 'de-DE' ? 'Keine Sprache erkannt' : 'No speech detected';
                    break;
                case 'audio-capture':
                    message = this.language === 'de-DE' ? 'Kein Mikrofon gefunden' : 'No microphone found';
                    break;
                case 'not-allowed':
                    message = this.language === 'de-DE' ? 'Mikrofonzugriff verweigert' : 'Microphone access denied';
                    break;
            }
            
            this.showNotification('error', message);
            this.stopListening();
        },
        
        // Recognition ended
        onRecognitionEnd: function() {
            if (snackVoice.debug) {
                console.log('Recognition ended');
            }
            
            // Restart if still listening (for continuous mode)
            if (this.isListening && this.continuous) {
                setTimeout(() => {
                    try {
                        this.recognition.start();
                    } catch (error) {
                        console.error('Error restarting recognition:', error);
                    }
                }, 100);
            }
        },
        
        // Process command
        processCommand: function(command) {
            if (this.isProcessing) return;
            
            this.isProcessing = true;
            this.updateUI('processing');
            
            // Stop listening while processing
            this.stopListening();
            
            // Send command to server
            $.ajax({
                url: snackVoice.ajax_url,
                type: 'POST',
                data: {
                    action: 'snack_process_voice_command',
                    command: command,
                    nonce: snackVoice.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.handleCommandResponse(response.data);
                    } else {
                        this.showNotification('error', response.data.message || 'Command failed');
                        if (response.data.speak) {
                            this.speak(response.data.message);
                        }
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX error:', error);
                    this.showNotification('error', 'Failed to process command');
                },
                complete: () => {
                    this.isProcessing = false;
                    this.updateUI('idle');
                    
                    // Hide transcript after delay
                    setTimeout(() => {
                        this.elements.transcript.fadeOut();
                    }, 3000);
                }
            });
        },
        
        // Handle command response
        handleCommandResponse: function(data) {
            // Show notification
            this.showNotification('success', data.message);
            
            // Speak response if enabled
            if (data.speak) {
                this.speak(data.message);
            }
            
            // Play success sound
            this.playSound('success');
            
            // Refresh UI if needed
            if (data.refresh) {
                this.refreshDashboard();
            }
            
            // Handle specific actions
            switch (data.action) {
                case 'sale':
                case 'refill':
                    // Update product card if visible
                    if (data.data.product_id) {
                        this.updateProductCard(data.data.product_id, data.data.new_stock);
                    }
                    break;
                    
                case 'statistics':
                    // Could open statistics modal
                    break;
            }
        },
        
        // Text to speech
        speak: function(text) {
            if (!this.synthesis || !text) return;
            
            // Cancel any ongoing speech
            this.synthesis.cancel();
            
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = this.language;
            utterance.rate = 1.0;
            utterance.pitch = 1.0;
            utterance.volume = 0.8;
            
            this.synthesis.speak(utterance);
        },
        
        // Change language
        changeLanguage: function(language) {
            this.language = language;
            this.recognition.lang = language;
            
            // Update server
            $.ajax({
                url: snackVoice.ajax_url,
                type: 'POST',
                data: {
                    action: 'snack_update_voice_language',
                    language: language,
                    nonce: snackVoice.nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Update strings
                        snackVoice.strings = response.data.strings;
                        this.elements.statusText.text(snackVoice.strings.listening);
                        
                        this.showNotification('success', 'Language updated');
                    }
                }
            });
        },
        
        // Update UI
        updateUI: function(state) {
            const widget = this.elements.widget;
            const icon = this.elements.voiceIcon;
            const statusText = this.elements.statusText;
            
            // Remove all state classes
            widget.removeClass('listening processing');
            
            switch (state) {
                case 'listening':
                    widget.addClass('listening');
                    statusText.text(snackVoice.strings.listening);
                    icon.addClass('pulse');
                    break;
                    
                case 'processing':
                    widget.addClass('processing');
                    statusText.text(snackVoice.strings.processing);
                    icon.removeClass('pulse');
                    break;
                    
                case 'idle':
                default:
                    statusText.text('');
                    icon.removeClass('pulse');
                    break;
            }
        },
        
        // Show notification
        showNotification: function(type, message) {
            const notification = $(`
                <div class="snack-notification ${type}">
                    <span>${message}</span>
                </div>
            `);
            
            $('body').append(notification);
            
            // Animate in
            setTimeout(() => {
                notification.addClass('show');
            }, 10);
            
            // Auto remove
            setTimeout(() => {
                notification.removeClass('show');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        },
        
        // Play sound
        playSound: function(type) {
            const audio = new Audio();
            
            switch (type) {
                case 'start':
                    // Simple beep sound using data URI
                    audio.src = 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZijYIG2m98OScTgwOUarm7blmFgU7k9n1x3IkBCh+zPLaizsIGmS57OihUBELTKXh8b1pGAY9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKSabh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREMUKfh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREMUKfh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREMUKfh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREMUKfh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREMUKfh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREMUKfh8b1pGAU9k9n1x3IkBSiAzPDaizsIG2m578mjUREMUKfh8b1pGAU9k9n1x3IkBSiAzPDaizsIG2m578mjUREMUKfh8b1pGAU9k9n1x3IkBSiAzPDaizsIG2m578mjUREMUKfh8b1pGAU9k9n1x3IkBSiAzPDaizsIG2m578mjUREMUKfh8b1pGAU9k9n1x3IkBSiAzPDaizsIG2m578mjUREMUKfh8b1pGAU9k9n1x3IkBSiAzPDaizsIG2m578mjUREMUKfh8b1pGAU9k9n1x3IkBSiAzPDaizsIG2m578mjUREMUKfh8b1pGAU9k9n1x3IkBSiAzPDaizsIG2m578mjUREMUKfh8b1pGAU9k9n1x3IkBSiAzPDaizsIG2m578mjUREMUKfh8b1pGAU9k9n1x3IkBSiAzPDaizsIG2m578mjUREMUKfh8b1pGAU9k9n1x3IkBSiAzPDaizsIG2m578mjUREMUKfh8b1pGAU9k9n1x3IkBSiAzPDaizsIG2m578mjUREMUKfh8b1pGAU9k9n1x3IkBSiAzPDaizsIG2m578mjUA==';
                    break;
                    
                case 'success':
                    // Success sound
                    audio.src = 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZijYIG2m98OScTgwOUarm7blmFgU7k9n1x3IkBCh+zPLaizsIGmS57OihUBELTKXh8b1pGAY9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKTKXh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREKSabh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREMUKfh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREMUKfh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREMUKfh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREMUKfh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREMUKfh8b1pGAU9k9n1x3IkBSh+zPDaizsIGmS578mjUREMUKfh8b1pGAU9k9n1x3IkBSiAzPDaizsIG2m578mjUREMUKfh8b1pGAU9k9n1x3IkBSiAzPDaizsIG2m578mjUREMUKfh8b1pGAU9k9n1x3IkBSiAzPDaizsIG2m578mjUREMUKfh8b1pGAU9k9n1x3IkBSiAzPDaizsIG2m578mjUREMUKfh8b1pGAU9k9n1x3IkBSiAzPDaizsIG2m578mjUREMUKfh8b1pGAU9k9n1x3IkBSiAzPDaizsIG2m578mjUREMUKfh8b1pGAU9k9n1x3IkBSiAzPDaizsIG2m578mjUREMUKfh8b1pGAU9k9n1x3IkBSiAzPDaizsIG2m578mjUREMUKfh8b1pGAU9k9n1x3IkBSiAzPDaizsIG2m578mjUREMUKfh8b1pGAU9k9n1x3IkBSiAzPDaizsIG2m578mjUREMUKfh8b1pGAU9k9n1x3IkBSiAzPDaizsIG2m578mjUREMUKfh8b1pGAU9k9n1x3IkBSiAzPDaizsIG2m578mjUREMUKfh8b1pGAU9k9n1x3IkBSiAzPDaizsIG2m578mjUA==';
                    break;
            }
            
            audio.volume = 0.3;
            audio.play().catch(e => console.log('Could not play sound:', e));
        },
        
        // Update product card
        updateProductCard: function(productId, newStock) {
            const card = $(`.product-card[data-product-id="${productId}"]`);
            if (card.length) {
                const stockElement = card.find('.product-stock');
                const oldStock = parseInt(stockElement.text());
                
                // Update stock with animation
                stockElement.addClass('updating');
                setTimeout(() => {
                    stockElement.text(newStock);
                    stockElement.removeClass('updating');
                    
                    // Add low stock warning if needed
                    if (newStock <= 5 && oldStock > 5) {
                        card.addClass('low-stock');
                    } else if (newStock > 5 && oldStock <= 5) {
                        card.removeClass('low-stock');
                    }
                }, 300);
            }
        },
        
        // Refresh dashboard
        refreshDashboard: function() {
            // Trigger dashboard refresh if available
            if (window.SnackDashboard && typeof window.SnackDashboard.refresh === 'function') {
                window.SnackDashboard.refresh();
            }
            
            // Or reload specific sections
            $('.dashboard-stats').load(window.location.href + ' .dashboard-stats > *');
            $('.recent-transactions').load(window.location.href + ' .recent-transactions > *');
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        SnackVoiceController.init();
        
        // Export to global scope for debugging
        window.SnackVoiceController = SnackVoiceController;
    });
    
})(jQuery);