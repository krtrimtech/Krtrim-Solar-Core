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
                user_id: userId,
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
    $('.vendor-edit-btn').on('click', function (e) {
        e.preventDefault();
        const btn = $(this);
        const userId = btn.data('user-id');
        const company = btn.data('company') === 'N/A' ? '' : btn.data('company');
        const phone = btn.data('phone');
        const states = btn.data('states') || [];
        const cities = btn.data('cities') || [];

        // Populate fields
        $('#edit-vendor-id').val(userId);
        $('#edit-company-name').val(company);
        $('#edit-phone').val(phone);

        // Select states
        $('#edit-states').val(states);

        // Trigger change to populate cities, then select cities
        updateEditCities(states, cities);

        $('#edit-vendor-modal').show();
    });

    $('#edit-states').on('change', function () {
        const selectedStates = $(this).val() || [];
        updateEditCities(selectedStates, []);
    });

    function updateEditCities(selectedStates, selectedCities) {
        const citySelect = $('#edit-cities');
        citySelect.empty();

        if (typeof indianStatesCities !== 'undefined' && indianStatesCities.states) {
            indianStatesCities.states.forEach(function (stateObj) {
                if (selectedStates.includes(stateObj.state)) {
                    stateObj.districts.forEach(function (district) {
                        const option = $('<option></option>').val(district).text(district);
                        if (selectedCities.includes(district)) {
                            option.prop('selected', true);
                        }
                        citySelect.append(option);
                    });
                }
            });
        }
    }

    $('#edit-vendor-form').on('submit', function (e) {
        e.preventDefault();
        const form = $(this);
        const btn = form.find('button[type="submit"]');
        const nonce = $('#sp_vendor_approval_nonce_field').val();

        const formData = {
            action: 'update_vendor_details',
            nonce: nonce,
            user_id: $('#edit-vendor-id').val(),
            company_name: $('#edit-company-name').val(),
            phone: $('#edit-phone').val(),
            states: $('#edit-states').val(),
            cities: $('#edit-cities').val()
        };

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            beforeSend: function () {
                btn.prop('disabled', true).text('Saving...');
            },
            success: function (response) {
                if (response.success) {
                    alert('Vendor details updated successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                    btn.prop('disabled', false).text('Save Changes');
                }
            },
            error: function () {
                alert('An unknown error occurred.');
                btn.prop('disabled', false).text('Save Changes');
            }
        });
    });
});
