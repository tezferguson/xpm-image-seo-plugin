/**
 * XPM Image SEO Admin JavaScript - COMPLETE FIXED VERSION WITH WORKING TABS
 */
jQuery(document).ready(function($) {
    'use strict';
    
    // Determine current page
    const currentPage = xpmImageSeo.current_page;
    
    // Initialize tab functionality for settings page
    if (currentPage === 'settings_page_xpm-image-seo-settings') {
        initializeSettingsTabs();
    }
    
    if (currentPage === 'media_page_xpm-image-seo-optimizer') {
        initializeOptimizer();
    } else if (currentPage === 'media_page_xpm-image-seo-bulk-update') {
        initializeBulkUpdate();
    }
    
    /**
     * Initialize Settings Page Tabs - NEW FUNCTION
     */
    function initializeSettingsTabs() {
        console.log('XPM Image SEO: Initializing settings tabs');
        
        // Tab switching functionality
        $('.xpm-nav-tab-wrapper .nav-tab').on('click', function(e) {
            e.preventDefault();
            
            var targetTab = $(this).data('tab');
            console.log('Switching to tab:', targetTab);
            
            // Remove active class from all tabs and content
            $('.xpm-nav-tab-wrapper .nav-tab').removeClass('nav-tab-active');
            $('.xpm-tab-content').removeClass('active').hide();
            
            // Add active class to clicked tab and corresponding content
            $(this).addClass('nav-tab-active');
            $('#' + targetTab + '_content').addClass('active').show();
            
            // Update URL without page reload
            if (history.replaceState) {
                var url = new URL(window.location);
                url.searchParams.set('tab', targetTab);
                window.history.replaceState({}, '', url);
            }
        });
        
        // Handle form submission for settings
        $('#xmp-settings-form').on('submit', function(e) {
            var $submitBtn = $(this).find('input[type=submit]');
            $submitBtn.prop('disabled', true);
            
            // Change button text based on active tab
            var activeTab = $('.nav-tab-active').data('tab');
            var buttonText = 'Saving...';
            
            switch(activeTab) {
                case 'alt_text':
                    buttonText = 'Saving Alt Text Settings...';
                    break;
                case 'optimization':
                    buttonText = 'Saving Optimization Settings...';
                    break;
                case 'performance':
                    buttonText = 'Saving Performance Settings...';
                    break;
            }
            
            $submitBtn.val(buttonText);
            
            // Re-enable after a delay (in case of error)
            setTimeout(function() {
                $submitBtn.prop('disabled', false).val('Save Settings');
            }, 5000);
        });
        
        // Initialize compression quality slider
        $('.compression-slider').on('input', function() {
            $('.quality-display').text($(this).val() + '%');
        });
        
        // Initialize custom placeholder field toggle
        $('select[name$="[lazy_loading_placeholder]"]').on('change', function() {
            var customField = $('#custom-placeholder-field');
            if ($(this).val() === 'custom') {
                customField.show();
            } else {
                customField.hide();
            }
        });
        
        console.log('Settings tabs initialized successfully');
    }
    
    /**
     * Initialize Image Optimizer
     */
    function initializeOptimizer() {
        let isOptimizing = false;
        let optimizeQueue = [];
        let currentIndex = 0;
        let startTime = null;
        let totalSavings = 0;
        let totalOriginalSize = 0;
        
        // Cache DOM elements
        const $scanButton = $('#xpm-scan-unoptimized');
        const $optimizeStartButton = $('#xpm-optimize-start');
        const $optimizeStopButton = $('#xpm-optimize-stop');
        const $restoreButton = $('#xpm-restore-images');
        const $optimizerStats = $('#xpm-optimizer-stats');
        const $optimizerResults = $('#xpm-optimizer-results');
        const $optimizerPreview = $('#xpm-optimizer-preview');
        const $optimizerCount = $('#xpm-optimizer-count');
        const $optimizerProgress = $('#xpm-optimizer-progress');
        const $optimizerProgressFill = $('#xpm-optimizer-progress-fill');
        const $optimizerProgressText = $('#xpm-optimizer-progress-text');
        const $optimizerSavings = $('#xpm-optimizer-savings');
        const $optimizerLog = $('#xpm-optimizer-log');
        
        console.log('XPM Image Optimizer: Interface loaded');
        
        // Load optimization statistics on page load
        loadOptimizationStats();
        
        // Scan for unoptimized images
        $scanButton.on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            $button.prop('disabled', true).html('<span class="dashicons dashicons-images-alt2"></span> ' + xpmImageSeo.strings.scanning);
            $button.addClass('updating-message');
            
            $.ajax({
                url: xpmImageSeo.ajax_url,
                method: 'POST',
                data: {
                    action: 'xpm_get_unoptimized_images',
                    nonce: xpmImageSeo.nonce
                },
                success: function(response) {
                    if (response.success) {
                        displayOptimizationResults(response.data);
                        addOptimizerLogEntry('✅ Scan completed: Found ' + response.data.images.length + ' unoptimized images', 'success');
                    } else {
                        showOptimizerError('Scan Error: ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Optimizer scan failed:', xhr.responseText);
                    showOptimizerError(xpmImageSeo.strings.scan_failed + ': ' + error);
                },
                complete: function() {
                    $button.prop('disabled', false)
                           .removeClass('updating-message')
                           .html('<span class="dashicons dashicons-images-alt2"></span> ' + xpmImageSeo.strings.scan_optimizer_button);
                }
            });
        });
        
        // Start bulk optimization
        $optimizeStartButton.on('click', function(e) {
            e.preventDefault();
            
            if (optimizeQueue.length === 0) {
                showOptimizerError(xpmImageSeo.strings.no_unoptimized);
                return;
            }
            
            if (confirm(xpmImageSeo.strings.confirm_optimize + '\n\n' + optimizeQueue.length + ' images will be processed.')) {
                startBulkOptimization();
            }
        });
        
        // Stop bulk optimization
        $optimizeStopButton.on('click', function(e) {
            e.preventDefault();
            
            if (confirm(xpmImageSeo.strings.confirm_stop)) {
                stopBulkOptimization();
            }
        });
        
        // Restore images functionality
        $restoreButton.on('click', function(e) {
            e.preventDefault();
            alert('Restore functionality: Select images to restore from backup. This feature can be expanded to show a list of backed up images.');
        });
        
        /**
         * Load optimization statistics
         */
        function loadOptimizationStats() {
            $.ajax({
                url: xpmImageSeo.ajax_url,
                method: 'POST',
                data: {
                    action: 'xpm_get_optimization_stats',
                    nonce: xpmImageSeo.nonce
                },
                success: function(response) {
                    if (response.success) {
                        updateStatsDisplay(response.data);
                    }
                }
            });
        }
        
        /**
         * Update statistics display
         */
        function updateStatsDisplay(stats) {
            $('#total-images').text(stats.total_images || 0);
            $('#total-size').text(stats.total_original_size_human || '0 MB');
            $('#potential-savings').text((stats.savings_percent || 0) + '%');
            $('#processing-count').text(currentIndex || 0);
            
            if (stats.total_images > 0) {
                $optimizerStats.show();
                addOptimizerLogEntry(`📊 Previously optimized: ${stats.total_images} images, saved ${stats.total_savings_human}`, 'info');
            }
        }
        
        /**
         * Display optimization scan results
         */
        function displayOptimizationResults(data) {
            optimizeQueue = data.images;
            currentIndex = 0;
            totalSavings = 0;
            totalOriginalSize = data.total_size;
            
            $optimizerCount.text(data.images.length);
            $optimizerResults.slideDown();
            $optimizeStartButton.prop('disabled', data.images.length === 0);
            
            // Update stats
            $('#total-images').text(data.images.length);
            $('#total-size').text(data.total_size_human);
            $('#potential-savings').text(data.estimated_savings.percent + '%');
            $optimizerStats.show();
            
            if (data.images.length === 0) {
                $optimizerPreview.html('<div class="xpm-no-images"><p>🎉 All your images are already optimized! Great job on performance.</p></div>');
                return;
            }
            
            // Create image preview grid
            let previewHtml = '';
            const previewCount = Math.min(data.images.length, 24);
            
            for (let i = 0; i < previewCount; i++) {
                const image = data.images[i];
                const formatClass = image.format.toLowerCase();
                
                previewHtml += `
                    <div class="xpm-optimizer-item" data-id="${image.id}">
                        <div class="optimizer-status pending"></div>
                        <img src="${escapeHtml(image.url)}" alt="" loading="lazy" />
                        <h4 title="${escapeHtml(image.title)}">${escapeHtml(truncateText(image.title, 20))}</h4>
                        <div class="optimizer-file-info">
                            <span class="format-icon ${formatClass}">${image.format}</span>
                            <span class="file-size">${image.file_size_human}</span><br/>
                            <span class="dimensions">${image.dimensions}</span>
                        </div>
                        <div class="size-comparison" style="display: none;">
                            <span class="size-before">Before: <span class="before-size">${image.file_size_human}</span></span>
                            <span class="size-after">After: <span class="after-size">-</span></span>
                            <span class="size-savings">-</span>
                        </div>
                    </div>
                `;
            }
            
            if (data.images.length > previewCount) {
                previewHtml += `
                    <div class="xpm-more-images">
                        <div class="dashicons dashicons-plus-alt"></div>
                        <p><strong>+${data.images.length - previewCount}</strong><br/>more images</p>
                    </div>
                `;
            }
            
            $optimizerPreview.html(previewHtml);
            
            // Add estimated information
            addOptimizerLogEntry(`📸 Found ${data.images.length} unoptimized images`, 'info');
            addOptimizerLogEntry(`💾 Total size: ${data.total_size_human}`, 'info');
            addOptimizerLogEntry(`🎯 Estimated savings: ${data.estimated_savings.percent}% (~${data.estimated_savings.human})`, 'info');
        }
        
        /**
         * Start bulk optimization
         */
        function startBulkOptimization() {
            isOptimizing = true;
            currentIndex = 0;
            startTime = Date.now();
            totalSavings = 0;
            
            $optimizeStartButton.hide();
            $optimizeStopButton.show();
            $optimizerProgress.slideDown();
            $optimizerLog.empty().show();
            
            addOptimizerLogEntry('🚀 Starting bulk image optimization...', 'info');
            optimizeNextImage();
        }
        
        /**
         * Stop bulk optimization
         */
        function stopBulkOptimization() {
            isOptimizing = false;
            $optimizeStopButton.hide();
            $optimizeStartButton.show();
            addOptimizerLogEntry('⏹️ ' + xpmImageSeo.strings.stopped, 'warning');
            updateOptimizerProgress();
        }
        
        /**
         * Optimize next image in queue
         */
        function optimizeNextImage() {
            if (!isOptimizing || currentIndex >= optimizeQueue.length) {
                completeBulkOptimization();
                return;
            }
            
            const image = optimizeQueue[currentIndex];
            updateOptimizerProgress();
            
            // Highlight current image
            $('.xpm-optimizer-item').removeClass('processing');
            const $currentItem = $(`.xpm-optimizer-item[data-id="${image.id}"]`);
            $currentItem.addClass('processing');
            $currentItem.find('.optimizer-status').removeClass('pending').addClass('processing');
            
            $.ajax({
                url: xpmImageSeo.ajax_url,
                method: 'POST',
                data: {
                    action: 'xpm_bulk_optimize_image',
                    nonce: xpmImageSeo.nonce,
                    attachment_id: image.id
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        const savingsPercent = data.savings_percent || 0;
                        const savingsHuman = formatBytes(data.savings || 0);
                        
                        totalSavings += data.savings || 0;
                        
                        // Update item display
                        $currentItem.find('.optimizer-status')
                                   .removeClass('processing pending')
                                   .addClass('optimized');
                        
                        // Show before/after comparison
                        const $comparison = $currentItem.find('.size-comparison');
                        $comparison.find('.after-size').text(formatBytes(data.new_size));
                        $comparison.find('.size-savings').text('-' + savingsPercent + '%');
                        $comparison.show();
                        
                        // Add backup indicator if backup was created
                        if (data.backup_created) {
                            $currentItem.append('<span class="backup-indicator">BACKUP</span>');
                        }
                        
                        // Enhanced log entry
                        let logMessage = `✅ <strong>${escapeHtml(image.title)}</strong><br/>`;
                        logMessage += `📦 Reduced from ${formatBytes(data.original_size)} to ${formatBytes(data.new_size)}<br/>`;
                        logMessage += `💾 Saved: ${savingsHuman} (${savingsPercent}%)`;
                        
                        if (data.resized) {
                            logMessage += `<br/>📐 Resized for optimal dimensions`;
                        }
                        
                        if (data.backup_created) {
                            logMessage += `<br/>🔒 Backup created`;
                        }
                        
                        addOptimizerLogEntry(logMessage, 'success');
                    } else {
                        // Update item for error
                        $currentItem.find('.optimizer-status')
                                   .removeClass('processing pending')
                                   .addClass('error');
                        
                        addOptimizerLogEntry(`❌ <strong>${escapeHtml(image.title)}</strong><br/>Error: ${escapeHtml(response.data)}`, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    let errorMsg = xpmImageSeo.strings.network_error;
                    if (xhr.responseJSON && xhr.responseJSON.data) {
                        errorMsg = xhr.responseJSON.data;
                    }
                    
                    $currentItem.find('.optimizer-status')
                               .removeClass('processing pending')
                               .addClass('error');
                    
                    addOptimizerLogEntry(`❌ <strong>${escapeHtml(image.title)}</strong><br/>Error: ${escapeHtml(errorMsg)}`, 'error');
                },
                complete: function() {
                    $currentItem.removeClass('processing');
                    currentIndex++;
                    updateOptimizerProgress();
                    
                    // Continue with next image after delay
                    setTimeout(optimizeNextImage, 1000);
                }
            });
        }
        
        /**
         * Update optimization progress
         */
        function updateOptimizerProgress() {
            const progress = Math.min((currentIndex / optimizeQueue.length) * 100, 100);
            $optimizerProgressFill.css('width', progress + '%');
            $optimizerProgressText.text(`${currentIndex} of ${optimizeQueue.length} images optimized`);
            $optimizerSavings.text(`Saved: ${formatBytes(totalSavings)}`);
            
            // Update stats
            $('#processing-count').text(currentIndex);
        }
        
        /**
         * Complete bulk optimization
         */
        function completeBulkOptimization() {
            isOptimizing = false;
            $optimizeStopButton.hide();
            $optimizeStartButton.show();
            updateOptimizerProgress();
            
            const duration = startTime ? Math.round((Date.now() - startTime) / 1000) : 0;
            const totalSavingsPercent = totalOriginalSize > 0 ? Math.round((totalSavings / totalOriginalSize) * 100) : 0;
            
            let finalMessage = `🎉 ${xpmImageSeo.strings.completed}<br/>`;
            finalMessage += `📊 Optimized: ${currentIndex}/${optimizeQueue.length} images in ${duration}s<br/>`;
            finalMessage += `💾 Total savings: ${formatBytes(totalSavings)} (${totalSavingsPercent}%)<br/>`;
            finalMessage += `🚀 Your website will now load faster!`;
            
            addOptimizerLogEntry(finalMessage, 'success');
            
            // Reload stats
            setTimeout(loadOptimizationStats, 2000);
        }
        
        /**
         * Add entry to optimizer log
         */
        function addOptimizerLogEntry(message, type = 'info') {
            const timestamp = new Date().toLocaleTimeString();
            const $entry = $(`
                <div class="xpm-log-entry ${type}">
                    <span class="xpm-log-timestamp">[${timestamp}]</span> ${message}
                </div>
            `);
            
            $optimizerLog.prepend($entry);
            
            if ($optimizerLog.children().length > 100) {
                $optimizerLog.children().slice(100).remove();
            }
            
            $optimizerLog.scrollTop(0);
            $optimizerLog.show();
        }
        
        /**
         * Show optimizer error
         */
        function showOptimizerError(message) {
            addOptimizerLogEntry('⚠️ ' + message, 'error');
            console.error('XPM Image Optimizer Error:', message);
        }
    }
    
    /**
     * Initialize Bulk Update
     */
    function initializeBulkUpdate() {
        let isUpdating = false;
        let updateQueue = [];
        let currentIndex = 0;
        let startTime = null;
        let totalProcessed = 0;
        let totalUpdates = {
            'Alt Text': 0,
            'Title': 0,
            'Description': 0
        };
        
        // Cache DOM elements
        const $scanButton = $('#xpm-scan-images');
        const $startButton = $('#xpm-bulk-update-start');
        const $stopButton = $('#xpm-bulk-update-stop');
        const $scanResults = $('#xpm-scan-results');
        const $imagesCount = $('#xpm-images-count');
        const $imagesPreview = $('#xpm-images-preview');
        const $progressContainer = $('#xpm-progress-container');
        const $progressFill = $('#xpm-progress-fill');
        const $progressText = $('#xpm-progress-text');
        const $estimatedTime = $('#xpm-estimated-time');
        const $resultsLog = $('#xpm-results-log');
        
        console.log('XPM Bulk Update: Interface loaded');
        
        // Scan for images without alt text
        $scanButton.on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            $button.prop('disabled', true).html('<span class="dashicons dashicons-search"></span> ' + xpmImageSeo.strings.scanning);
            $button.addClass('updating-message');
            
            $.ajax({
                url: xpmImageSeo.ajax_url,
                method: 'POST',
                data: {
                    action: 'xpm_get_images_without_alt',
                    nonce: xpmImageSeo.nonce
                },
                success: function(response) {
                    if (response.success) {
                        displayScanResults(response.data);
                        addLogEntry('✅ Scan completed: Found ' + response.data.length + ' images without alt text', 'success');
                    } else {
                        showError('Scan Error: ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Scan failed:', xhr.responseText);
                    showError(xpmImageSeo.strings.scan_failed + ': ' + error);
                },
                complete: function() {
                    $button.prop('disabled', false)
                           .removeClass('updating-message')
                           .html('<span class="dashicons dashicons-search"></span> ' + xpmImageSeo.strings.scan_button);
                }
            });
        });
        
        // Start bulk update
        $startButton.on('click', function(e) {
            e.preventDefault();
            
            if (updateQueue.length === 0) {
                showError(xpmImageSeo.strings.no_images);
                return;
            }
            
            const message = `Start updating ${updateQueue.length} images?\n\nThis will update:\n• Alt text (always)\n• Image titles (if enabled)\n• Image descriptions (if enabled)\n• Smart keyword optimization\n\nThis may take a while and will use your OpenAI API credits.`;
            
            if (confirm(message)) {
                startBulkUpdate();
            }
        });
        
        // Stop bulk update
        $stopButton.on('click', function(e) {
            e.preventDefault();
            
            if (confirm(xpmImageSeo.strings.confirm_stop)) {
                stopBulkUpdate();
            }
        });
        
        /**
         * Display scan results
         */
        function displayScanResults(images) {
            updateQueue = images;
            currentIndex = 0;
            totalProcessed = 0;
            totalUpdates = {'Alt Text': 0, 'Title': 0, 'Description': 0};
            
            $imagesCount.text(images.length);
            $scanResults.slideDown();
            $startButton.prop('disabled', images.length === 0);
            
            if (images.length === 0) {
                $imagesPreview.html('<div class="xpm-no-images"><p>🎉 All your images already have alt text! Great job on accessibility and SEO.</p></div>');
                return;
            }
            
            // Create enhanced image preview grid
            let previewHtml = '';
            const previewCount = Math.min(images.length, 20);
            
            for (let i = 0; i < previewCount; i++) {
                const image = images[i];
                previewHtml += `
                    <div class="xpm-image-preview" data-id="${image.id}">
                        <img src="${escapeHtml(image.url)}" alt="" loading="lazy" />
                        <p title="${escapeHtml(image.title)}">${escapeHtml(truncateText(image.title, 20))}</p>
                        <small>${image.upload_date || ''}</small>
                        <div class="xpm-update-status">
                            <span class="status-badge pending">Pending</span>
                        </div>
                    </div>
                `;
            }
            
            if (images.length > previewCount) {
                previewHtml += `
                    <div class="xpm-more-images">
                        <div class="dashicons dashicons-plus-alt"></div>
                        <p><strong>+${images.length - previewCount}</strong><br/>more images</p>
                    </div>
                `;
            }
            
            $imagesPreview.html(previewHtml);
            
            // Add enhanced information
            const estimatedCost = (images.length * 0.002).toFixed(3);
            addLogEntry(`ℹ️ Ready to process ${images.length} images with smart keyword optimization`, 'info');
            addLogEntry(`💰 Estimated API cost: ~${estimatedCost} USD`, 'info');
            addLogEntry(`🔧 Will update: Alt text + titles + descriptions (based on your settings)`, 'info');
            addLogEntry(`🎯 Keywords will be extracted from: page content, titles, tags, categories + your global keywords`, 'info');
        }
        
        /**
         * Start bulk update process
         */
        function startBulkUpdate() {
            isUpdating = true;
            currentIndex = 0;
            startTime = Date.now();
            
            $startButton.hide();
            $stopButton.show();
            $progressContainer.slideDown();
            $resultsLog.empty().show();
            
            addLogEntry('🚀 Starting enhanced bulk update with smart keyword optimization...', 'info');
            addLogEntry('🎯 Keywords will be extracted from page content and your global settings', 'info');
            updateNextImage();
        }
        
        /**
         * Stop bulk update process
         */
        function stopBulkUpdate() {
            isUpdating = false;
            $stopButton.hide();
            $startButton.show();
            addLogEntry('⏹️ ' + xpmImageSeo.strings.stopped, 'warning');
            updateEstimatedTime();
            showFinalStats();
        }
        
        /**
         * Update next image in queue
         */
        function updateNextImage() {
            if (!isUpdating || currentIndex >= updateQueue.length) {
                completeBulkUpdate();
                return;
            }
            
            const image = updateQueue[currentIndex];
            updateProgress();
            
            // Highlight current image in preview
            $('.xpm-image-preview').removeClass('processing');
            const $currentPreview = $(`.xpm-image-preview[data-id="${image.id}"]`);
            $currentPreview.addClass('processing');
            $currentPreview.find('.status-badge').removeClass('pending').addClass('processing').text('Processing...');
            
            $.ajax({
                url: xpmImageSeo.ajax_url,
                method: 'POST',
                data: {
                    action: 'xpm_bulk_update_alt_text',
                    nonce: xpmImageSeo.nonce,
                    attachment_id: image.id
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        const altText = data.alt_text;
                        const updatesMsg = data.detailed_message || 'Updated successfully';
                        const keywordsUsed = data.keywords_used || [];
                        
                        // Track what was updated
                        if (data.updates_made) {
                            data.updates_made.forEach(field => {
                                if (totalUpdates[field] !== undefined) {
                                    totalUpdates[field]++;
                                }
                            });
                        }
                        
                        // Update preview status
                        $currentPreview.find('.status-badge')
                                      .removeClass('processing pending')
                                      .addClass('success')
                                      .text('✓ Updated');
                        
                        // Enhanced log entry with keywords
                        let logMessage = `✅ <strong>${escapeHtml(image.title)}</strong><br/>`;
                        logMessage += `📝 Alt text: "${escapeHtml(altText)}"<br/>`;
                        logMessage += `🔧 ${escapeHtml(updatesMsg)}`;
                        
                        if (keywordsUsed.length > 0) {
                            logMessage += `<br/>🎯 Keywords used: <em>${escapeHtml(keywordsUsed.join(', '))}</em>`;
                        }
                        
                        if (data.retry_count > 0) {
                            logMessage += `<br/>⚡ Succeeded after ${data.retry_count} retries`;
                        }
                        
                        addLogEntry(logMessage, 'success');
                        totalProcessed++;
                    } else {
                        // Update preview status for error
                        $currentPreview.find('.status-badge')
                                      .removeClass('processing pending')
                                      .addClass('error')
                                      .text('✗ Failed');
                        
                        addLogEntry(`❌ <strong>${escapeHtml(image.title)}</strong><br/>Error: ${escapeHtml(response.data)}`, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    let errorMsg = xpmImageSeo.strings.network_error;
                    if (xhr.responseJSON && xhr.responseJSON.data) {
                        errorMsg = xhr.responseJSON.data;
                    }
                    
                    $currentPreview.find('.status-badge')
                                  .removeClass('processing pending')
                                  .addClass('error')
                                  .text('✗ Error');
                    
                    addLogEntry(`❌ <strong>${escapeHtml(image.title)}</strong><br/>Error: ${escapeHtml(errorMsg)}`, 'error');
                },
                complete: function() {
                    $currentPreview.removeClass('processing');
                    currentIndex++;
                    updateEstimatedTime();
                    
                    // Continue with next image after delay (rate limiting)
                    setTimeout(updateNextImage, 2000);
                }
            });
        }
        
        /**
         * Update progress indicators
         */
        function updateProgress() {
            const progress = Math.min((currentIndex / updateQueue.length) * 100, 100);
            $progressFill.css('width', progress + '%');
            $progressText.text(`${currentIndex} of ${updateQueue.length} images processed`);
        }
        
        /**
         * Update estimated time remaining
         */
        function updateEstimatedTime() {
            if (!startTime || currentIndex === 0) return;
            
            const elapsed = Date.now() - startTime;
            const avgTimePerImage = elapsed / currentIndex;
            const remaining = updateQueue.length - currentIndex;
            const estimatedRemaining = remaining * avgTimePerImage;
            
            if (estimatedRemaining > 0) {
                const minutes = Math.ceil(estimatedRemaining / 60000);
                $estimatedTime.text(`Estimated time remaining: ${minutes} minutes`);
            }
        }
        
        /**
         * Complete bulk update process
         */
        function completeBulkUpdate() {
            isUpdating = false;
            $stopButton.hide();
            $startButton.show();
            updateProgress();
            
            showFinalStats();
            
            // Remove processing highlights
            $('.xpm-image-preview').removeClass('processing');
            
            // Suggest rescanning if there were failures
            if (totalProcessed < updateQueue.length) {
                setTimeout(() => {
                    addLogEntry('💡 Tip: You can re-scan to retry failed images. Keywords will be re-analyzed for better targeting.', 'info');
                }, 2000);
            }
        }
        
        /**
         * Show final statistics
         */
        function showFinalStats() {
            const duration = startTime ? Math.round((Date.now() - startTime) / 1000) : 0;
            const successRate = updateQueue.length > 0 ? Math.round((totalProcessed / updateQueue.length) * 100) : 0;
            
            let statsMessage = `🎉 ${xpmImageSeo.strings.completed}<br/>`;
            statsMessage += `📊 Processed: ${totalProcessed}/${updateQueue.length} images (${successRate}%) in ${duration}s<br/>`;
            statsMessage += `📝 Fields updated:<br/>`;
            statsMessage += `&nbsp;&nbsp;• Alt Text: ${totalUpdates['Alt Text']}<br/>`;
            statsMessage += `&nbsp;&nbsp;• Titles: ${totalUpdates['Title']}<br/>`;
            statsMessage += `&nbsp;&nbsp;• Descriptions: ${totalUpdates['Description']}<br/>`;
            statsMessage += `🎯 All updates included smart keyword optimization for better SEO`;
            
            addLogEntry(statsMessage, 'success');
        }
        
        /**
         * Add entry to results log
         */
        function addLogEntry(message, type = 'info') {
            const timestamp = new Date().toLocaleTimeString();
            const $entry = $(`
                <div class="xpm-log-entry ${type}">
                    <span class="xpm-log-timestamp">[${timestamp}]</span> ${message}
                </div>
            `);
            
            $resultsLog.prepend($entry);
            
            $resultsLog.scrollTop(0);
            $resultsLog.show();
        }
        
        /**
         * Show error message
         */
        function showError(message) {
            addLogEntry('⚠️ ' + message, 'error');
            console.error('XPM Image SEO Error:', message);
        }
    }
    
    /**
     * Shared utility functions
     */
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    function truncateText(text, length) {
        if (!text || text.length <= length) return text;
        return text.substring(0, length - 3) + '...';
    }
    
    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }
    
    // Global connectivity test
    if (typeof xpmImageSeo !== 'undefined') {
        console.log('XPM Image SEO: Enhanced interface loaded successfully');
        
        if (currentPage === 'settings_page_xpm-image-seo-settings') {
            console.log('Settings page with working tabs loaded');
        } else if (currentPage === 'media_page_xpm-image-seo-optimizer') {
            console.log('Image Optimizer module loaded');
        } else if (currentPage === 'media_page_xpm-image-seo-bulk-update') {
            console.log('Bulk Update module loaded');
        }
    } else {
        console.error('XPM Image SEO: JavaScript configuration not loaded');
    }
});