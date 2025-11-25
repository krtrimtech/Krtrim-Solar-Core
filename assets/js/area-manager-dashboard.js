// Password Toggle
$(document).on('click', '.toggle-password', function () {
    const targetId = $(this).data('target');
    const field = $('#' + targetId);
    if (field.attr('type') === 'password') {
        field.attr('type', 'text');
    } else {
        field.attr('type', 'password');
    }
});

// Generate Password
$(document).on('click', '.generate-password-btn', function () {
    const targetId = $(this).data('target');
    const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()";
    let password = "";
    for (let i = 0; i < 12; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    $('#' + targetId).val(password).trigger('input');
});

// Password Strength
$('#client_password').on('input', function () {
    const password = $(this).val();
    const meter = $('#password-strength-bar');
    const text = $('#password-strength-text');
    let strength = 0;

    if (password.length >= 8) strength++;
    if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
    if (password.match(/\d/)) strength++;
    if (password.match(/[^a-zA-Z\d]/)) strength++;

    let color = 'red';
    let width = '0%';
    let label = '';

    switch (strength) {
        case 0:
        case 1:
            width = '25%';
            color = 'red';
            label = 'Weak';
            break;
        case 2:
            width = '50%';
            color = 'orange';
            label = 'Medium';
            break;
        case 3:
            width = '75%';
            color = 'blue';
            label = 'Strong';
            break;
        case 4:
            width = '100%';
            color = 'green';
            label = 'Very Strong';
            break;
    }

    if (password.length === 0) {
        width = '0%';
        label = '';
    }

    meter.css({ 'width': width, 'background-color': color });
    text.text(label).css('color', color);
});

jQuery(document).ready(function ($) {
    const ajaxUrl = sp_area_dashboard_vars.ajax_url;
    const createProjectNonce = sp_area_dashboard_vars.create_project_nonce;
    const projectDetailsNonce = sp_area_dashboard_vars.project_details_nonce;
    const reviewSubmissionNonce = sp_area_dashboard_vars.review_submission_nonce;
    const awardBidNonce = sp_area_dashboard_vars.award_bid_nonce;

    // --- Load Dashboard Stats ---
    function loadDashboardStats() {
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_area_manager_dashboard_stats',
                nonce: sp_area_dashboard_vars.get_dashboard_stats_nonce,
            },
            success: function (response) {
                if (response.success) {
                    const stats = response.data;
                    $('#total-projects-stat').text(stats.total_projects);
                    $('#completed-projects-stat').text(stats.completed_projects);
                    $('#in-progress-projects-stat').text(stats.in_progress_projects);
                    $('#total-paid-stat').text('₹' + stats.total_paid_to_vendors.toLocaleString());
                    $('#total-profit-stat').text('₹' + stats.total_company_profit.toLocaleString());

                    const ctx = document.getElementById('project-status-chart').getContext('2d');
                    new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: ['Completed', 'In Progress', 'Pending'],
                            datasets: [{
                                label: 'Project Statuses',
                                data: [stats.completed_projects, stats.in_progress_projects, stats.total_projects - stats.completed_projects - stats.in_progress_projects],
                                backgroundColor: [
                                    'rgba(40, 167, 69, 0.5)',
                                    'rgba(255, 193, 7, 0.5)',
                                    'rgba(220, 53, 69, 0.5)',
                                ],
                            }]
                        },
                    });
                }
            }
        });
    }
    loadDashboardStats();

    // --- Navigation ---
    $('.nav-item').on('click', function (e) {
        e.preventDefault();
        const section = $(this).data('section');

        $('.nav-item').removeClass('active');
        $(this).addClass('active');

        $('.section-content').hide();
        $('#' + section + '-section').show();
        $('#section-title').text($(this).text());

        if (section === 'projects') {
            loadProjects();
        } else if (section === 'project-reviews') {
            loadReviews();
        } else if (section === 'vendor-approvals') {
            loadVendorApprovals();
        } else if (section === 'leads') {
            loadLeads();
        } else if (section === 'my-clients') {
            loadMyClients();
        }
    });

    // --- Load Projects ---
    function loadProjects() {
        $('#area-project-list-container').html('<p>Loading projects...</p>');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_area_manager_projects',
                nonce: sp_area_dashboard_vars.get_projects_nonce,
            },
            success: function (response) {
                if (response.success) {
                    let html = '';
                    if (response.data.projects.length > 0) {
                        response.data.projects.forEach(project => {
                            html += `
                                <div class="project-card" data-project-id="${project.id}">
                                    <h4>${project.title}</h4>
                                    <p>Status: <span class="badge status-${project.status}">${project.status}</span></p>
                                    <p>${project.pending_submissions} pending submissions</p>
                                </div>
                            `;
                        });
                    } else {
                        html = '<p>No projects found.</p>';
                    }
                    $('#area-project-list-container').html(html);
                } else {
                    $('#area-project-list-container').html('<p class="text-danger">Error loading projects.</p>');
                }
            }
        });
    }

    // --- Load Reviews ---
    function loadReviews() {
        $('#project-reviews-container').html('<p>Loading reviews...</p>');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_area_manager_reviews',
                nonce: sp_area_dashboard_vars.get_reviews_nonce,
            },
            success: function (response) {
                if (response.success) {
                    let html = '';
                    if (response.data.reviews.length > 0) {
                        response.data.reviews.forEach(review => {
                            html += `
                                <div class="review-item">
                                    <h4>Project: ${review.project_id}</h4>
                                    <p><strong>Step ${review.step_number}:</strong> ${review.step_name}</p>
                                    ${review.image_url ? `<a href="${review.image_url}" target="_blank">View Submission</a>` : ''}
                                    <p><em>${review.vendor_comment || ''}</em></p>
                                    <div class="review-form">
                                        <textarea class="review-comment" placeholder="Add a comment..."></textarea>
                                        <button class="btn btn-success review-btn" data-decision="approved" data-step-id="${review.id}">Approve</button>
                                        <button class="btn btn-danger review-btn" data-decision="rejected" data-step-id="${review.id}">Reject</button>
                                    </div>
                                </div>
                            `;
                        });
                    } else {
                        html = '<p>No pending reviews.</p>';
                    }
                    $('#project-reviews-container').html(html);
                } else {
                    $('#project-reviews-container').html('<p class="text-danger">Error loading reviews.</p>');
                }
            }
        });
    }

    // --- Load Vendor Approvals ---
    function loadVendorApprovals() {
        $('#vendor-approvals-container').html('<p>Loading vendor approvals...</p>');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_area_manager_vendor_approvals',
                nonce: sp_area_dashboard_vars.get_vendor_approvals_nonce,
            },
            success: function (response) {
                if (response.success) {
                    let html = '';
                    if (response.data.vendors.length > 0) {
                        html += '<table class="wp-list-table widefat fixed striped users"><thead><tr><th>Name</th><th>Email</th><th>Action</th></tr></thead><tbody>';
                        response.data.vendors.forEach(vendor => {
                            html += `
                                <tr>
                                    <td>${vendor.display_name}</td>
                                    <td>${vendor.user_email}</td>
                                    <td>
                                        <button class="button button-primary approve-vendor-btn" data-vendor-id="${vendor.ID}">Approve</button>
                                        <button class="button button-secondary deny-vendor-btn" data-vendor-id="${vendor.ID}">Deny</button>
                                    </td>
                                </tr>
                            `;
                        });
                        html += '</tbody></table>';
                    } else {
                        html = '<p>No vendors awaiting approval.</p>';
                    }
                    $('#vendor-approvals-container').html(html);
                } else {
                    $('#vendor-approvals-container').html('<p class="text-danger">Error loading vendor approvals.</p>');
                }
            }
        });
    }

    // --- Create Project ---
    let statesAndCities = [];
    if ($('#project_state').is('select')) {
        $.getJSON(sp_area_dashboard_vars.states_cities_json_url, function (data) {
            statesAndCities = data.states;
            const stateSelect = $('#project_state');
            statesAndCities.forEach(state => {
                stateSelect.append(`<option value="${state.state}">${state.state}</option>`);
            });
        });

        $('#project_state').on('change', function () {
            const selectedState = $(this).val();
            const citySelect = $('#project_city');
            citySelect.empty().append('<option value="">Select City</option>');

            if (selectedState) {
                const stateData = statesAndCities.find(state => state.state === selectedState);
                if (stateData) {
                    stateData.districts.forEach(city => {
                        citySelect.append(`<option value="${city}">${city}</option>`);
                    });
                }
            }
        });
    }

    $('input[name="vendor_assignment_method"]').on('change', function () {
        if ($(this).val() === 'bidding') {
            // Bidding
            $('.vendor-manual-fields').hide();
            $('#assigned_vendor_id, #paid_to_vendor').prop('disabled', true);
        } else {
            // Manual
            $('.vendor-manual-fields').show();
            $('#assigned_vendor_id, #paid_to_vendor').prop('disabled', false);
        }
    });

    $('#create-project-form').on('submit', function (e) {
        e.preventDefault();
        const form = $(this);
        const feedback = $('#create-project-feedback');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'create_solar_project',
                sp_create_project_nonce: createProjectNonce,
                project_title: $('#project_title').val(),
                project_state: $('#project_state').val(),
                project_city: $('#project_city').val(),
                project_status: $('#project_status').val(),
                client_user_id: $('#client_user_id').val(),
                solar_system_size_kw: $('#solar_system_size_kw').val(),
                client_address: $('#client_address').val(),
                client_phone_number: $('#client_phone_number').val(),
                project_start_date: $('#project_start_date').val(),
                project_start_date: $('#project_start_date').val(),
                project_start_date: $('#project_start_date').val(),
                vendor_assignment_method: $('input[name="vendor_assignment_method"]:checked').val(),
                assigned_vendor_id: $('#assigned_vendor_id').val(),
                paid_to_vendor: $('#paid_to_vendor').val(),
            },
            beforeSend: function () {
                form.find('button').prop('disabled', true).text('Creating...');
                feedback.text('').removeClass('text-success text-danger');
            },
            success: function (response) {
                if (response.success) {
                    feedback.text(response.data.message).addClass('text-success');
                    form[0].reset();
                    setTimeout(() => {
                        $('.nav-item[data-section="projects"]').click();
                    }, 1500);
                } else {
                    feedback.text(response.data.message).addClass('text-danger');
                }
            },
            complete: function () {
                form.find('button').prop('disabled', false).text('Create Project');
            }
        });
    });

    // --- Lead Management ---
    function loadLeads() {
        $('#leads-list-container').html('<p>Loading leads...</p>');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_area_manager_leads',
                nonce: sp_area_dashboard_vars.get_leads_nonce,
            },
            success: function (response) {
                if (response.success) {
                    let html = '';
                    if (response.data.leads.length > 0) {
                        html += '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
                        response.data.leads.forEach(lead => {
                            html += `
                                <tr>
                                    <td>${lead.name}</td>
                                    <td>${lead.phone}</td>
                                    <td>${lead.email}</td>
                                    <td><span class="badge status-${lead.status}">${lead.status}</span></td>
                                    <td>
                                        <button class="button button-small open-msg-modal" data-type="email" data-lead-id="${lead.id}" data-recipient="${lead.email}" ${!lead.email ? 'disabled' : ''}>Email</button>
                                        <button class="button button-small open-msg-modal" data-type="whatsapp" data-lead-id="${lead.id}" data-recipient="${lead.phone}" ${!lead.phone ? 'disabled' : ''}>WhatsApp</button>
                                        <button class="button button-small convert-lead-btn" data-lead-name="${lead.name}" data-lead-email="${lead.email}" data-lead-phone="${lead.phone}">Create Client</button>
                                        <button class="button button-small button-link-delete delete-lead-btn" data-lead-id="${lead.id}" style="color:red;">Delete</button>
                                    </td>
                                </tr>
                                <tr><td colspan="5"><small><em>${lead.notes}</em></small></td></tr>
                            `;
                        });
                        html += '</tbody></table>';
                    } else {
                        html = '<p>No leads found.</p>';
                    }
                    $('#leads-list-container').html(html);
                } else {
                    $('#leads-list-container').html('<p class="text-danger">Error loading leads.</p>');
                }
            }
        });
    }

    $('#create-lead-form').on('submit', function (e) {
        e.preventDefault();
        const form = $(this);
        const feedback = $('#create-lead-feedback');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'create_solar_lead',
                nonce: sp_area_dashboard_vars.create_lead_nonce,
                name: $('#lead_name').val(),
                phone: $('#lead_phone').val(),
                email: $('#lead_email').val(),
                status: $('#lead_status').val(),
                notes: $('#lead_notes').val(),
            },
            beforeSend: function () {
                form.find('button').prop('disabled', true).text('Adding...');
                feedback.text('').removeClass('text-success text-danger');
            },
            success: function (response) {
                if (response.success) {
                    feedback.text(response.data.message).addClass('text-success');
                    form[0].reset();
                    loadLeads();
                } else {
                    feedback.text(response.data.message).addClass('text-danger');
                }
            },
            complete: function () {
                form.find('button').prop('disabled', false).text('Add Lead');
            }
        });
    });

    $(document).on('click', '.delete-lead-btn', function () {
        if (!confirm('Are you sure you want to delete this lead?')) return;
        const leadId = $(this).data('lead-id');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'delete_solar_lead',
                nonce: sp_area_dashboard_vars.delete_lead_nonce,
                lead_id: leadId,
            },
            success: function (response) {
                if (response.success) {
                    loadLeads();
                } else {
                    alert(response.data.message);
                }
            }
        });
    });

    // Message Modal
    $(document).on('click', '.open-msg-modal', function (e) {
        e.preventDefault();
        const type = $(this).data('type');
        const leadId = $(this).data('lead-id');
        const recipient = $(this).data('recipient');

        $('#msg_type').val(type);
        $('#msg_lead_id').val(leadId);
        $('#msg_recipient').text(recipient + ' (' + type + ')');
        $('#message-modal').show();
    });

    $('.close-modal').on('click', function () {
        $('#message-modal').hide();
    });

    $('#send-message-form').on('submit', function (e) {
        e.preventDefault();
        const form = $(this);
        const feedback = $('#send-message-feedback');
        const type = $('#msg_type').val();

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'send_lead_message',
                nonce: sp_area_dashboard_vars.send_message_nonce,
                lead_id: $('#msg_lead_id').val(),
                type: type,
                message: $('#msg_content').val(),
            },
            beforeSend: function () {
                form.find('button').prop('disabled', true).text('Sending...');
                feedback.text('');
            },
            success: function (response) {
                if (response.success) {
                    if (type === 'whatsapp' && response.data.whatsapp_url) {
                        window.open(response.data.whatsapp_url, '_blank');
                        feedback.text('WhatsApp opened.').addClass('text-success');
                    } else {
                        feedback.text(response.data.message).addClass('text-success');
                    }
                    setTimeout(() => { $('#message-modal').hide(); form[0].reset(); feedback.text(''); }, 2000);
                } else {
                    feedback.text(response.data.message).addClass('text-danger');
                }
            },
            complete: function () {
                form.find('button').prop('disabled', false).text('Send');
            }
        });
    });

    // --- Create Client ---
    // --- Create Client ---
    // Convert Lead to Client
    $(document).on('click', '.convert-lead-btn', function () {
        const name = $(this).data('lead-name');
        const email = $(this).data('lead-email');

        // Switch to Create Client section
        $('.nav-item[data-section="create-client"]').click();

        // Pre-fill form
        $('#client_name').val(name);
        $('#client_email').val(email);

        // Generate a username suggestion
        const username = email.split('@')[0];
        $('#client_username').val(username);

        $('#create-client-feedback').text('Pre-filled from lead data. Please set a password.').addClass('text-info');
    });

    $('#create-client-form').on('submit', function (e) {
        e.preventDefault();
        const form = $(this);
        const feedback = $('#create-client-feedback');

        const password = $('#client_password').val();

        // No confirm check needed anymore

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'create_client_from_dashboard',
                name: $('#client_name').val(),
                username: $('#client_username').val(),
                email: $('#client_email').val(),
                password: password,
                nonce: sp_area_dashboard_vars.create_client_nonce,
            },
            beforeSend: function () {
                form.find('button').prop('disabled', true).text('Creating...');
                feedback.text('').removeClass('text-success text-danger text-info');
            },
            success: function (response) {
                if (response.success) {
                    feedback.text(response.data.message).addClass('text-success');
                    form[0].reset();
                } else {
                    feedback.text(response.data.message).addClass('text-danger');
                }
            },
            complete: function () {
                form.find('button').prop('disabled', false).text('Create Client');
            }
        });
    });

    // --- Project Details ---
    $('#area-project-list-container').on('click', '.project-card', function () {
        const projectId = $(this).data('project-id');
        loadProjectDetails(projectId);
    });

    $('#back-to-projects-list').on('click', function () {
        $('#project-detail-section').hide();
        $('#projects-section').show();
    });

    function loadProjectDetails(projectId) {
        $('#projects-section').hide();
        $('#project-detail-section').show();

        // Clear previous details
        $('#project-detail-title').text('Loading...');
        $('#project-detail-meta').html('');
        $('#vendor-submissions-list').html('');
        $('#project-bids-list').html('');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_area_manager_project_details',
                nonce: projectDetailsNonce,
                project_id: projectId,
            },
            success: function (response) {
                if (response.success) {
                    const project = response.data;
                    $('#project-detail-title').text(project.title);

                    let metaHtml = '';
                    for (const key in project.meta) {
                        metaHtml += `<div><strong>${key}:</strong> ${project.meta[key]}</div>`;
                    }
                    $('#project-detail-meta').html(metaHtml);

                    let submissionsHtml = '';
                    if (project.submissions.length > 0) {
                        project.submissions.forEach(sub => {
                            submissionsHtml += `
                                <div class="submission-item">
                                    <p><strong>Step ${sub.step_number}:</strong> ${sub.step_name} - <span class="badge status-${sub.admin_status}">${sub.admin_status}</span></p>
                                    ${sub.image_url ? `<a href="${sub.image_url}" target="_blank">View Submission</a>` : ''}
                                    <p><em>${sub.vendor_comment || ''}</em></p>
                                    ${sub.admin_status === 'pending' ? `
                                        <div class="review-form">
                                            <textarea class="review-comment" placeholder="Add a comment..."></textarea>
                                            <button class="btn btn-success review-btn" data-decision="approved" data-step-id="${sub.id}">Approve</button>
                                            <button class="btn btn-danger review-btn" data-decision="rejected" data-step-id="${sub.id}">Reject</button>
                                        </div>
                                    ` : `<p><strong>Admin Comment:</strong> ${sub.admin_comment}</p>`}
                                </div>
                            `;
                        });
                    } else {
                        submissionsHtml = '<p>No submissions yet.</p>';
                    }
                    $('#vendor-submissions-list').html(submissionsHtml);

                    let bidsHtml = '';
                    if (project.bids.length > 0) {
                        project.bids.forEach(bid => {
                            bidsHtml += `
                                <div class="bid-item">
                                    <p><strong>${bid.vendor_name}</strong> - ₹${bid.bid_amount}</p>
                                    <p>${bid.bid_details}</p>
                                    <button class="btn btn-primary award-bid-btn" data-project-id="${projectId}" data-vendor-id="${bid.vendor_id}" data-bid-amount="${bid.bid_amount}">Award Project</button>
                                </div>
                            `;
                        });
                    } else {
                        bidsHtml = '<p>No bids yet.</p>';
                    }
                    $('#project-bids-list').html(bidsHtml);
                } else {
                    $('#project-detail-title').text('Error');
                    $('#project-detail-meta').html(`<p class="text-danger">${response.data.message}</p>`);
                }
            }
        });
    }

    // --- Review Submission ---
    $('#vendor-submissions-list').on('click', '.review-btn', function () {
        const button = $(this);
        const stepId = button.data('step-id');
        const decision = button.data('decision');
        const comment = button.siblings('.review-comment').val();

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'review_vendor_submission',
                nonce: reviewSubmissionNonce,
                step_id: stepId,
                decision: decision,
                comment: comment,
            },
            beforeSend: function () {
                button.prop('disabled', true).text('Processing...');
            },
            success: function (response) {
                if (response.success) {
                    alert(response.data.message);
                    loadProjectDetails(button.closest('.project-detail-card').find('.award-bid-btn').data('project-id'));
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            complete: function () {
                button.prop('disabled', false).text(decision.charAt(0).toUpperCase() + decision.slice(1));
            }
        });
    });

    // --- Award Bid ---
    $('#project-bids-list').on('click', '.award-bid-btn', function () {
        const button = $(this);
        const projectId = button.data('project-id');
        const vendorId = button.data('vendor-id');
        const bidAmount = button.data('bid-amount');

        if (!confirm('Are you sure you want to award this project to this vendor?')) {
            return;
        }

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'award_project_to_vendor',
                nonce: awardBidNonce,
                project_id: projectId,
                vendor_id: vendorId,
                bid_amount: bidAmount,
            },
            beforeSend: function () {
                button.prop('disabled', true).text('Awarding...');
            },
            success: function (response) {
                if (response.success) {
                    alert(response.data.message);
                    loadProjectDetails(projectId);
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            complete: function () {
                button.prop('disabled', false).text('Award Project');
            }
        });
    });
    // --- My Clients ---
    function loadMyClients() {
        $('#my-clients-list-container').html('<p>Loading clients...</p>');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_area_manager_clients',
                nonce: sp_area_dashboard_vars.get_clients_nonce,
            },
            success: function (response) {
                if (response.success) {
                    let html = '';
                    if (response.data.clients.length > 0) {
                        html += '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Name</th><th>Username</th><th>Email</th><th>Actions</th></tr></thead><tbody>';
                        response.data.clients.forEach(client => {
                            html += `
                                <tr>
                                    <td>${client.name}</td>
                                    <td>${client.username}</td>
                                    <td>${client.email}</td>
                                    <td>
                                        <button class="button button-small open-reset-password-modal" data-client-id="${client.id}" data-client-name="${client.name}">Reset Password</button>
                                    </td>
                                </tr>
                            `;
                        });
                        html += '</tbody></table>';
                    } else {
                        html = '<p>No clients found.</p>';
                    }
                    $('#my-clients-list-container').html(html);
                } else {
                    $('#my-clients-list-container').html('<p class="text-danger">Error loading clients.</p>');
                }
            }
        });
    }

    $(document).on('click', '.open-reset-password-modal', function (e) {
        e.preventDefault();
        const clientId = $(this).data('client-id');
        const clientName = $(this).data('client-name');

        $('#reset_password_client_id').val(clientId);
        $('#reset-password-client-name').text(clientName);
        $('#reset-password-modal').show();
    });

    $('#reset-password-form').on('submit', function (e) {
        e.preventDefault();
        const form = $(this);
        const feedback = $('#reset-password-feedback');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'reset_client_password',
                nonce: sp_area_dashboard_vars.reset_password_nonce,
                client_id: $('#reset_password_client_id').val(),
                new_password: $('#new_password').val(),
            },
            beforeSend: function () {
                form.find('button').prop('disabled', true).text('Resetting...');
                feedback.text('');
            },
            success: function (response) {
                if (response.success) {
                    feedback.text(response.data.message).addClass('text-success');
                    setTimeout(() => { $('#reset-password-modal').hide(); form[0].reset(); feedback.text(''); }, 2000);
                } else {
                    feedback.text(response.data.message).addClass('text-danger');
                }
            },
            complete: function () {
                form.find('button').prop('disabled', false).text('Reset Password');
            }
        });
    });

});
