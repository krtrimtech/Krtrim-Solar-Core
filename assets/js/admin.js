function handleWhatsAppRedirect(whatsapp_data) {
    if (whatsapp_data && whatsapp_data.phone && whatsapp_data.message) {
        const url = `https://wa.me/${whatsapp_data.phone}?text=${whatsapp_data.message}`;
        window.open(url, '_blank');
    }
}

jQuery(document).ready(function ($) {
    // --- Vendor Approval Page Logic ---
    $('.vendor-action-btn').on('click', function () {
        const button = $(this);
        const userId = button.data('user-id');
        const action = button.data('action');
        const nonce = $('#sp_vendor_approval_nonce_field').val();

        if (!confirm(`Are you sure you want to ${action} this vendor?`)) {
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'update_vendor_status',
                vendor_id: userId,
                status: action === 'approve' ? 'yes' : 'denied',  // ✅ Map to PHP expected values
                nonce: nonce,
            },
            beforeSend: function () {
                button.siblings('.vendor-action-btn').addBack().prop('disabled', true).text('Processing...');
            },
            success: function (response) {
                if (response.success) {
                    alert(response.data.message);
                    handleWhatsAppRedirect(response.data.whatsapp_data);
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                    button.siblings('.vendor-action-btn').addBack().prop('disabled', false).text(function () {
                        return $(this).data('action') === 'approve' ? 'Approve' : 'Deny';
                    });
                }
            },
            error: function () {
                alert('An unknown error occurred.');
                button.siblings('.vendor-action-btn').addBack().prop('disabled', false).text(function () {
                    return $(this).data('action') === 'approve' ? 'Approve' : 'Deny';
                });
            }
        });
    });

    // --- Bidding Meta Box Logic ---
    $('#bids-meta-box-table').on('click', '.award-bid-btn', function () {
        const button = $(this);
        const projectId = button.data('project-id');
        const vendorId = button.data('vendor-id');
        const bidAmount = button.data('bid-amount');
        const nonce = $('#award_bid_nonce_field').val();

        if (!confirm('Are you sure you want to award this project to this vendor?')) {
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'award_project_to_vendor',
                project_id: projectId,
                vendor_id: vendorId,
                bid_amount: bidAmount,
                nonce: nonce,
            },
            beforeSend: function () {
                button.text('Awarding...').prop('disabled', true);
            },
            success: function (response) {
                if (response.success) {
                    alert(response.data.message);
                    handleWhatsAppRedirect(response.data.whatsapp_data);
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                    button.text('Award Project').prop('disabled', false);
                }
            },
            error: function () {
                alert('An unknown error occurred.');
                button.text('Award Project').prop('disabled', false);
            }
        });
    });

    // --- Area Manager Dashboard Logic ---
    const dashboardApp = $('#area-manager-dashboard-app');
    if (dashboardApp.length) {
        // ... (This part remains unchanged as it's for analytics display)
    }

    // --- Project Review Page Logic ---
    const reviewContainer = $('.review-container');
    if (reviewContainer.length) {
        reviewContainer.on('click', '.review-btn', function () {
            const button = $(this);
            const stepId = button.data('step-id');
            const decision = button.data('decision');
            const form = button.closest('.review-form');
            const comment = form.find('.review-input').val();
            const nonce = $('#sp_review_nonce_field').val();

            if (!comment && decision === 'rejected') {
                alert('A comment is required to reject a submission.');
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'review_vendor_submission',
                    step_id: stepId,
                    decision: decision,
                    comment: comment,
                    nonce: nonce,
                },
                beforeSend: function () {
                    form.find('button').prop('disabled', true).text('Processing...');
                },
                success: function (response) {
                    if (response.success) {
                        const statusBadge = form.closest('.submission-toggle').find('.status-badge');
                        statusBadge.text(decision).removeClass('pending').addClass(decision);
                        form.replaceWith(`<div class="submission-comment"><strong>Admin Comment:</strong> ${comment || 'No comment.'}</div>`);
                        alert('Status updated.');
                        handleWhatsAppRedirect(response.data.whatsapp_data);
                    } else {
                        alert('Error: ' + response.data.message);
                        form.find('button').prop('disabled', false).text(decision);
                    }
                },
                error: function () {
                    alert('An unknown error occurred.');
                    form.find('button').prop('disabled', false).text(decision);
                }
            });
        });
    }
    // --- Vendor Edit Modal Logic ---
    // Vendor Edit Modal - Checkbox Version
    $('.vendor-edit-btn').on('click', function (e) {
        e.preventDefault();
        const btn = $(this);
        const userId = btn.data('user-id');
        const company = btn.data('company') === 'N/A' ? '' : btn.data('company');
        const phone = btn.data('phone');
        let states = btn.data('states') || [];
        let cities = btn.data('cities') || [];

        // Normalize data (handle both string and object formats)
        states = states.map(s => (typeof s === 'object' && s.state) ? s.state : s);
        cities = cities.map(c => (typeof c === 'object' && c.city) ? c.city : c);

        // Populate fields
        $('#edit-vendor-id').val(userId);
        $('#edit-company-name').val(company);
        $('#edit-phone').val(phone);

        // Check state checkboxes
        $('.state-checkbox').prop('checked', false);
        states.forEach(function (state) {
            $('.state-checkbox[value="' + state + '"]').prop('checked', true);
        });

        // Trigger city update and select cities
        updateEditCitiesCheckboxes(states, cities);

        $('#edit-vendor-modal').show();
    });

    // Handle state checkbox changes
    $(document).on('change', '.state-checkbox', function () {
        const selectedStates = [];
        $('.state-checkbox:checked').each(function () {
            selectedStates.push($(this).val());
        });

        // Preserve currently selected cities
        const currentlySelectedCities = [];
        $('.city-checkbox:checked').each(function () {
            currentlySelectedCities.push($(this).val());
        });

        updateEditCitiesCheckboxes(selectedStates, currentlySelectedCities);
    });

    function updateEditCitiesCheckboxes(selectedStates, selectedCities) {
        const container = $('#edit-cities-checkboxes');
        container.empty();

        if (selectedStates.length === 0) {
            container.html('<p style="text-align: center; color: #999; margin: 20px 0;">Select states above to see available cities</p>');
            return;
        }

        if (typeof indianStatesCities !== 'undefined' && indianStatesCities.states) {
            indianStatesCities.states.forEach(function (stateObj) {
                if (selectedStates.includes(stateObj.state)) {
                    // Add state header
                    container.append(
                        '<div style="background: #0073aa; color: white; padding: 6px 10px; margin: 10px 0 5px 0; border-radius: 3px; font-weight: 600;">' +
                        '📍 ' + stateObj.state +
                        '</div>'
                    );

                    // Add cities for this state
                    stateObj.districts.forEach(function (city) {
                        const isChecked = selectedCities.includes(city) ? 'checked' : '';
                        container.append(
                            '<label style="display: block; padding: 4px 0 4px 20px; cursor: pointer;">' +
                            '<input type="checkbox" class="city-checkbox" value="' + city + '" ' + isChecked + ' style="margin-right: 8px;">' +
                            '<span>' + city + '</span>' +
                            '</label>'
                        );
                    });
                }
            });
        }
    }

    // Select/Deselect All functions
    window.selectAllStates = function () {
        $('.state-checkbox').prop('checked', true).first().trigger('change');
    };

    window.deselectAllStates = function () {
        $('.state-checkbox').prop('checked', false).first().trigger('change');
    };

    window.selectAllCities = function () {
        $('.city-checkbox').prop('checked', true);
    };

    window.deselectAllCities = function () {
        $('.city-checkbox').prop('checked', false);
    };

    $('#edit-vendor-form').on('submit', function (e) {
        e.preventDefault();
        const form = $(this);
        const btn = form.find('button[type="submit"]');
        const nonce = $('#sp_vendor_approval_nonce_field').val();

        // Collect checked states and cities
        const selectedStates = [];
        $('.state-checkbox:checked').each(function () {
            selectedStates.push($(this).val());
        });

        const selectedCities = [];
        $('.city-checkbox:checked').each(function () {
            selectedCities.push($(this).val());
        });

        const formData = {
            action: 'update_vendor_details',
            nonce: nonce,
            vendor_id: $('#edit-vendor-id').val(),
            company_name: $('#edit-company-name').val(),
            phone: $('#edit-phone').val(),
            states: selectedStates,
            cities: selectedCities
        };

        btn.prop('disabled', true).text('Saving...');

        $.post(ajaxurl, formData, function (response) {
            if (response.success) {
                alert('Vendor details updated successfully!');
                location.reload();
            } else {
                alert('Error: ' + (response.data.message || 'Failed to update vendor details'));
                btn.prop('disabled', false).text('Save Changes');
            }
        }).fail(function () {
            alert('Error connecting to server');
            btn.prop('disabled', false).text('Save Changes');
        });
    });

});

