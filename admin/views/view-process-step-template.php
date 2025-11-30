<?php
/**
 * View: Process Step Template Management
 * 
 * Allows admins to manage default process steps for all new solar projects.
 */

if (!defined('ABSPATH')) {
    exit;
}

function sp_render_process_step_template_page() {
    // Security check
    if (!current_user_can('administrator') && !current_user_can('manager')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Get current default steps
    $default_steps = get_option('sp_default_process_steps', [
        'Site Visit',
        'Design Approval',
        'Material Delivery',
        'Installation',
        'Grid Connection',
        'Final Inspection'
    ]);

    ?>

    <div class="wrap">
        <h1 class="wp-heading-inline">Process Step Template</h1>
        <hr class="wp-header-end">

        <div class="notice notice-info">
            <p><strong>Note:</strong> These default steps will be automatically created for all new solar projects. Changes here do not affect existing projects.</p>
        </div>

        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Default Process Steps</h2>
            <p>Drag to reorder, click × to remove. These steps apply to all new projects.</p>

            <form id="process-step-template-form" method="post">
                <?php wp_nonce_field('sp_save_default_steps', 'sp_steps_nonce'); ?>
                
                <div id="steps-list" class="steps-sortable">
                    <?php foreach ($default_steps as $index => $step): ?>
                        <div class="step-item" data-step-index="<?php echo $index; ?>">
                            <span class="step-handle dashicons dashicons-menu"></span>
                            <input type="text" name="steps[]" value="<?php echo esc_attr($step); ?>" class="regular-text step-input" required>
                            <button type="button" class="button remove-step-btn" title="Remove step">×</button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top: 20px;">
                    <button type="button" id="add-step-btn" class="button">
                        <span class="dashicons dashicons-plus-alt" style="margin-top: 3px;"></span> Add Step
                    </button>
                </div>

                <div style="margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px;">
                    <button type="submit" class="button button-primary button-large">Save Changes</button>
                    <button type="button" id="reset-steps-btn" class="button button-secondary">Reset to Defaults</button>
                    <span id="save-feedback" style="margin-left: 15px;"></span>
                </div>
            </form>
        </div>
    </div>

    <style>
    .steps-sortable {
        margin: 20px 0;
    }

    .step-item {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 10px 15px;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: box-shadow 0.2s;
    }

    .step-item:hover {
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .step-item.ui-sortable-helper {
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        cursor: move;
    }

    .step-handle {
        cursor: move;
        color: #999;
        font-size: 20px;
    }

    .step-handle:hover {
        color: #333;
    }

    .step-input {
        flex: 1;
    }

    .remove-step-btn {
        color: #dc3545;
        font-size: 24px;
        line-height: 1;
        padding: 0 8px;
        min-width: auto;
        height: 30px;
        border-color: #dc3545;
    }

    .remove-step-btn:hover {
        background: #dc3545;
        color: #fff;
        border-color: #dc3545;
    }

    #save-feedback.success {
        color: #28a745;
        font-weight: 500;
    }

    #save-feedback.error {
        color: #dc3545;
        font-weight: 500;
    }
    </style>

    <script>
    jQuery(document).ready(function($) {
        // Make steps sortable
        $('#steps-list').sortable({
            handle: '.step-handle',
            placeholder: 'step-item-placeholder',
            axis: 'y',
            opacity: 0.8
        });

        // Add new step
        $('#add-step-btn').on('click', function() {
            const newIndex = $('#steps-list .step-item').length;
            const newStep = `
                <div class="step-item" data-step-index="${newIndex}">
                    <span class="step-handle dashicons dashicons-menu"></span>
                    <input type="text" name="steps[]" value="" class="regular-text step-input" placeholder="Enter step name" required>
                    <button type="button" class="button remove-step-btn" title="Remove step">×</button>
                </div>
            `;
            $('#steps-list').append(newStep);
        });

        // Remove step
        $(document).on('click', '.remove-step-btn', function() {
            if ($('#steps-list .step-item').length <= 1) {
                alert('You must have at least one step.');
                return;
            }
            if (confirm('Are you sure you want to remove this step?')) {
                $(this).closest('.step-item').fadeOut(300, function() {
                    $(this).remove();
                });
            }
        });

        // Save changes
        $('#process-step-template-form').on('submit', function(e) {
            e.preventDefault();
            
            const steps = [];
            $('#steps-list .step-input').each(function() {
                const stepName = $(this).val().trim();
                if (stepName) {
                    steps.push(stepName);
                }
            });

            if (steps.length === 0) {
                alert('Please add at least one step.');
                return;
            }

            const feedback = $('#save-feedback');
            feedback.text('Saving...').removeClass('success error');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'save_default_process_steps',
                    nonce: $('#sp_steps_nonce').val(),
                    steps: steps
                },
                success: function(response) {
                    if (response.success) {
                        feedback.text('✓ Saved successfully!').addClass('success');
                        setTimeout(() => feedback.fadeOut(), 3000);
                    } else {
                        feedback.text('Error: ' + response.data.message).addClass('error');
                    }
                },
                error: function() {
                    feedback.text('Error: Failed to save changes.').addClass('error');
                }
            });
        });

        // Reset to defaults
        $('#reset-steps-btn').on('click', function() {
            if (!confirm('Are you sure you want to reset to default steps? This will overwrite your current template.')) {
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'reset_default_process_steps',
                    nonce: $('#sp_steps_nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Error: Failed to reset steps.');
                }
            });
        });
    });
    </script>
<?php }
