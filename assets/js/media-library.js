/**
 * XPM Image SEO Media Library JavaScript
 */
jQuery(document).ready(function($) {
    'use strict';
    
    // Generate Alt Text
    $(document).on('click', '.xpm-generate-alt', function() {
        var attachmentId = $(this).data('attachment-id');
        var button = $(this);
        
        button.prop('disabled', true).text('Generating...').addClass('xpm-processing');
        
        $.post(ajaxurl, {
            action: 'xpm_bulk_update_alt_text',
            nonce: xpmImageSeo.nonce,
            attachment_id: attachmentId
        }, function(response) {
            if (response.success) {
                button.hide();
                alert('Alt text generated successfully!\n\nAlt Text: ' + response.data.alt_text);
                setTimeout(function() { 
                    location.reload(); 
                }, 1000);
            } else {
                alert('Error: ' + response.data);
                button.prop('disabled', false).text('Generate Alt Text').removeClass('xpm-processing');
            }
        }).fail(function() {
            alert('Network error. Please try again.');
            button.prop('disabled', false).text('Generate Alt Text').removeClass('xpm-processing');
        });
    });
    
    // Optimize Image
    $(document).on('click', '.xpm-optimize-image', function() {
        var attachmentId = $(this).data('attachment-id');
        var button = $(this);
        
        button.prop('disabled', true).text('Optimizing...').addClass('xpm-processing');
        
        $.post(ajaxurl, {
            action: 'xpm_bulk_optimize_image',
            nonce: xpmImageSeo.nonce,
            attachment_id: attachmentId
        }, function(response) {
            if (response.success) {
                var data = response.data;
                var message = 'Image optimized successfully!\n\n';
                message += 'Original Size: ' + formatBytes(data.original_size) + '\n';
                message += 'New Size: ' + formatBytes(data.new_size) + '\n';
                message += 'Saved: ' + formatBytes(data.savings) + ' (' + data.savings_percent + '%)';
                
                button.hide();
                alert(message);
                setTimeout(function() { 
                    location.reload(); 
                }, 1000);
            } else {
                alert('Error: ' + response.data);
                button.prop('disabled', false).text('Optimize Image').removeClass('xpm-processing');
            }
        }).fail(function() {
            alert('Network error. Please try again.');
            button.prop('disabled', false).text('Optimize Image').removeClass('xpm-processing');
        });
    });
    
    // Re-optimize
    $(document).on('click', '.xpm-reoptimize', function() {
        var attachmentId = $(this).data('attachment-id');
        var button = $(this);
        
        if (!confirm('Re-optimize this image? This will overwrite the current optimization.')) {
            return;
        }
        
        button.prop('disabled', true).text('Re-optimizing...');
        
        // First restore, then optimize again
        $.post(ajaxurl, {
            action: 'xpm_restore_from_backup',
            nonce: xpmImageSeo.nonce,
            attachment_id: attachmentId
        }, function(restoreResponse) {
            if (restoreResponse.success) {
                // Now re-optimize
                $.post(ajaxurl, {
                    action: 'xpm_bulk_optimize_image',
                    nonce: xpmImageSeo.nonce,
                    attachment_id: attachmentId
                }, function(optimizeResponse) {
                    if (optimizeResponse.success) {
                        alert('Image re-optimized successfully!');
                        location.reload();
                    } else {
                        alert('Re-optimization failed: ' + optimizeResponse.data);
                        button.prop('disabled', false).text('Re-Optimize');
                    }
                });
            } else {
                alert('Could not restore original: ' + restoreResponse.data);
                button.prop('disabled', false).text('Re-Optimize');
            }
        });
    });
    
    // Restore Original
    $(document).on('click', '.xpm-restore-original', function() {
        var attachmentId = $(this).data('attachment-id');
        var button = $(this);
        
        if (!confirm('Restore original image? This will undo all optimizations.')) {
            return;
        }
        
        button.prop('disabled', true).text('Restoring...');
        
        $.post(ajaxurl, {
            action: 'xpm_restore_from_backup',
            nonce: xpmImageSeo.nonce,
            attachment_id: attachmentId
        }, function(response) {
            if (response.success) {
                alert('Original image restored successfully!');
                location.reload();
            } else {
                alert('Error: ' + response.data);
                button.prop('disabled', false).text('Restore Original');
            }
        });
    });
    
    // Format bytes function
    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }
});