// --- Admin Cleaner Management Logic ---
// Wrap in jQuery ready to ensure $ is available
jQuery(function ($) {

    // Open Cleaner Profile Modal
    $(document).on('click', '.admin-view-cleaner-btn', function (e) {
        e.preventDefault();
        const cleanerId = $(this).data('id');
        const modal = $('#admin-cleaner-profile-modal');

        // Reset and Show Loading
        modal.find('h2').text('Loading...');
        modal.find('p, img').not('.close-modal-btn').css('opacity', 0.5);
        modal.show();

        // Fetch Data
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'get_cleaners' // Admin gets all cleaners so this works if we filter or just find by ID in JS
            },
            success: function (response) {
                if (response.success) {
                    const cleaner = response.data.find(c => c.id == cleanerId);
                    if (cleaner) {
                        populateAdminCleanerModal(cleaner);
                    } else {
                        alert('Cleaner not found.');
                        modal.hide();
                    }
                } else {
                    alert('Error fetching cleaner details.');
                    modal.hide();
                }
            },
            error: function () {
                alert('Connection error.');
                modal.hide();
            }
        });
    });

    function populateAdminCleanerModal(cleaner) {
        const modal = $('#admin-cleaner-profile-modal');

        modal.find('#admin-cleaner-name').text(cleaner.name);
        modal.find('#admin-cleaner-meta').text('Solar Cleaner • Joined ' + new Date(cleaner.created_at).toLocaleDateString());
        modal.find('#admin-cleaner-phone').text(cleaner.phone);
        modal.find('#admin-cleaner-email').text(cleaner.email || 'N/A');
        modal.find('#admin-cleaner-address').text(cleaner.address || 'N/A');
        modal.find('#admin-cleaner-aadhaar').text(cleaner.aadhaar || 'N/A');

        // Images
        const photoUrl = cleaner.photo_url || 'https://via.placeholder.com/150?text=No+Photo';
        modal.find('#admin-cleaner-photo').attr('src', photoUrl).css('opacity', 1);

        if (cleaner.aadhaar_image_url) {
            modal.find('#admin-cleaner-aadhaar-img').attr('src', cleaner.aadhaar_image_url).show().css('opacity', 1);
            modal.find('#admin-cleaner-aadhaar-img').next().show();
        } else {
            modal.find('#admin-cleaner-aadhaar-img').hide();
            modal.find('#admin-cleaner-aadhaar-img').next().hide();
        }

        // Bind Actions
        modal.find('#admin-edit-cleaner-btn').data('id', cleaner.id).off('click').on('click', function () {
            // Future: Implement inline edit or redirect to user-edit.php
            window.location.href = 'user-edit.php?user_id=' + cleaner.id;
        });

        modal.find('#admin-delete-cleaner-btn').data('id', cleaner.id).off('click').on('click', function () {
            if (confirm('Are you sure you want to permanently delete this cleaner account? This action cannot be undone.')) {
                deleteCleanerAsAdmin(cleaner.id);
            }
        });
    }

    function deleteCleanerAsAdmin(cleanerId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'delete_cleaner',
                cleaner_id: cleanerId,
                // nonce should be added if stricter security is needed, using generic admin nonce or specific one
            },
            success: function (response) {
                if (response.success) {
                    alert('Cleaner deleted successfully.');
                    $('#admin-cleaner-profile-modal').hide();
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                }
            }
        });
    }




    // --- Admin Schedule Visit Logic ---
    console.log('🔧 [Admin.js] Attempting to bind Schedule button handler...');
    try {
        $(document).on('click', '.admin-schedule-btn', function (e) {
            console.log('🎯 [Admin.js] Schedule button clicked!');
            e.preventDefault();
            e.stopPropagation();

            const btn = $(this);
            const serviceId = btn.data('id');
            const customerName = btn.data('name');
            const preferredDate = btn.data('preferred-date');

            $('#admin_schedule_service_id').val(serviceId);
            $('#admin_schedule_customer_name').text(customerName);

            // Auto-fill preferred date if available, otherwise use today
            const dateInput = $('#admin_schedule_date');
            const today = new Date().toISOString().split('T')[0];
            dateInput.attr('min', today);

            if (preferredDate && preferredDate !== '') {
                // Use client's preferred date
                dateInput.val(preferredDate);
            } else {
                // Default to today
                dateInput.val(today);
            }

            // Clear previous cleaners
            const cleanerSelect = $('#admin_schedule_cleaner_id');
            cleanerSelect.html('<option value="">Loading cleaners...</option>');

            $('#admin-schedule-modal').fadeIn(200).css('display', 'flex');

            // Fetch cleaners
            $.post(ajaxurl, { action: 'get_cleaners' }, function (response) {
                if (response.success) {
                    cleanerSelect.empty();
                    cleanerSelect.append('<option value="">Select Cleaner</option>');
                    response.data.forEach(cleaner => {
                        // Determine if cleaner has phone to show
                        const label = cleaner.name + (cleaner.phone ? ` (${cleaner.phone})` : '');
                        cleanerSelect.append(`<option value="${cleaner.id}">${label}</option>`);
                    });
                } else {
                    cleanerSelect.html('<option value="">Error loading cleaners</option>');
                }
            });
        });
        console.log('✅ [Admin.js] Schedule button handler bound successfully');
    } catch (error) {
        console.error('❌ [Admin.js] Error binding Schedule button handler:', error);
    }

    // Handle Schedule Form Submit
    $('#admin-schedule-form').on('submit', function (e) {
        e.preventDefault();
        const btn = $(this).find('button[type="submit"]');
        const feedback = $('#admin-schedule-feedback');

        btn.prop('disabled', true).text('Scheduling...');
        feedback.html('');

        const data = {
            action: 'schedule_cleaning_visit',
            service_id: $('#admin_schedule_service_id').val(),
            cleaner_id: $('#admin_schedule_cleaner_id').val(),
            scheduled_date: $('#admin_schedule_date').val(),
            scheduled_time: $('#admin_schedule_time').val()
        };

        $.post(ajaxurl, data, function (response) {
            if (response.success) {
                feedback.html('<span style="color: green;">Visit scheduled successfully!</span>');
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                feedback.html('<span style="color: red;">Error: ' + (response.data.message || 'Unknown error') + '</span>');
                btn.prop('disabled', false).text('+ Schedule Visit');
            }
        }).fail(function () {
            feedback.html('<span style="color: red;">Server connection failed.</span>');
            btn.prop('disabled', false).text('+ Schedule Visit');
        });
    });

    // Admin View Service Details Button Handler
    $(document).on('click', '.admin-view-service-details-btn', function (e) {
        e.preventDefault();
        e.stopPropagation();

        const serviceId = $(this).data('id');
        displayAdminServiceDetails(serviceId);
    });

    // Function to display service details in admin
    function displayAdminServiceDetails(serviceId) {
        const modal = $('#admin-service-details-modal');
        const content = $('#admin-service-details-content');

        // Fix: Use flex to center the modal instead of default block from .show()
        modal.css('display', 'flex').fadeIn(200);
        content.html('<p style="text-align: center; color: #666;">Loading service details...</p>');

        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'get_cleaning_service_details',
                service_id: serviceId
            },
            success: function (response) {
                if (response.success) {
                    const service = response.data.service;
                    const visits = response.data.visits;

                    const planLabels = {
                        'one_time': 'One-Time',
                        'monthly': 'Monthly',
                        '6_month': '6-Month',
                        'yearly': 'Yearly'
                    };

                    const paymentModeLabels = {
                        'online': '💳 Online',
                        'cash': '💵 Cash',
                        'upi': '📱 UPI',
                        'card': '💳 Card'
                    };

                    let html = `
                        <div style="background: #f9fafb; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                            <h4 style="margin: 0 0 15px 0; color: #1f2937;">👤 Customer Information</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div><strong>Name:</strong> ${service.customer_name}</div>
                                <div><strong>Phone:</strong> ${service.customer_phone}</div>
                                <div style="grid-column: 1 / -1;"><strong>Address:</strong> ${service.customer_address || 'N/A'}</div>
                            </div>
                        </div>
                        
                        <div style="background: #eff6ff; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                            <h4 style="margin: 0 0 15px 0; color: #1e40af;">📋 Service Plan</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div><strong>Plan Type:</strong> ${planLabels[service.plan_type] || service.plan_type}</div>
                                <div><strong>System Size:</strong> ${service.system_size_kw} kW</div>
                                <div><strong>Total Visits:</strong> ${service.visits_total}</div>
                                <div><strong>Visits Used:</strong> ${service.visits_used}</div>
                                <div><strong>Remaining:</strong> ${service.visits_total - service.visits_used}</div>
                                ${service.preferred_date ? `<div><strong>Preferred Date:</strong> ${service.preferred_date}</div>` : ''}
                            </div>
                        </div>
                        
                        <div style="background: #f0fdf4; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                            <h4 style="margin: 0 0 15px 0; color: #166534;">💰 Payment Information</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div><strong>Total Amount:</strong> ₹${Number(service.total_amount || 0).toLocaleString()}</div>
                                <div><strong>Payment Status:</strong> <span style="background: ${service.payment_status === 'paid' ? '#d1fae5' : '#fef3c7'}; color: ${service.payment_status === 'paid' ? '#047857' : '#b45309'}; padding: 3px 8px; border-radius: 4px;">${service.payment_status === 'paid' ? 'Paid' : 'Pending'}</span></div>
                                <div><strong>Payment Mode:</strong> ${service.payment_mode ? paymentModeLabels[service.payment_mode] || service.payment_mode : 'N/A'}</div>
                                ${service.transaction_id ? `<div><strong>Transaction ID:</strong> ${service.transaction_id}</div>` : ''}
                            </div>
                        </div>
                        
                        <div style="background: #fef3c7; padding: 20px; border-radius: 8px;">
                            <h4 style="margin: 0 0 15px 0; color: #92400e;">📅 Visit History</h4>
                    `;

                    if (visits.length === 0) {
                        html += '<p style="color: #666; text-align: center;">No visits scheduled yet.</p>';
                    } else {
                        html += '<div style="max-height: 300px; overflow-y: auto;">';
                        visits.forEach((visit, index) => {
                            const statusColors = {
                                'scheduled': '#3b82f6',
                                'in_progress': '#f59e0b',
                                'completed': '#10b981',
                                'cancelled': '#ef4444'
                            };
                            const statusIcons = {
                                'scheduled': '📅',
                                'in_progress': '⏳',
                                'completed': '✅',
                                'cancelled': '❌'
                            };

                            let actions = '';
                            // Ensure status check is robust (trim whitespace, lowercase)
                            const vStatus = (visit.status || '').toLowerCase().trim();

                            if (vStatus === 'scheduled') {
                                actions = `
                                    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed #eee;">
                                        <button class="button button-small admin-btn-edit-visit" 
                                            data-id="${visit.id}" 
                                            data-service-id="${serviceId}" 
                                            data-customer="${service.customer_name}"
                                            data-cleaner-id="${visit.cleaner_id || ''}"
                                            data-date="${visit.scheduled_date}" 
                                            data-time="${visit.scheduled_time || ''}"
                                            style="margin-right: 5px;">✏️ Edit</button>
                                        <button class="button button-small admin-btn-cancel-visit" 
                                            data-id="${visit.id}" 
                                            style="color: #dc3545; border-color: #dc3545;">❌ Cancel</button>
                                    </div>
                                `;
                            }

                            html += `
                                <div style="background: white; padding: 15px; border-radius: 6px; margin-bottom: 10px; border-left: 4px solid ${statusColors[vStatus] || '#6b7280'};">
                                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                                        <strong>${statusIcons[vStatus] || '📍'} Visit ${index + 1}</strong>
                                        <span style="background: ${statusColors[vStatus] || '#6b7280'}; color: white; padding: 2px 8px; border-radius: 4px; font-size: 12px;">${(visit.status || 'unknown').toUpperCase()}</span>
                                    </div>
                                    <div style="font-size: 14px; color: #666;">
                                        <div><strong>Date:</strong> ${visit.scheduled_date} at ${visit.scheduled_time || 'N/A'}</div>
                                        <div><strong>Cleaner:</strong> ${visit.cleaner_name || 'Not assigned'}</div>
                                        ${visit.completed_at ? `<div><strong>Completed:</strong> ${visit.completed_at}</div>` : ''}
                                        ${visit.completion_notes ? `<div><strong>Notes:</strong> ${visit.completion_notes}</div>` : ''}
                                    </div>
                                    ${actions}
                                </div>
                            `;
                        });
                        html += '</div>';
                    }

                    html += '</div>';

                    content.html(html);
                    jQuery('#admin-service-details-title').text(`Service Details - ${service.customer_name}`);
                } else {
                    content.html('<p style="color: red; text-align: center;">Error loading service details.</p>');
                }
            },
            error: function () {
                content.html('<p style="color: red; text-align: center;">Error loading service details. Please try again.</p>');
            }
        });
    }

    // --- Admin Edit Visit Logic ---
    $(document).on('click', '.admin-btn-edit-visit', function (e) {
        e.preventDefault();
        const btn = $(this);
        const visitId = btn.data('id');
        const serviceId = btn.data('service-id');
        const customerName = btn.data('customer');
        const cleanerId = btn.data('cleaner-id');
        const date = btn.data('date');
        const time = btn.data('time');

        $('#admin_edit_visit_id').val(visitId);
        $('#admin_edit_service_id').val(serviceId);
        $('#admin_edit_customer_name').text(customerName);
        $('#admin_edit_visit_date').val(date);
        $('#admin_edit_visit_time').val(time);

        // Populate cleaners
        const select = $('#admin_edit_visit_cleaner');
        select.html('<option value="">Loading...</option>');

        $.post(ajaxurl, { action: 'get_cleaners' }, function (response) {
            if (response.success) {
                select.empty();
                select.append('<option value="">Select Cleaner</option>');
                select.append('<option value="0">Unassigned</option>');
                response.data.forEach(c => {
                    const selected = c.id == cleanerId ? 'selected' : '';
                    select.append(`<option value="${c.id}" ${selected}>${c.name} (${c.phone || ''})</option>`);
                });
            } else {
                select.html('<option value="">Error loading cleaners</option>');
            }
        });

        $('#admin-edit-visit-modal').fadeIn(200).css('display', 'flex');
    });

    $('#admin-edit-visit-form').on('submit', function (e) {
        e.preventDefault();
        const form = $(this);
        const btn = form.find('button[type="submit"]');
        const serviceId = $('#admin_edit_service_id').val();

        btn.prop('disabled', true).text('Updating...');

        const formData = form.serialize() + '&action=update_cleaning_visit';

        $.post(ajaxurl, formData, function (response) {
            if (response.success) {
                alert('Visit updated successfully!');
                $('#admin-edit-visit-modal').hide();
                displayAdminServiceDetails(serviceId); // Refresh details
                // Optional: Refresh main table row if needed, but details modal is key
            } else {
                alert('Error: ' + response.data.message);
            }
            btn.prop('disabled', false).text('Update Visit');
        }).fail(function () {
            alert('Server error');
            btn.prop('disabled', false).text('Update Visit');
        });
    });

    // --- Admin Cancel Visit Logic ---
    $(document).on('click', '.admin-btn-cancel-visit', function (e) {
        e.preventDefault();
        const visitId = $(this).data('id');
        $('#admin_cancel_visit_id').val(visitId);
        $('#admin_cancel_reason').val('');
        $('#admin-cancel-visit-modal').fadeIn(200).css('display', 'flex');
    });

    $('#admin-cancel-visit-form').on('submit', function (e) {
        e.preventDefault();
        const form = $(this);
        const btn = form.find('button[type="submit"]');
        // We need service ID to refresh details, but it's not in this form directly.
        // It's in the background details modal. We can get it from there if needed or reload page.
        // Or store it in a global/hidden field when opening cancel modal.
        // Let's assume we reload details if we can find the ID, or just reload page.
        // Currently the Cancel modal is on top of Service Details modal.

        btn.prop('disabled', true).text('Cancelling...');

        const formData = form.serialize() + '&action=cancel_cleaning_visit';

        $.post(ajaxurl, formData, function (response) {
            if (response.success) {
                alert('Visit cancelled successfully.');
                $('#admin-cancel-visit-modal').hide();

                // Try to find open service details ID to refresh
                // We can't easily get it unless we stored it. 
                // But the service details modal is still open behind.
                // We can close everything or try to refresh.
                // Simple approach: Close details modal too and reload page to reflect status in main table
                $('#admin-service-details-modal').hide();
                location.reload();
            } else {
                alert('Error: ' + response.data.message);
                btn.prop('disabled', false).text('Confirm Cancellation');
            }
        }).fail(function () {
            alert('Server error');
            btn.prop('disabled', false).text('Confirm Cancellation');
        });
    });


    // --- Admin View Cleaner Profile ---
    // --- Admin View Cleaner Profile ---
    console.log('Attaching cleaner profile handlers...'); // DEBUG
    $(document).on('click', '.admin-view-cleaner-btn', function (e) {
        e.preventDefault();
        console.log('Cleaner Profile Button Clicked!'); // DEBUG
        const cleanerId = $(this).data('id');
        console.log('Cleaner ID:', cleanerId); // DEBUG

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: { action: 'get_cleaners' },
            success: function (response) {
                console.log('API Response:', response); // DEBUG
                if (response.success) {
                    const cleaner = response.data.find(c => c.id == cleanerId);
                    if (cleaner) {
                        console.log('Cleaner Found:', cleaner); // DEBUG
                        $('#admin-cleaner-name').text(cleaner.name);
                        $('#admin-cleaner-phone').text(cleaner.phone);
                        $('#admin-cleaner-email').text(cleaner.email);
                        $('#admin-cleaner-address').text(cleaner.address);
                        $('#admin-cleaner-aadhaar').text(cleaner.aadhaar);
                        $('#admin-cleaner-created').text(cleaner.created_at);

                        if (cleaner.photo_url) {
                            $('#admin-cleaner-photo').attr('src', cleaner.photo_url);
                        } else {
                            // Fallback or keep existing src if it has a default
                            // logic to set default if empty
                            $('#admin-cleaner-photo').attr('src', '');
                        }

                        if (cleaner.aadhaar_image_url) {
                            $('#admin-cleaner-aadhaar-img').attr('src', cleaner.aadhaar_image_url);
                        } else {
                            $('#admin-cleaner-aadhaar-img').attr('src', '');
                        }

                        console.log('Opening Modal...'); // DEBUG
                        const modal = $('#admin-cleaner-profile-modal');
                        console.log('Modal Element Count:', modal.length); // DEBUG

                        if (modal.length > 0) {
                            // Move to body to avoid z-index/overflow issues
                            $('body').append(modal);
                            modal.css('display', 'flex');
                            $('#admin-edit-cleaner-btn').data('cleaner-id', cleanerId);
                        } else {
                            console.error('CRITICAL: Modal element #admin-cleaner-profile-modal NOT found in DOM!');
                        }
                    } else {
                        console.error('Cleaner NOT found in list for ID:', cleanerId); // DEBUG
                    }
                } else {
                    console.error('API Error:', response); // DEBUG
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Failure:', status, error); // DEBUG
            }
        });
    });

    // --- Admin Edit Cleaner Redirect ---
    $(document).on('click', '#admin-edit-cleaner-btn', function () {
        const cleanerId = $(this).data('cleaner-id');
        if (cleanerId) {
            window.location.href = 'user-edit.php?user_id=' + cleanerId;
        }
    });

}); // End jQuery ready wrapper

