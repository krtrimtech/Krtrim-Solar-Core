/**
 * Shared Cleaner Component JavaScript
 * 
 * Reusable cleaner management for Area Manager and Manager dashboards.
 * 
 * @package Krtrim_Solar_Core
 * @since 1.4.7
 */

(function ($) {
    'use strict';

    let canCreate = false;
    let dashboardType = 'area_manager';
    let currentCleanerId = null;

    // Initialize component
    window.initCleanerComponent = function (ajaxUrl, nonce) {
        if (window.cleanerComponentInitialized) {
            // Just reload if already initialized and needed
            loadCleaners();
            return;
        }

        const $component = $('.cleaner-component');
        if (!$component.length) return;

        window.cleanerComponentInitialized = true;

        canCreate = $component.data('can-create') === true;
        dashboardType = $component.data('dashboard') || 'area_manager';

        // Store configuration
        if (ajaxUrl && nonce) {
            window.cleanerAjax = { url: ajaxUrl, nonce: nonce };
        } else if (!window.cleanerAjax) {
            console.error('❌ [Cleaner Component] Missing AJAX configuration');
            return;
        }

        bindEvents();

        // Move modals to body to prevent z-index/overflow issues
        $('#add-cleaner-modal').appendTo('body');
        $('#cleaner-detail-modal').appendTo('body');

        loadCleaners();
    };

    function bindEvents() {
        // Open Add Cleaner Modal
        $(document).on('click', '#btn-open-add-cleaner', function (e) {
            e.preventDefault();
            resetForm();
            $('#add-cleaner-modal').fadeIn(200).css('display', 'flex');
        });

        // Close Modals (Button)
        $(document).on('click', '.close-cleaner-modal', function (e) {
            console.log('Close button clicked');
            e.preventDefault();
            e.stopPropagation();

            var $overlays = $('.cleaner-component-modal-overlay');
            console.log('Overlays found to close:', $overlays.length);

            if ($overlays.length > 0) {
                $overlays.fadeOut(200, function () {
                    console.log('FadeOut complete');
                    $(this).hide(); // Ensure hidden
                });
            } else {
                console.error('No overlays found to close!');
                // Fallback: Try ID selectors if class failed
                $('#add-cleaner-modal, #cleaner-detail-modal').fadeOut(200);
            }
        });

        // Close on outside click (Overlay)
        $(document).on('click', '.cleaner-component-modal-overlay', function (e) {
            if (e.target === this) {
                console.log('Overlay clicked - closing');
                $(this).fadeOut(200);
            }
        });



        // View Profile
        $(document).on('click', '.view-cleaner-profile', function (e) {
            e.preventDefault();
            const cleaner = $(this).data('cleaner');
            openProfileModal(cleaner);
        });

        // Edit Profile (from Detail Modal)
        $(document).on('click', '#btn-edit-cleaner-profile', function (e) {
            e.preventDefault();
            $('#cleaner-detail-modal').hide();
            openEditModal(currentData);
        });

        // Delete Cleaner
        $(document).on('click', '.delete-cleaner', function (e) {
            e.preventDefault();
            const id = $(this).data('id');
            if (confirm('Are you sure you want to delete this cleaner? This action cannot be undone.')) {
                deleteCleaner(id);
            }
        });

        // Submit Form
        $(document).on('submit', '#create-cleaner-form', function (e) {
            e.preventDefault();
            saveCleaner($(this));
        });

        // Photo Preview
        $('#cleaner_photo').on('change', function () {
            readURL(this, '#preview-cleaner-photo img');
            $('#preview-cleaner-photo').show();
        });
    }

    // Load Cleaners
    function loadCleaners() {
        const container = $('#cleaners-list-container');
        container.html('<div style="text-align: center; padding: 40px; color: #666;"><p>Loading cleaners...</p></div>');

        $.ajax({
            url: window.cleanerAjax.url,
            type: 'POST',
            data: {
                action: 'get_cleaners',
                nonce: window.cleanerAjax.nonce
            },
            success: function (response) {
                if (response.success) {
                    renderCleaners(response.data);
                } else {
                    container.html(`<p style="color: red; text-align: center;">Error: ${response.data.message}</p>`);
                }
            },
            error: function () {
                container.html('<p style="color: red; text-align: center;">Network error loading cleaners.</p>');
            }
        });
    }
    // Expose globally
    window.loadCleaners = loadCleaners;

    // Render List
    function renderCleaners(cleaners) {
        const container = $('#cleaners-list-container');

        if (!cleaners || cleaners.length === 0) {
            container.html(`
                <div style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 12px;">
                    <div style="font-size: 40px; margin-bottom: 10px;">🧹</div>
                    <h3>No cleaners found</h3>
                    <p style="color: #666;">${canCreate ? 'Click "Add New Cleaner" to get started.' : 'No cleaners assigned to your area yet.'}</p>
                </div>
            `);
            return;
        }

        let html = '<div class="cleaners-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">';

        cleaners.forEach(cleaner => {
            const photoUrl = cleaner.photo_url || 'https://via.placeholder.com/80x80?text=No+Photo';
            // Store data for modal
            const cleanerDataStr = JSON.stringify(cleaner).replace(/'/g, "&#39;");

            html += `
                <div class="cleaner-card" style="background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: transform 0.2s; position: relative; overflow: hidden;">
                    <div style="display: flex; gap: 15px; align-items: center; margin-bottom: 15px;">
                        <img src="${photoUrl}" alt="${cleaner.name}" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 2px solid #eee;">
                        <div>
                            <h4 style="margin: 0; font-size: 16px; color: #333;">${cleaner.name}</h4>
                            <p style="margin: 5px 0 0; color: #666; font-size: 13px;">📞 ${cleaner.phone}</p>
                        </div>
                    </div>
                    
                    <div style="font-size: 13px; color: #555; background: #f8f9fa; padding: 10px; border-radius: 8px; margin-bottom: 15px;">
                        <p style="margin: 0 0 5px;"><strong>📍 Location:</strong> ${cleaner.city || 'N/A'}</p>
                        <p style="margin: 0;"><strong>🆔 Aadhaar:</strong> XXXX-${cleaner.aadhaar ? cleaner.aadhaar.slice(-4) : 'XXXX'}</p>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <button class="btn btn-primary btn-sm view-cleaner-profile" data-cleaner='${cleanerDataStr}' style="flex: 1; padding: 8px; font-size: 13px; border-radius: 6px;">
                            👤 Profile
                        </button>
                        <a href="tel:${cleaner.phone}" class="btn btn-secondary btn-sm" style="padding: 8px 12px; font-size: 13px; border-radius: 6px; border: 1px solid #ddd; text-decoration: none; color: #333;">
                            📞
                        </a>
                        ${canCreate ? `
                        <button class="btn btn-danger btn-sm delete-cleaner" data-id="${cleaner.id}" style="padding: 8px 12px; font-size: 13px; border-radius: 6px;">
                            🗑️
                        </button>
                        ` : ''}
                    </div>
                </div>
            `;
        });

        html += '</div>';
        container.html(html);
    }

    // Open Profile Modal
    let currentData = null;
    function openProfileModal(cleaner) {
        currentData = cleaner;
        currentCleanerId = cleaner.id;

        $('#modal-cleaner-name').text(cleaner.name);
        $('#modal-cleaner-phone').text(`📞 ${cleaner.phone}`);
        $('#modal-cleaner-email').text(cleaner.email || 'N/A');
        $('#modal-cleaner-address').text(cleaner.address || 'N/A');
        $('#modal-cleaner-aadhaar').text(cleaner.aadhaar || 'N/A');

        // Supervisor (if available in data)
        if (cleaner.supervisor_name) {
            $('#modal-cleaner-supervisor').text(cleaner.supervisor_name);
        } else if (cleaner.supervisor_id) {
            $('#modal-cleaner-supervisor').text(`ID: ${cleaner.supervisor_id}`);
        } else {
            $('#modal-cleaner-supervisor').text('N/A');
        }

        $('#modal-cleaner-photo').attr('src', cleaner.photo_url || 'https://via.placeholder.com/150?text=No+Photo');

        if (cleaner.aadhaar_image_url) {
            $('#modal-cleaner-aadhaar-img').attr('src', cleaner.aadhaar_image_url).parent().show();
        } else {
            $('#modal-cleaner-aadhaar-img').parent().hide();
        }

        // Show/Hide Edit button based on permissions (assuming canCreate implies canEdit)
        if (canCreate) {
            $('#btn-edit-cleaner-profile').show();
        } else {
            $('#btn-edit-cleaner-profile').hide();
        }

        $('#cleaner-detail-modal').fadeIn(200).css('display', 'flex');
    }

    // Open Edit Modal
    function openEditModal(cleaner) {
        resetForm();
        $('#modal-cleaner-title').text('✏️ Edit Cleaner');
        $('#cleaner_action_type').val('update');
        $('#cleaner_id').val(cleaner.id);

        // Populate fields
        $('#cleaner_name').val(cleaner.name);
        $('#cleaner_phone').val(cleaner.phone);
        $('#cleaner_email').val(cleaner.email);
        $('#cleaner_aadhaar').val(cleaner.aadhaar);
        $('#cleaner_address').val(cleaner.address);

        // Handle Supervisor Dropdown
        if ($('#assigned_area_manager').length && cleaner.supervisor_id) {
            $('#assigned_area_manager').val(cleaner.supervisor_id);
        }

        // Show photos preview
        if (cleaner.photo_url) {
            $('#preview-cleaner-photo img').attr('src', cleaner.photo_url);
            $('#preview-cleaner-photo').show();
        }

        if (cleaner.aadhaar_image_url) {
            $('#preview-cleaner-aadhaar a').attr('href', cleaner.aadhaar_image_url);
            $('#preview-cleaner-aadhaar').show();
        }

        $('#add-cleaner-modal').fadeIn(200).css('display', 'flex');
    }

    // Save Cleaner (Create/Update)
    function saveCleaner($form) {
        const formData = new FormData($form[0]);
        const actionType = $('#cleaner_action_type').val();

        // Map to backend actions
        const action = actionType === 'create' ? 'create_cleaner' : 'update_cleaner';
        formData.append('action', action);
        formData.append('cleaner_nonce', window.cleanerAjax.nonce); // Ensure nonce is sent if not in form

        const btn = $form.find('button[type="submit"]');
        const feedback = $('#create-cleaner-feedback');

        btn.prop('disabled', true).text(actionType === 'create' ? 'Creating...' : 'Updating...');
        feedback.html('');

        $.ajax({
            url: window.cleanerAjax.url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success) {
                    showToast(response.data.message || 'Success!', 'success');
                    $('#add-cleaner-modal').fadeOut(200);
                    loadCleaners(); // Refresh list
                } else {
                    feedback.html(`<div style="color: red; padding: 10px; background: #ffeeee; border-radius: 4px;">❌ ${response.data.message}</div>`);
                }
            },
            error: function () {
                feedback.html('<div style="color: red;">Network error. Please try again.</div>');
            },
            complete: function () {
                btn.prop('disabled', false).text(actionType === 'create' ? '➕ Save Cleaner' : '💾 Update Cleaner');
            }
        });
    }

    // Delete Cleaner
    function deleteCleaner(id) {
        $.ajax({
            url: window.cleanerAjax.url,
            type: 'POST',
            data: {
                action: 'delete_cleaner',
                cleaner_id: id,
                cleaner_nonce: window.cleanerAjax.nonce // Some backends might check this
            },
            success: function (response) {
                if (response.success) {
                    showToast('Cleaner deleted successfully', 'success');
                    loadCleaners();
                } else {
                    showToast(response.data.message || 'Error deleting cleaner', 'error');
                }
            },
            error: function () {
                showToast('Network error', 'error');
            }
        });
    }

    // Helper: Reset Form
    function resetForm() {
        $('#create-cleaner-form')[0].reset();
        $('#cleaner_action_type').val('create');
        $('#cleaner_id').val('');
        $('#modal-cleaner-title').text('➕ Add New Cleaner');
        $('#create-cleaner-feedback').html('');
        $('#preview-cleaner-photo').hide();
        $('#preview-cleaner-aadhaar').hide();
    }

    function readURL(input, selector) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function (e) {
                $(selector).attr('src', e.target.result);
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function showToast(message, type) {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
        } else {
            alert((type === 'error' ? '❌ ' : '✅ ') + message);
        }
    }

})(jQuery);
