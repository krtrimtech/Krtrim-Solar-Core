<?php
/**
 * Email Templates Factory
 * 
 * Generates and sends stylized HTML emails and invoices.
 */

if (!defined('ABSPATH')) {
    exit;
}

class KSC_Email_Templates {

    /**
     * Send a styled HTML email using the standard wrapper
     */
    public static function send_styled_email($to, $subject, $content_html) {
        $site_name = get_bloginfo('name');
        
        $wrapper = "
        <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; background-color: #f4f6f9; padding: 40px 0;'>
            <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05);'>
                <div style='background-color: #1e293b; padding: 24px; text-align: center;'>
                    <h1 style='color: #ffffff; margin: 0; font-size: 24px; font-weight: 600;'>{$site_name}</h1>
                </div>
                <div style='padding: 32px;'>
                    {$content_html}
                </div>
                <div style='background-color: #f8fafc; padding: 24px; text-align: center; border-top: 1px solid #e2e8f0;'>
                    <p style='color: #64748b; font-size: 14px; margin: 0;'>Thank you for choosing {$site_name}.</p>
                    <p style='color: #94a3b8; font-size: 12px; margin: 8px 0 0 0;'>This is an automated message, please do not reply directly to this email.</p>
                </div>
            </div>
        </div>
        ";

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        return wp_mail($to, $subject, $wrapper, $headers);
    }

    /**
     * Shared Base Structure for All Invoices
     */
    private static function get_invoice_base_html($title, $billed_to, $txn_id, $items_html, $footer_text) {
        $date = date('d M Y');
        $invoice_id = strtoupper(substr(md5($txn_id . time()), 0, 8));

        return "
        <div style='margin-bottom: 24px;'>
            <h2 style='color: #0f172a; margin: 0 0 8px 0; font-size: 20px;'>{$title}</h2>
            <p style='color: #64748b; margin: 0; font-size: 14px;'>Reference #{$invoice_id}</p>
        </div>

        <div style='margin-bottom: 32px; color: #334155; line-height: 1.6;'>
            <p style='margin: 0;'><strong>Billed To:</strong> {$billed_to}</p>
            <p style='margin: 0;'><strong>Date:</strong> {$date}</p>
            <p style='margin: 0;'><strong>Transaction ID:</strong> <span style='font-family: monospace; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;'>{$txn_id}</span></p>
        </div>

        <table style='width: 100%; border-collapse: collapse; margin-bottom: 32px;'>
            <thead>
                <tr style='background-color: #f8fafc; border-bottom: 2px solid #e2e8f0;'>
                    <th style='padding: 12px; text-align: left; color: #475569; font-weight: 600; font-size: 14px;'>Description</th>
                    <th style='padding: 12px; text-align: right; color: #475569; font-weight: 600; font-size: 14px;'>Amount</th>
                </tr>
            </thead>
            <tbody>
                {$items_html}
            </tbody>
        </table>

        <p style='color: #475569; margin: 0; line-height: 1.6;'>{$footer_text}</p>
        ";
    }

    /**
     * Generates HTML Invoice for a Cleaning Booking
     */
    public static function get_cleaning_invoice_html($service_id) {
        $customer_name = get_post_meta($service_id, '_customer_name', true);
        $system_size_kw = get_post_meta($service_id, '_system_size_kw', true);
        $plan_type = get_post_meta($service_id, '_plan_type', true);
        $total_amount = get_post_meta($service_id, '_total_amount', true);
        $transaction_id = get_post_meta($service_id, '_razorpay_payment_id', true) ?: 'PENDING';
        
        $plan_name = ucfirst(str_replace('_', ' ', $plan_type));
        $formatted_amount = number_format((float)$total_amount, 2);

        $items_html = "
            <tr style='border-bottom: 1px solid #e2e8f0;'>
                <td style='padding: 16px 12px; color: #334155;'>Solar Cleaning Service<br><span style='font-size: 13px; color: #64748b;'>Plan: {$plan_name} ({$system_size_kw} kW)</span></td>
                <td style='padding: 16px 12px; text-align: right; color: #0f172a; font-weight: 500;'>₹{$formatted_amount}</td>
            </tr>
            <tr>
                <td style='padding: 16px 12px; text-align: right; font-weight: 600; color: #0f172a;'>Total Paid</td>
                <td style='padding: 16px 12px; text-align: right; font-weight: 700; color: #16a34a; font-size: 18px;'>₹{$formatted_amount}</td>
            </tr>
        ";

        $footer = "Your payment has been successfully processed. Our team will contact you shortly to confirm the scheduled date and time for the visit.";
        
        return self::get_invoice_base_html("Payment Receipt", $customer_name, $transaction_id, $items_html, $footer);
    }

    /**
     * Generates HTML Invoice for Vendor Coverage Expansion
     */
    public static function get_vendor_invoice_html($vendor_id, $payment_id, $amount, $states_added, $cities_added) {
        $vendor_user = get_userdata($vendor_id);
        $vendor_name = $vendor_user->display_name;
        $vendor_company = get_user_meta($vendor_id, 'company_name', true) ?: $vendor_name;
        
        $formatted_amount = number_format((float)$amount, 2);
        $states_list = is_array($states_added) ? implode(', ', $states_added) : $states_added;
        $cities_list = is_array($cities_added) ? implode(', ', $cities_added) : $cities_added;

        $items_html = "
            <tr style='border-bottom: 1px solid #e2e8f0;'>
                <td style='padding: 16px 12px; color: #334155;'>
                    Coverage Expansion Fee<br>
                    <span style='font-size: 13px; color: #64748b;'>States: " . ($states_list ?: 'None') . "</span><br>
                    <span style='font-size: 13px; color: #64748b;'>Cities: " . ($cities_list ?: 'None') . "</span>
                </td>
                <td style='padding: 16px 12px; text-align: right; color: #0f172a; font-weight: 500;'>₹{$formatted_amount}</td>
            </tr>
            <tr>
                <td style='padding: 16px 12px; text-align: right; font-weight: 600; color: #0f172a;'>Total Paid</td>
                <td style='padding: 16px 12px; text-align: right; font-weight: 700; color: #16a34a; font-size: 18px;'>₹{$formatted_amount}</td>
            </tr>
        ";

        $footer = "Your coverage areas have been successfully expanded in our marketplace. You will now receive leads for the newly added locations.";

        return self::get_invoice_base_html("Tax Invoice / Receipt", $vendor_company, $payment_id, $items_html, $footer);
    }
}
