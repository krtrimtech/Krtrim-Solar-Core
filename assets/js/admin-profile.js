jQuery(document).ready(function ($) {
    // Generic Media Uploader Handler
    function initMediaUploader(buttonClass, inputId, previewId, displayUrlId) {
        $(document).on('click', buttonClass, function (e) {
            e.preventDefault();
            var button = $(this);
            var custom_uploader = wp.media({
                title: 'Select Image',
                button: {
                    text: 'Use this image'
                },
                multiple: false
            }).on('select', function () {
                var attachment = custom_uploader.state().get('selection').first().toJSON();

                // Set Attachment ID
                $(inputId).val(attachment.id);

                // Set URL (if separate input exists)
                if (displayUrlId) {
                    $(displayUrlId).val(attachment.url);
                }

                // Update Preview
                if (previewId) {
                    $(previewId).attr('src', attachment.url).show();
                }

                // Change button text
                button.text('Change Image');
            }).open();
        });
    }

    // Initialize for Cleaner Photo
    initMediaUploader('.upload-cleaner-photo', '#cleaner_photo_id', '#cleaner_photo_preview', '#cleaner_photo_url');

    // Initialize for Aadhaar Image
    initMediaUploader('.upload-aadhaar-image', '#aadhaar_image_id', '#aadhaar_image_preview', '#aadhaar_image_url');

    // Remove Image Handler
    $('.remove-cleaner-photo').on('click', function (e) {
        e.preventDefault();
        $('#cleaner_photo_id').val('');
        $('#cleaner_photo_url').val('');
        $('#cleaner_photo_preview').hide().attr('src', '');
        $('.upload-cleaner-photo').text('Upload Photo');
        $(this).hide();
    });

    $('.remove-aadhaar-image').on('click', function (e) {
        e.preventDefault();
        $('#aadhaar_image_id').val('');
        $('#aadhaar_image_url').val('');
        $('#aadhaar_image_preview').hide().attr('src', '');
        $('.upload-aadhaar-image').text('Upload Aadhaar Image');
        $(this).hide();
    });
});
