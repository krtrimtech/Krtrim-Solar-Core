jQuery(document).ready(function ($) {
    // Toggle Bids Row
    $('.toggle-bids-btn').on('click', function () {
        var btn = $(this);
        var projectId = btn.data('project-id');
        var row = $('#bids-row-' + projectId);

        if (row.is(':visible')) {
            row.hide();
            btn.text('Show Bids');
        } else {
            row.show();
            btn.text('Hide Bids');
        }
    });

    // Handle Award Bid
    $('.award-bid-form').on('submit', function (e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to award this project?')) {
            return;
        }

        var form = $(this);
        var btn = form.find('button');
        var originalText = btn.text();

        btn.prop('disabled', true).text('Processing...');

        $.ajax({
            url: spBidManagement.ajaxurl,
            type: 'POST',
            data: form.serialize(),
            success: function (response) {
                if (response.success) {
                    alert(response.data.message);
                    if (response.data.whatsapp_data) {
                        handleWhatsAppRedirect(response.data.whatsapp_data);
                    }
                    location.reload();
                } else {
                    alert('Error: ' + (response.data.message || 'Unknown error'));
                    btn.prop('disabled', false).text(originalText);
                }
            },
            error: function () {
                alert('Network error. Please try again.');
                btn.prop('disabled', false).text(originalText);
            }
        });
    });

    function handleWhatsAppRedirect(whatsapp_data) {
        if (whatsapp_data && whatsapp_data.phone && whatsapp_data.message) {
            const url = `https://wa.me/${whatsapp_data.phone}?text=${whatsapp_data.message}`;
            window.open(url, '_blank');
        }
    }
});
