<?php
/**
 * Edit Project Modal
 * Partial template for editing solar projects
 */
?>
<div id="edit-project-modal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h2>Edit Project</h2>
            <span class="modal-close edit-modal-close">&times;</span>
        </div>
        <div class="modal-body">
            <form id="edit-project-form">
                <input type="hidden" id="edit_project_id" name="project_id" value="">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_project_title">Project Title *</label>
                        <input type="text" id="edit_project_title" name="project_title" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_project_description">Description</label>
                        <textarea id="edit_project_description" name="project_description" rows="3"></textarea>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_project_state">State *</label>
                        <select id="edit_project_state" name="project_state" required>
                            <option value="">Select State</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_project_city">City *</label>
                        <select id="edit_project_city" name="project_city" required>
                            <option value="">Select City</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_client_user_id">Client *</label>
                        <?php
                        wp_dropdown_users([
                            'role' => 'solar_client',
                            'name' => 'client_user_id',
                            'id' => 'edit_client_user_id',
                            'show_option_none' => 'Select Client',
                        ]);
                        ?>
                    </div>
                    <div class="form-group">
                        <label for="edit_solar_system_size_kw">System Size (kW) *</label>
                        <input type="number" id="edit_solar_system_size_kw" name="solar_system_size_kw" step="0.01" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_client_address">Installation Address *</label>
                        <textarea id="edit_client_address" name="client_address" rows="2" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="edit_client_phone_number">Client Phone *</label>
                        <input type="tel" id="edit_client_phone_number" name="client_phone_number" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_project_start_date">Start Date *</label>
                        <input type="date" id="edit_project_start_date" name="project_start_date" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_total_project_cost">Total Cost (₹) *</label>
                        <input type="number" id="edit_total_project_cost" name="total_project_cost" step="0.01" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_paid_amount">Amount Received from Client (₹)</label>
                        <input type="number" id="edit_paid_amount" name="paid_amount" step="0.01">
                    </div>
                    <div class="form-group">
                        <label for="edit_project_status">Project Status</label>
                        <select id="edit_project_status" name="project_status">
                            <option value="pending">Pending</option>
                            <option value="assigned">Assigned</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Vendor Assignment Method</label>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="vendor_assignment_method" value="bidding" id="edit_vendor_bidding" class="edit-vendor-method" checked>
                            Bidding System
                        </label>
                        <label>
                            <input type="radio" name="vendor_assignment_method" value="manual" id="edit_vendor_manual" class="edit-vendor-method">
                            Manual Assignment
                        </label>
                    </div>
                </div>

                <div class="edit-vendor-manual-fields" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_assigned_vendor_id">Assign Vendor</label>
                            <?php
                            wp_dropdown_users([
                                'role' => 'solar_vendor',
                                'name' => 'assigned_vendor_id',
                                'id' => 'edit_assigned_vendor_id',
                                'show_option_none' => 'Select Vendor',
                            ]);
                            ?>
                        </div>
                        <div class="form-group">
                            <label for="edit_paid_to_vendor">Amount to Pay Vendor (₹)</label>
                            <input type="number" id="edit_paid_to_vendor" name="paid_to_vendor" step="0.01">
                        </div>
                    </div>
                </div>

                <div id="edit-project-feedback" class="feedback-message"></div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary edit-modal-close">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Project</button>
                </div>
            </form>
        </div>
    </div>
</div>
