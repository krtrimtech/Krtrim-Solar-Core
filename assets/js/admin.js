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

