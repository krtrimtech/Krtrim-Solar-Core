<?php
/**
 * Vendor Status Dashboard
 * Shows vendors their current registration and approval status
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

function sp_vendor_status_dashboard_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Please <a href="' . wp_login_url() . '">login</a> to view your status.</p>';
    }

    $user = wp_get_current_user();
    
    if (!in_array('solar_vendor', $user->roles)) {
        return '<p>This page is only for vendors.</p>';
    }

    // Get vendor meta data
    $email_verified = get_user_meta($user->ID, 'email_verified', true);
    $payment_status = get_user_meta($user->ID, 'vendor_payment_status', true);
    $account_approved = get_user_meta($user->ID, 'account_approved', true);
    $approval_method = get_user_meta($user->ID, 'approval_method', true);
    $approved_date = get_user_meta($user->ID, 'account_approved_date', true);
    $company_name = get_user_meta($user->ID, 'company_name', true);

    // Enqueue nonce for AJAX
    wp_localize_script('unified-dashboard-scripts', 'vendor_status_vars', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'resend_nonce' => wp_create_nonce('resend_email_nonce'),
    ]);

    ob_start();
    ?>
    <div class="vendor-status-dashboard">
        <h2>Welcome, <?php echo esc_html($company_name ?: $user->display_name); ?>!</h2>
        <p class="status-intro">Track your registration and approval status below.</p>
        
        <div class="status-cards-container">
            <!-- Payment Status Card -->
            <div class="status-card <?php echo ($payment_status === 'completed') ? 'status-complete' : ''; ?>">
                <div class="status-card-header">
                    <span class="status-icon">💳</span>
                    <h3>Payment Status</h3>
                </div>
                <div class="status-card-body">
                    <?php if ($payment_status === 'completed'): ?>
                        <div class="status-badge status-success">✅ Completed</div>
                        <p>Your payment has been received and verified.</p>
                    <?php else: ?>
                        <div class="status-badge status-pending">⏳ Pending</div>
                        <p>Your payment is being processed.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Email Verification Card -->
            <div class="status-card <?php echo ($email_verified === 'yes') ? 'status-complete' : ''; ?>">
                <div class="status-card-header">
                    <span class="status-icon">📧</span>
                    <h3>Email Verification</h3>
                </div>
                <div class="status-card-body">
                    <?php if ($email_verified === 'yes'): ?>
                        <div class="status-badge status-success">✅ Verified</div>
                        <p>Your email address has been verified.</p>
                    <?php else: ?>
                        <div class="status-badge status-pending">⏳ Pending</div>
                        <p>Please check your email and click the verification link.</p>
                        <button id="resend-verification-btn" class="button button-primary" style="margin-top: 10px;">
                            Resend Verification Email
                        </button>
                        <div id="resend-message" style="margin-top: 10px;"></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Account Approval Card -->
            <div class="status-card <?php echo ($account_approved === 'yes') ? 'status-complete' : ''; ?>">
                <div class="status-card-header">
                    <span class="status-icon">👤</span>
                    <h3>Account Approval</h3>
                </div>
                <div class="status-card-body">
                    <?php if ($account_approved === 'yes'): ?>
                        <div class="status-badge status-success">✅ Approved</div>
                        <?php if ($approval_method === 'auto'): ?>
                            <p><strong>Automatically approved!</strong></p>
                            <p>Your account was auto-approved after completing payment and email verification.</p>
                        <?php elseif ($approval_method === 'manual'): ?>
                            <p><strong>Manually approved by administrator.</strong></p>
                        <?php else: ?>
                            <p>Your account has been approved.</p>
                        <?php endif; ?>
                        <?php if ($approved_date): ?>
                            <p><small>Approved on: <?php echo date('F j, Y', strtotime($approved_date)); ?></small></p>
                        <?php endif; ?>
                        <p style="margin-top: 15px;">
                            <a href="<?php echo home_url('/solar-dashboard'); ?>" class="button button-primary">
                                Go to Dashboard →
                            </a>
                        </p>
                    <?php elseif ($account_approved === 'denied'): ?>
                        <div class="status-badge status-denied">❌ Denied</div>
                        <p>Your account application has been denied. Please contact support for more information.</p>
                    <?php else: ?>
                        <div class="status-badge status-pending">⏳ Pending Approval</div>
                        <?php if ($payment_status === 'completed' && $email_verified === 'yes'): ?>
                            <p><strong>Your account is being reviewed.</strong></p>
                            <p>You've completed all requirements. Approval is typically automatic but may require administrator review in some cases.</p>
                        <?php else: ?>
                            <p><strong>Complete the steps above to get auto-approved!</strong></p>
                            <div class="requirements-checklist">
                                <div class="requirement-item <?php echo ($payment_status === 'completed') ? 'completed' : ''; ?>">
                                    <?php echo ($payment_status === 'completed') ? '✅' : '⏳'; ?> Payment Verified
                                </div>
                                <div class="requirement-item <?php echo ($email_verified === 'yes') ? 'completed' : ''; ?>">
                                    <?php echo ($email_verified === 'yes') ? '✅' : '⏳'; ?> Email Verified
                                </div>
                            </div>
                            <p><small>Once both steps are complete, you'll be automatically approved within minutes!</small></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <style>
        .vendor-status-dashboard {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .vendor-status-dashboard h2 {
            font-size: 32px;
            margin-bottom: 10px;
            color: #333;
        }
        .status-intro {
            font-size: 16px;
            color: #666;
            margin-bottom: 30px;
        }
        .status-cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }
        .status-card {
            background: #fff;
            border: 2px solid #e1e8ed;
            border-radius: 12px;
            padding: 24px;
            transition: all 0.3s ease;
        }
        .status-card:hover {
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        .status-card.status-complete {
            border-color: #10b981;
            background: linear-gradient(135deg, #f0fdf4 0%, #ffffff 100%);
        }
        .status-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        .status-icon {
            font-size: 32px;
        }
        .status-card h3 {
            margin: 0;
            font-size: 20px;
            color: #333;
        }
        .status-card-body p {
            margin: 8px 0;
            color: #555;
            line-height: 1.6;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .status-success {
            background: #10b981;
            color: #fff;
        }
        .status-pending {
            background: #f59e0b;
            color: #fff;
        }
        .status-denied {
            background: #ef4444;
            color: #fff;
        }
        .requirements-checklist {
            background: #f9fafb;
            border-radius: 8px;
            padding: 16px;
            margin: 16px 0;
        }
        .requirement-item {
            padding: 8px 0;
            font-size: 15px;
            color: #666;
        }
        .requirement-item.completed {
            color: #10b981;
            font-weight: 600;
        }
        #resend-verification-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        #resend-message {
            font-size: 14px;
            padding: 8px 12px;
            border-radius: 4px;
        }
        #resend-message.success {
            background: #10b981;
            color: #fff;
        }
        #resend-message.error {
            background: #ef4444;
            color: #fff;
        }
    </style>

    <script>
    jQuery(document).ready(function($) {
        $('#resend-verification-btn').on('click', function() {
            const btn = $(this);
            const message = $('#resend-message');
            
            btn.prop('disabled', true).text('Sending...');
            message.text('').removeClass('success error');
            
            $.ajax({
                url: vendor_status_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'resend_verification_email',
                    nonce: vendor_status_vars.resend_nonce
                },
                success: function(response) {
                    if (response.success) {
                        message.text(response.data.message).addClass('success');
                        btn.text('Email Sent!');
                        setTimeout(function() {
                            btn.prop('disabled', false).text('Resend Verification Email');
                        }, 5000);
                    } else {
                        message.text(response.data.message).addClass('error');
                        btn.prop('disabled', false).text('Resend Verification Email');
                    }
                },
                error: function() {
                    message.text('An error occurred. Please try again.').addClass('error');
                    btn.prop('disabled', false).text('Resend Verification Email');
                }
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

// Shortcode registration moved to unified-solar-dashboard.php to avoid duplicate registration
