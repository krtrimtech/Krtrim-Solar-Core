<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SP_Process_Steps_Manager {

    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_process_steps_meta_box'));
        add_action('wp_ajax_admin_review_step', array($this, 'ajax_review_step'));
    }

    public function add_process_steps_meta_box() {
        // Only add Review Submissions meta box for authorized users
        if (current_user_can('manage_options') || current_user_can('manager') || current_user_can('area_manager')) {
            add_meta_box(
                'solar_review_submissions_meta',
                '🔍 Review Vendor Submissions',
                array($this, 'render_review_submissions_meta_box'),
                'solar_project',
                'normal',
                'high'
            );
        }
    }

    public function render_review_submissions_meta_box($post) {
        global $wpdb;
        $table = $wpdb->prefix . 'solar_process_steps';
        $steps = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE project_id = %d AND admin_status = 'pending' AND image_url IS NOT NULL ORDER BY step_number ASC",
            $post->ID
        ));

        wp_nonce_field('review_step_nonce', 'review_step_nonce_field');

        if (empty($steps)) {
            echo '<p>No pending submissions to review.</p>';
            return;
        }

        foreach ($steps as $step) {
            ?>
            <div class="submission-review-card" id="review-card-<?php echo $step->id; ?>">
                <h4>Step <?php echo $step->step_number; ?>: <?php echo esc_html($step->step_name); ?></h4>
                <div class="submission-content">
                    <div class="submission-image">
                        <a href="<?php echo esc_url($step->image_url); ?>" target="_blank">
                            <img src="<?php echo esc_url($step->image_url); ?>" style="max-width: 200px; border-radius: 4px;" />
                        </a>
                    </div>
                    <div class="submission-details">
                        <p><strong>Vendor Comment:</strong></p>
                        <p><?php echo esc_html($step->vendor_comment); ?></p>
                    </div>
                </div>
                <div class="review-actions">
                    <textarea class="admin-comment" rows="2" placeholder="Add a comment (required for rejection)"></textarea>
                    <button class="button button-secondary reject-step-btn" data-step-id="<?php echo $step->id; ?>">Reject</button>
                    <button class="button button-primary approve-step-btn" data-step-id="<?php echo $step->id; ?>">Approve</button>
                </div>
            </div>
            <?php
        }
        ?>
        <script>
            jQuery(document).ready(function($) {
                $('.approve-step-btn, .reject-step-btn').on('click', function() {
                    var stepId = $(this).data('step-id');
                    var decision = $(this).hasClass('approve-step-btn') ? 'approved' : 'rejected';
                    var comment = $('#review-card-' + stepId + ' .admin-comment').val();
                    var button = $(this);

                    if (decision === 'rejected' && !comment) {
                        alert('A comment is required to reject a step.');
                        return;
                    }
                    
                    button.prop('disabled', true).text('Processing...');

                    $.post(ajaxurl, {
                        action: 'admin_review_step',
                        step_id: stepId,
                        decision: decision,
                        comment: comment,
                        nonce: $('#review_step_nonce_field').val()
                    }, function(response) {
                        if (response.success) {
                            $('#review-card-' + stepId).html('<p><strong>' + response.data.message + '</strong></p>').fadeOut(2000, function() { $(this).remove(); });
                        } else {
                            alert('Error: ' + response.data.message);
                            button.prop('disabled', false).text(decision === 'approved' ? 'Approve' : 'Reject');
                        }
                    });
                });
            });
        </script>
        <style>
            .submission-review-card { border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 4px; background: #fff; }
            .submission-content { display: flex; gap: 20px; }
            .review-actions { margin-top: 15px; }
            .review-actions textarea { width: 100%; margin-bottom: 10px; }
        </style>
        <?php
    }

    public function ajax_review_step() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'review_step_nonce')) {
            wp_send_json_error(['message' => 'Security check failed.']);
        }

        if (!current_user_can('manage_options') && !current_user_can('manager') && !current_user_can('area_manager')) {
            wp_send_json_error(['message' => 'You do not have permission to review submissions.']);
        }

        $step_id = intval($_POST['step_id']);
        $decision = sanitize_text_field($_POST['decision']);
        $comment = sanitize_textarea_field($_POST['comment']);

        $result = self::process_step_review($step_id, $decision, $comment, get_current_user_id());

        if ($result['success']) {
            wp_send_json_success(['message' => $result['message']]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }

    /**
     * Centralized method to process a step review (Approve/Reject).
     * Handles database updates and notifications.
     *
     * @param int $step_id
     * @param string $decision 'approved' or 'rejected'
     * @param string $comment
     * @param int $reviewer_id
     * @return array ['success' => bool, 'message' => string, 'whatsapp_data' => array|null]
     */
    public static function process_step_review($step_id, $decision, $comment, $reviewer_id) {
        if (empty($step_id) || !in_array($decision, ['approved', 'rejected'])) {
            return ['success' => false, 'message' => 'Invalid data provided.'];
        }

        if ($decision === 'rejected' && empty($comment)) {
            return ['success' => false, 'message' => 'A comment is required for rejection.'];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'solar_process_steps';
        
        // Verify step exists
        $submission = $wpdb->get_row($wpdb->prepare("SELECT project_id, step_number, step_name FROM {$table} WHERE id = %d", $step_id));
        if (!$submission) {
            return ['success' => false, 'message' => 'Invalid submission.'];
        }

        // Update database
        $result = $wpdb->update(
            $table,
            [
                'admin_status' => $decision,
                'admin_comment' => $comment,
                'approved_date' => current_time('mysql'),
            ],
            ['id' => $step_id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            return ['success' => false, 'message' => 'Failed to update the database.'];
        }

        // Trigger action hook
        do_action('sp_step_reviewed', $step_id, $submission->project_id, $decision);

        // ✅ AUTO-COMPLETE PROJECT if all steps are approved
        if ($decision === 'approved') {
            $remaining_steps = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE project_id = %d AND admin_status != 'approved'",
                $submission->project_id
            ));

            if ($remaining_steps == 0) {
                update_post_meta($submission->project_id, 'project_status', 'completed');
                
                // Notify Client of Completion
                $client_id = get_post_meta($submission->project_id, '_client_user_id', true);
                if ($client_id) {
                    SP_Notifications_Manager::create_notification([
                        'user_id' => $client_id,
                        'project_id' => $submission->project_id,
                        'message' => 'Your project has been marked as Completed!',
                        'type' => 'project_completed',
                    ]);
                }
            }
        }

        // --- Notification Logic ---
        $whatsapp_data = null;
        $project_id = $submission->project_id;
        $vendor_id = get_post_meta($project_id, '_assigned_vendor_id', true);
        
        if ($vendor_id) {
            $vendor = get_userdata($vendor_id);
            $project_title = get_the_title($project_id);
            
            if ($vendor) {
                $notification_options = get_option('sp_notification_options');
                $vendor_phone = get_user_meta($vendor_id, 'phone', true);
                $whatsapp_message = '';

                if ($decision === 'approved') {
                    // Email
                    if (isset($notification_options['email_submission_approved']) && $notification_options['email_submission_approved'] === '1') {
                        $subject = 'Project Step Approved: ' . $project_title;
                        $email_message = "<p>Good news! Step " . $submission->step_number . " (" . $submission->step_name . ") for project '" . $project_title . "' has been approved.</p>";
                        if ($comment) $email_message .= "<p>Admin comment: " . esc_html($comment) . "</p>";
                        wp_mail($vendor->user_email, $subject, $email_message, ['Content-Type: text/html; charset=UTF-8']);
                    }
                    // WhatsApp
                    if (isset($notification_options['whatsapp_enable']) && isset($notification_options['whatsapp_submission_approved']) && $notification_options['whatsapp_submission_approved'] === '1' && !empty($vendor_phone)) {
                        $whatsapp_message = "Good news! Step " . $submission->step_number . " (" . $submission->step_name . ") for project '" . $project_title . "' has been approved.";
                        if ($comment) $whatsapp_message .= "\nAdmin comment: " . esc_html($comment);
                        $whatsapp_data = [
                            'phone' => '91' . preg_replace('/\D/', '', $vendor_phone),
                            'message' => urlencode($whatsapp_message)
                        ];
                    }

                } else { // rejected
                    // Email
                    if (isset($notification_options['email_submission_rejected']) && $notification_options['email_submission_rejected'] === '1') {
                        $subject = 'Project Step Rejected: ' . $project_title;
                        $email_message = "<p>Step " . $submission->step_number . " (" . $submission->step_name . ") for project '" . $project_title . "' has been rejected.</p>";
                        if ($comment) $email_message .= "<p>Reason: " . esc_html($comment) . "</p>";
                        wp_mail($vendor->user_email, $subject, $email_message, ['Content-Type: text/html; charset=UTF-8']);
                    }
                    // WhatsApp
                    if (isset($notification_options['whatsapp_enable']) && isset($notification_options['whatsapp_submission_rejected']) && $notification_options['whatsapp_submission_rejected'] === '1' && !empty($vendor_phone)) {
                        $whatsapp_message = "Step " . $submission->step_number . " (" . $submission->step_name . ") for project '" . $project_title . "' has been rejected.";
                        if ($comment) $whatsapp_message .= "\nReason: " . esc_html($comment);
                         $whatsapp_data = [
                            'phone' => '91' . preg_replace('/\D/', '', $vendor_phone),
                            'message' => urlencode($whatsapp_message)
                        ];
                    }
                }
            }
        }
                
        // ✅ NOTIFY CLIENT - Step Rejected
        $client_id = get_post_meta($project_id, '_client_user_id', true);
        if ($client_id) {
            SP_Notifications_Manager::create_notification([
                'user_id' => $client_id,
                'project_id' => $project_id,
                'message' => sprintf('Step "%s" for your project needs rework', $submission->step_name),
                'type' => 'step_rejected',
            ]);
        }

        return [
            'success' => true, 
            'message' => 'Step has been ' . $decision . '.',
            'whatsapp_data' => $whatsapp_data
        ];
    }
}
