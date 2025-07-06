/**
 * Analytics JavaScript für Snack Manager Pro
 * 
 * @package SnackManagerPro
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Analytics Manager Object
    const AnalyticsManager = {
        // Chart instances
        charts: {
            revenue: null,
            sparklineRevenue: null,
            sparklineTransactions: null,
            hourlyHeatmap: null,
            forecast: null
        },

        // Current data
        currentData: {
            stats: {},
            revenue: {},
            products: [],
            trends: {},
            lastUpdate: null
        },

        // Settings
        settings: {
            updateInterval: 30000, // 30 seconds
            animationDuration: 750,
            colors: {
                primary: '#6366f1',
                secondary: '#ec4899',
                success: '#10b981',
                warning: '#f59e0b',
                danger: '#ef4444',
                info: '#3b82f6'
            }
        },

        // Initialize
        init() {
            this.bindEvents();
            this.initCharts();
            this.loadInitialData();
            this.startLiveUpdates();
        },

        // Bind events
        bindEvents() {
            // Date range changes
            $('#date-start, #date-end').on('change', () => this.loadData());
            
            // Quick period buttons
            $('.btn-period').on('click', function() {
                const days = parseInt($(this).data('period'));
                const endDate = new Date();
                const startDate = new Date();
                startDate.setDate(startDate.getDate() - days);
                
                $('#date-start').val(startDate.toISOString().split('T')[0]);
                $('#date-end').val(endDate.toISOString().split('T')[0]);
                
                $('.btn-period').removeClass('active');
                $(this).addClass('active');
                
                AnalyticsManager.loadData();
            });

            // Period selector for revenue chart
            $('.period-btn').on('click', function() {
                $('.period-btn').removeClass('active');
                $(this).addClass('active');
                AnalyticsManager.loadRevenueData($(this).data('period'));
            });

            // Product limit change
            $('#product-limit').on('change', function() {
                AnalyticsManager.loadProductData();
            });

            // Refresh button
            $('#refresh-analytics').on('click', () => {
                this.animateRefresh();
                this.loadData();
            });

            // Export menu
            $('#export-toggle').on('click', function(e) {
                e.stopPropagation();
                $('#export-menu').toggleClass('show');
            });

            $(document).on('click', function() {
                $('#export-menu').removeClass('show');
            });

            // Export items
            $('.export-item').on('click', function(e) {
                e.preventDefault();
                const type = $(this).data('type');
                const format = $(this).data('format');
                AnalyticsManager.exportData(type, format);
            });

            // Comparison tool
            $('#toggle-comparison').on('click', function() {
                $('#comparison-content').slideToggle();
                $(this).toggleClass('active');
            });

            $('#run-comparison').on('click', () => {
                this.runComparison();
            });

            // Forecast days change
            $('#forecast-days').on('change', function() {
                AnalyticsManager.loadForecastData();
            });
        },

        // Initialize charts
        initCharts() {
            // Chart.js defaults
            Chart.defaults.color = 'rgba(255, 255, 255, 0.8)';
            Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.1)';
            Chart.defaults.font.family = "'Inter', sans-serif";

            // Revenue Chart
            const revenueCtx = document.getElementById('revenue-chart').getContext('2d');
            this.charts.revenue = new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: []
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
                            position: 'top',
                            labels: {
                                padding: 20,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(17, 24, 39, 0.95)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: '#6366f1',
                            borderWidth: 1,
                            padding: 12,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        if (context.dataset.yAxisID === 'y-revenue') {
                                            label += new Intl.NumberFormat('de-DE', {
                                                style: 'currency',
                                                currency: 'EUR'
                                            }).format(context.parsed.y);
                                        } else {
                                            label += context.parsed.y;
                                        }
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                color: 'rgba(255, 255, 255, 0.05)'
                            }
                        },
                        'y-revenue': {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            grid: {
                                color: 'rgba(255, 255, 255, 0.05)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return new Intl.NumberFormat('de-DE', {
                                        style: 'currency',
                                        currency: 'EUR',
                                        minimumFractionDigits: 0
                                    }).format(value);
                                }
                            }
                        },
                        'y-transactions': {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });

            // Sparkline charts
            this.initSparklines();

            // Hourly heatmap
            this.initHourlyHeatmap();

            // Forecast chart
            this.initForecastChart();
        },

        // Initialize sparkline charts
        initSparklines() {
            const sparklineOptions = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: false }
                },
                scales: {
                    x: { display: false },
                    y: { display: false }
                },
                elements: {
                    point: { radius: 0 },
                    line: { borderWidth: 2 }
                }
            };

            // Revenue sparkline
            const revenueSparkCtx = document.getElementById('revenue-sparkline').getContext('2d');
            this.charts.sparklineRevenue = new Chart(revenueSparkCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        data: [],
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        fill: true
                    }]
                },
                options: sparklineOptions
            });

            // Transactions sparkline
            const transSparkCtx = document.getElementById('transactions-sparkline').getContext('2d');
            this.charts.sparklineTransactions = new Chart(transSparkCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        data: [],
                        borderColor: '#ec4899',
                        backgroundColor: 'rgba(236, 72, 153, 0.1)',
                        fill: true
                    }]
                },
                options: sparklineOptions
            });
        },

        // Initialize hourly heatmap
        initHourlyHeatmap() {
            const heatmapCtx = document.getElementById('hourly-heatmap').getContext('2d');
            this.charts.hourlyHeatmap = new Chart(heatmapCtx, {
                type: 'bar',
                data: {
                    labels: Array.from({length: 24}, (_, i) => i + ':00'),
                    datasets: [{
                        label: 'Umsatz',
                        data: [],
                        backgroundColor: (context) => {
                            const value = context.parsed.y;
                            const max = Math.max(...context.dataset.data);
                            const intensity = value / max;
                            return `rgba(99, 102, 241, ${0.2 + intensity * 0.8})`;
                        }
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return new Intl.NumberFormat('de-DE', {
                                        style: 'currency',
                                        currency: 'EUR'
                                    }).format(context.parsed.y);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            display: false
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        },

        // Initialize forecast chart
        initForecastChart() {
            const forecastCtx = document.getElementById('forecast-chart').getContext('2d');
            this.charts.forecast = new Chart(forecastCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: []
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    label += new Intl.NumberFormat('de-DE', {
                                        style: 'currency',
                                        currency: 'EUR'
                                    }).format(context.parsed.y);
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            ticks: {
                                callback: function(value) {
                                    return new Intl.NumberFormat('de-DE', {
                                        style: 'currency',
                                        currency: 'EUR',
                                        minimumFractionDigits: 0
                                    }).format(value);
                                }
                            }
                        }
                    }
                }
            });
        },

        // Load initial data
        loadInitialData() {
            this.showLoading();
            this.loadData();
        },

        // Load all data
        async loadData() {
            try {
                await Promise.all([
                    this.loadDashboardStats(),
                    this.loadRevenueData($('.period-btn.active').data('period') || 'daily'),
                    this.loadProductData(),
                    this.loadSalesTrends(),
                    this.loadForecastData()
                ]);
                this.hideLoading();
            } catch (error) {
                console.error('Error loading analytics data:', error);
                this.showError();
            }
        },

        // Load dashboard statistics
        async loadDashboardStats() {
            const response = await $.ajax({
                url: smpAnalytics.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smp_get_dashboard_stats',
                    nonce: smpAnalytics.nonce
                }
            });

            if (response.success) {
                this.updateDashboardStats(response.data);
            }
        },

        // Load revenue data
        async loadRevenueData(period = 'daily') {
            const response = await $.ajax({
                url: smpAnalytics.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smp_get_revenue_data',
                    period: period,
                    start_date: $('#date-start').val(),
                    end_date: $('#date-end').val(),
                    nonce: smpAnalytics.nonce
                }
            });

            if (response.success) {
                this.updateRevenueChart(response.data);
                this.updateRevenueSummary(response.data.summary);
            }
        },

        // Load product performance data
        async loadProductData() {
            const response = await $.ajax({
                url: smpAnalytics.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smp_get_product_performance',
                    start_date: $('#date-start').val(),
                    end_date: $('#date-end').val(),
                    limit: $('#product-limit').val(),
                    nonce: smpAnalytics.nonce
                }
            });

            if (response.success) {
                this.renderTopProducts(response.data);
            }
        },

        // Load sales trends
        async loadSalesTrends() {
            const response = await $.ajax({
                url: smpAnalytics.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smp_get_sales_trends',
                    period: $('.period-btn.active').data('period') || 'weekly',
                    nonce: smpAnalytics.nonce
                }
            });

            if (response.success) {
                this.updateSalesTrends(response.data);
            }
        },

        // Load forecast data
        async loadForecastData() {
            const response = await $.ajax({
                url: smpAnalytics.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smp_get_forecast_data',
                    days_ahead: $('#forecast-days').val(),
                    nonce: smpAnalytics.nonce
                }
            });

            if (response.success) {
                this.updateForecastChart(response.data);
            }
        },

        // Update dashboard statistics
        updateDashboardStats(data) {
            // Today's revenue
            $('#today-revenue').text(this.formatCurrency(data.today.revenue));
            $('#today-transactions').text(data.today.transactions);
            $('#avg-transaction').text(this.formatCurrency(data.today.avg_transaction));

            // Changes
            this.updateChangeIndicator('#today-change', data.today.change);
            this.updateChangeIndicator('#transactions-change', data.today.change);

            // Products
            $('#total-products').text(data.products.total);
            $('#in-stock').text(data.products.in_stock);
            $('#low-stock').text(data.products.low_stock);
            $('#out-stock').text(data.products.out_of_stock);

            // Update sparklines
            this.updateSparklines(data);

            // Store current data
            this.currentData.stats = data;
            this.currentData.lastUpdate = data.last_update;
        },

        // Update change indicator
        updateChangeIndicator(selector, change) {
            const $element = $(selector);
            $element.removeClass('positive negative neutral');
            
            if (change > 0) {
                $element.addClass('positive');
                $element.find('i').attr('class', 'fas fa-arrow-up');
                $element.find('span').text(`+${change.toFixed(1)}%`);
            } else if (change < 0) {
                $element.addClass('negative');
                $element.find('i').attr('class', 'fas fa-arrow-down');
                $element.find('span').text(`${change.toFixed(1)}%`);
            } else {
                $element.addClass('neutral');
                $element.find('i').attr('class', 'fas fa-equals');
                $element.find('span').text('0%');
            }
        },

        // Update revenue chart
        updateRevenueChart(data) {
            this.charts.revenue.data.labels = data.labels;
            this.charts.revenue.data.datasets = data.datasets;
            this.charts.revenue.update('active');
        },

        // Update revenue summary
        updateRevenueSummary(summary) {
            $('#total-revenue').text(this.formatCurrency(summary.total_revenue));
            $('#total-transactions').text(summary.total_transactions);
            $('#avg-value').text(this.formatCurrency(summary.avg_transaction_value));
        },

        // Render top products
        renderTopProducts(products) {
            const $container = $('#top-products');
            $container.empty();

            products.forEach((product, index) => {
                const trendClass = product.trend > 0 ? 'positive' : product.trend < 0 ? 'negative' : 'neutral';
                const trendIcon = product.trend > 0 ? 'fa-arrow-up' : product.trend < 0 ? 'fa-arrow-down' : 'fa-equals';
                
                const html = `
                    <div class="product-item" data-aos="fade-up" data-aos-delay="${index * 50}">
                        <div class="product-rank">#${index + 1}</div>
                        <div class="product-info">
                            <h4>${product.name}</h4>
                            <p class="product-meta">
                                <span class="category">${product.category}</span>
                                <span class="barcode">${product.barcode}</span>
                            </p>
                        </div>
                        <div class="product-stats">
                            <div class="stat">
                                <span class="label">Umsatz</span>
                                <span class="value">${this.formatCurrency(product.total_revenue)}</span>
                            </div>
                            <div class="stat">
                                <span class="label">Verkäufe</span>
                                <span class="value">${product.sales_count}</span>
                            </div>
                            <div class="stat">
                                <span class="label">Anteil</span>
                                <span class="value">${product.revenue_share.toFixed(1)}%</span>
                            </div>
                        </div>
                        <div class="product-trend ${trendClass}">
                            <i class="fas ${trendIcon}"></i>
                            <span>${Math.abs(product.trend).toFixed(1)}%</span>
                        </div>
                    </div>
                `;
                $container.append(html);
            });
        },

        // Update sales trends
        updateSalesTrends(data) {
            // Update hourly heatmap
            const hourlyRevenue = data.hourly_pattern.map(h => h.revenue);
            this.charts.hourlyHeatmap.data.datasets[0].data = hourlyRevenue;
            this.charts.hourlyHeatmap.update('active');

            // Update peak hours
            const $peakHours = $('#peak-hours');
            $peakHours.empty();
            data.peak_hours.forEach(hour => {
                $peakHours.append(`<span class="peak-badge">${hour}:00 - ${hour + 1}:00</span>`);
            });

            // Update peak days
            const $peakDays = $('#peak-days');
            $peakDays.empty();
            data.peak_days.forEach(day => {
                $peakDays.append(`<span class="peak-badge">${day}</span>`);
            });

            // Update growth trend
            const $growth = $('#growth-trend');
            const growth = data.growth_trend;
            $growth.removeClass('positive negative neutral');
            
            if (growth > 0) {
                $growth.addClass('positive');
                $growth.find('i').attr('class', 'fas fa-arrow-up');
                $growth.find('.growth-value').text(`+${growth.toFixed(1)}%`);
            } else if (growth < 0) {
                $growth.addClass('negative');
                $growth.find('i').attr('class', 'fas fa-arrow-down');
                $growth.find('.growth-value').text(`${growth.toFixed(1)}%`);
            } else {
                $growth.addClass('neutral');
                $growth.find('i').attr('class', 'fas fa-equals');
                $growth.find('.growth-value').text('0%');
            }
        },

        // Update forecast chart
        updateForecastChart(data) {
            const labels = [];
            const historical = [];
            const forecast = [];
            const lowerBound = [];
            const upperBound = [];

            // Historical data
            data.historical.forEach(point => {
                labels.push(this.formatDate(point.date));
                historical.push(point.daily_revenue);
                forecast.push(null);
                lowerBound.push(null);
                upperBound.push(null);
            });

            // Forecast data
            data.forecast.forEach(point => {
                labels.push(this.formatDate(point.date));
                historical.push(null);
                forecast.push(point.revenue);
                lowerBound.push(point.confidence_lower);
                upperBound.push(point.confidence_upper);
            });

            this.charts.forecast.data.labels = labels;
            this.charts.forecast.data.datasets = [
                {
                    label: 'Historisch',
                    data: historical,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    fill: false
                },
                {
                    label: 'Prognose',
                    data: forecast,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderDash: [5, 5],
                    fill: false
                },
                {
                    label: 'Konfidenzbereich',
                    data: upperBound,
                    borderColor: 'rgba(16, 185, 129, 0.3)',
                    backgroundColor: 'rgba(16, 185, 129, 0.05)',
                    fill: '+1',
                    pointRadius: 0
                },
                {
                    label: '',
                    data: lowerBound,
                    borderColor: 'rgba(16, 185, 129, 0.3)',
                    backgroundColor: 'rgba(16, 185, 129, 0.05)',
                    fill: false,
                    pointRadius: 0
                }
            ];

            this.charts.forecast.update('active');

            // Update accuracy
            $('#forecast-accuracy').text(`${data.accuracy}%`);
        },

        // Update sparklines
        updateSparklines(data) {
            // Generate last 7 days of data (mock for now)
            const days = 7;
            const labels = [];
            const revenueData = [];
            const transactionData = [];

            for (let i = days - 1; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                labels.push(date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit' }));
                
                // Mock data - in real implementation, this would come from the server
                revenueData.push(Math.random() * 500 + 100);
                transactionData.push(Math.floor(Math.random() * 50 + 10));
            }

            this.charts.sparklineRevenue.data.labels = labels;
            this.charts.sparklineRevenue.data.datasets[0].data = revenueData;
            this.charts.sparklineRevenue.update('none');

            this.charts.sparklineTransactions.data.labels = labels;
            this.charts.sparklineTransactions.data.datasets[0].data = transactionData;
            this.charts.sparklineTransactions.update('none');
        },

        // Run comparison
        async runComparison() {
            const period1Start = $('#compare-start-1').val();
            const period1End = $('#compare-end-1').val();
            const period2Start = $('#compare-start-2').val();
            const period2End = $('#compare-end-2').val();

            if (!period1Start || !period1End || !period2Start || !period2End) {
                this.showNotification('Bitte alle Datumsfelder ausfüllen', 'warning');
                return;
            }

            this.showLoading();

            try {
                const response = await $.ajax({
                    url: smpAnalytics.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'smp_get_comparison_data',
                        period1_start: period1Start,
                        period1_end: period1End,
                        period2_start: period2Start,
                        period2_end: period2End,
                        nonce: smpAnalytics.nonce
                    }
                });

                if (response.success) {
                    this.renderComparisonResults(response.data);
                }
            } catch (error) {
                console.error('Comparison error:', error);
                this.showError();
            } finally {
                this.hideLoading();
            }
        },

        // Render comparison results
        renderComparisonResults(data) {
            const $results = $('#comparison-results');
            
            const html = `
                <div class="comparison-grid">
                    <div class="comparison-metric">
                        <h4>Umsatz</h4>
                        <div class="metric-values">
                            <div class="period-value">
                                <span class="label">Zeitraum 1</span>
                                <span class="value">${this.formatCurrency(data.revenue.period1)}</span>
                            </div>
                            <div class="change-indicator ${data.revenue.percentage > 0 ? 'positive' : data.revenue.percentage < 0 ? 'negative' : 'neutral'}">
                                <i class="fas ${data.revenue.percentage > 0 ? 'fa-arrow-up' : data.revenue.percentage < 0 ? 'fa-arrow-down' : 'fa-equals'}"></i>
                                <span>${data.revenue.percentage > 0 ? '+' : ''}${data.revenue.percentage.toFixed(1)}%</span>
                            </div>
                            <div class="period-value">
                                <span class="label">Zeitraum 2</span>
                                <span class="value">${this.formatCurrency(data.revenue.period2)}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="comparison-metric">
                        <h4>Transaktionen</h4>
                        <div class="metric-values">
                            <div class="period-value">
                                <span class="label">Zeitraum 1</span>
                                <span class="value">${data.transactions.period1}</span>
                            </div>
                            <div class="change-indicator ${data.transactions.percentage > 0 ? 'positive' : data.transactions.percentage < 0 ? 'negative' : 'neutral'}">
                                <i class="fas ${data.transactions.percentage > 0 ? 'fa-arrow-up' : data.transactions.percentage < 0 ? 'fa-arrow-down' : 'fa-equals'}"></i>
                                <span>${data.transactions.percentage > 0 ? '+' : ''}${data.transactions.percentage.toFixed(1)}%</span>
                            </div>
                            <div class="period-value">
                                <span class="label">Zeitraum 2</span>
                                <span class="value">${data.transactions.period2}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="comparison-metric">
                        <h4>Ø Transaktionswert</h4>
                        <div class="metric-values">
                            <div class="period-value">
                                <span class="label">Zeitraum 1</span>
                                <span class="value">${this.formatCurrency(data.avg_transaction.period1)}</span>
                            </div>
                            <div class="change-indicator ${data.avg_transaction.percentage > 0 ? 'positive' : data.avg_transaction.percentage < 0 ? 'negative' : 'neutral'}">
                                <i class="fas ${data.avg_transaction.percentage > 0 ? 'fa-arrow-up' : data.avg_transaction.percentage < 0 ? 'fa-arrow-down' : 'fa-equals'}"></i>
                                <span>${data.avg_transaction.percentage > 0 ? '+' : ''}${data.avg_transaction.percentage.toFixed(1)}%</span>
                            </div>
                            <div class="period-value">
                                <span class="label">Zeitraum 2</span>
                                <span class="value">${this.formatCurrency(data.avg_transaction.period2)}</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="comparison-products">
                    <div class="products-period">
                        <h4>Top Produkte - Zeitraum 1</h4>
                        <ul>
                            ${data.top_products.period1.map(p => `
                                <li>${p.name} - ${this.formatCurrency(p.revenue)}</li>
                            `).join('')}
                        </ul>
                    </div>
                    <div class="products-period">
                        <h4>Top Produkte - Zeitraum 2</h4>
                        <ul>
                            ${data.top_products.period2.map(p => `
                                <li>${p.name} - ${this.formatCurrency(p.revenue)}</li>
                            `).join('')}
                        </ul>
                    </div>
                </div>
            `;
            
            $results.html(html).hide().fadeIn();
        },

        // Export data
        exportData(type, format) {
            const startDate = $('#date-start').val();
            const endDate = $('#date-end').val();
            
            // Create form and submit
            const form = $('<form>', {
                method: 'POST',
                action: smpAnalytics.ajaxUrl
            });
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'action',
                value: 'smp_export_analytics'
            }));
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'nonce',
                value: smpAnalytics.nonce
            }));
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'format',
                value: format
            }));
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'data_type',
                value: type
            }));
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'start_date',
                value: startDate
            }));
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'end_date',
                value: endDate
            }));
            
            $('body').append(form);
            form.submit();
            form.remove();
        },

        // Start live updates
        startLiveUpdates() {
            setInterval(() => {
                this.checkForUpdates();
            }, this.settings.updateInterval);
        },

        // Check for updates
        async checkForUpdates() {
            if (!this.currentData.lastUpdate) return;

            try {
                const response = await $.ajax({
                    url: smpAnalytics.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'smp_get_realtime_updates',
                        last_update: this.currentData.lastUpdate,
                        nonce: smpAnalytics.nonce
                    }
                });

                if (response.success && response.data.new_transactions.length > 0) {
                    this.handleRealtimeUpdates(response.data);
                    this.flashLiveIndicator();
                }
            } catch (error) {
                console.error('Live update error:', error);
            }
        },

        // Handle realtime updates
        handleRealtimeUpdates(data) {
            // Update stats
            this.updateDashboardStats(data.stats);

            // Show notification for new transactions
            if (data.new_transactions.length > 0) {
                const latest = data.new_transactions[0];
                this.showNotification(
                    `Neuer Verkauf: ${latest.product_name} - ${this.formatCurrency(latest.amount)}`,
                    'success'
                );
            }

            // Refresh charts with smooth animation
            this.loadRevenueData($('.period-btn.active').data('period') || 'daily');
            this.loadProductData();
        },

        // Utility functions
        formatCurrency(value) {
            return new Intl.NumberFormat('de-DE', {
                style: 'currency',
                currency: 'EUR'
            }).format(value || 0);
        },

        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('de-DE', {
                day: '2-digit',
                month: '2-digit'
            });
        },

        showLoading() {
            $('#analytics-loading').fadeIn();
        },

        hideLoading() {
            $('#analytics-loading').fadeOut();
        },

        showError() {
            this.showNotification(smpAnalytics.labels.error, 'error');
        },

        showNotification(message, type = 'info') {
            const notification = $(`
                <div class="analytics-notification ${type}">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
                    <span>${message}</span>
                </div>
            `);

            $('body').append(notification);
            
            setTimeout(() => {
                notification.addClass('show');
            }, 100);

            setTimeout(() => {
                notification.removeClass('show');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        },

        animateRefresh() {
            const $icon = $('#refresh-analytics i');
            $icon.addClass('fa-spin');
            setTimeout(() => $icon.removeClass('fa-spin'), 1000);
        },

        flashLiveIndicator() {
            const $indicator = $('#live-indicator');
            $indicator.addClass('flash');
            setTimeout(() => $indicator.removeClass('flash'), 1000);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        // Check if we're on the analytics page
        if ($('.smp-analytics-container').length > 0) {
            AnalyticsManager.init();
        }
    });

})(jQuery);