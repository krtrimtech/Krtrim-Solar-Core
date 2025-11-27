/**
 * Project Bid Submission Handler
 * Handles AJAX submission of vendor bids on single project pages
 */

jQuery(document).ready(function ($) {
    console.log('🎯 Project Bid JS Loaded');

    // Handle bid form submission
    $('#place-bid-form').on('submit', function (e) {
        e.preventDefault();

        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        const feedbackDiv = $('#bid-form-feedback');

        // Disable submit button to prevent double submission
        submitBtn.prop('disabled', true).text('Submitting...');

        // Collect form data
        const formData = {
            action: 'submit_project_bid',
            project_id: form.find('input[name="project_id"]').val(),
            bid_amount: form.find('#bid_amount').val(),
            bid_details: form.find('#bid_details').val(),
            bid_type: form.find('input[name="bid_type"]:checked').val(),
            submit_bid_nonce: form.find('input[name="submit_bid_nonce"]').val()
        };

        console.log('📤 Submitting bid:', formData);

        // Send AJAX request
        $.ajax({
            url: project_bid_vars.ajax_url,
            type: 'POST',
            data: formData,
            success: function (response) {
                console.log('📥 Bid response:', response);

                if (response.success) {
                    // Show success message
                    feedbackDiv
                        .removeClass('error')
                        .addClass('success')
                        .html('✓ ' + response.data.message)
                        .show();

                    // Reset form
                    form[0].reset();

                    // Reload page after 1.5 seconds to show updated bid list
                    setTimeout(function () {
                        location.reload();
                    }, 1500);
                } else {
                    // Show error message
                    feedbackDiv
                        .removeClass('success')
                        .addClass('error')
                        .html('✗ ' + (response.data.message || 'Failed to submit bid'))
                        .show();

                    // Re-enable submit button
                    submitBtn.prop('disabled', false).text('Submit Bid');
                }
            },
            error: function (xhr, status, error) {
                console.error('❌ AJAX Error:', status, error);
                console.error('Response:', xhr.responseText);

                feedbackDiv
                    .removeClass('success')
                    .addClass('error')
                    .html('✗ Network error. Please try again.')
                    .show();

                // Re-enable submit button
                submitBtn.prop('disabled', false).text('Submit Bid');
            }
        });
    });

    // Validate bid amount in real-time
    $('#bid_amount').on('input', function () {
        const amount = parseFloat($(this).val());
        if (amount <= 0) {
            $(this).css('border-color', '#dc3545');
        } else {
            $(this).css('border-color', '#ddd');
        }
    });
});
