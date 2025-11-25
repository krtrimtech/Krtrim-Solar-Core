jQuery(document).ready(function($) {
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
                nonce: '<?php echo wp_create_nonce("get_dashboard_stats_nonce"); ?>',
            },
            success: function(response) {
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
    $('.nav-item').on('click', function(e) {
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
                security: ajaxUrl,
            },
            success: function(response) {
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
                nonce: '<?php echo wp_create_nonce("get_reviews_nonce"); ?>',
            },
            success: function(response) {
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
                nonce: '<?php echo wp_create_nonce("get_vendor_approvals_nonce"); ?>',
            },
            success: function(response) {
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
    $.getJSON('<?php echo plugin_dir_url( __FILE__ ) . '../../assets/data/indian-states-cities.json'; ?>', function(data) {
        statesAndCities = data.states;
        const stateSelect = $('#project_state');
        statesAndCities.forEach(state => {
            stateSelect.append(`<option value="${state.state}">${state.state}</option>`);
        });
    });

    $('#project_state').on('change', function() {
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

    $('input[name="vendor_assignment_method"]').on('change', function() {
        if ($(this).val() === 'manual') {
            $('.vendor-manual-fields').show();
            $('#assigned_vendor_id, #paid_to_vendor').prop('disabled', false);
        } else {
            $('.vendor-manual-fields').hide();
            $('#assigned_vendor_id, #paid_to_vendor').prop('disabled', true);
        }
    });

    $('#create-project-form').on('submit', function(e) {
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
                vendor_assignment_method: $('input[name="vendor_assignment_method"]:checked').val(),
                assigned_vendor_id: $('#assigned_vendor_id').val(),
                paid_to_vendor: $('#paid_to_vendor').val(),
            },
            beforeSend: function() {
                form.find('button').prop('disabled', true).text('Creating...');
                feedback.text('').removeClass('text-success text-danger');
            },
            success: function(response) {
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
            complete: function() {
                form.find('button').prop('disabled', false).text('Create Project');
            }
        });
    });

    // --- Create Vendor ---
    $('#create-vendor-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const feedback = $('#create-vendor-feedback');
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'create_vendor_from_dashboard',
                username: $('#vendor_username').val(),
                email: $('#vendor_email').val(),
                password: $('#vendor_password').val(),
                nonce: '<?php echo wp_create_nonce("create_vendor_nonce"); ?>',
            },
            beforeSend: function() {
                form.find('button').prop('disabled', true).text('Creating...');
                feedback.text('').removeClass('text-success text-danger');
            },
            success: function(response) {
                if (response.success) {
                    feedback.text(response.data.message).addClass('text-success');
                    form[0].reset();
                } else {
                    feedback.text(response.data.message).addClass('text-danger');
                }
            },
            complete: function() {
                form.find('button').prop('disabled', false).text('Create Vendor');
            }
        });
    });

    // --- Create Client ---
    $('#create-client-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const feedback = $('#create-client-feedback');
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'create_client_from_dashboard',
                username: $('#client_username').val(),
                email: $('#client_email').val(),
                password: $('#client_password').val(),
                nonce: '<?php echo wp_create_nonce("create_client_nonce"); ?>',
            },
            beforeSend: function() {
                form.find('button').prop('disabled', true).text('Creating...');
                feedback.text('').removeClass('text-success text-danger');
            },
            success: function(response) {
                if (response.success) {
                    feedback.text(response.data.message).addClass('text-success');
                    form[0].reset();
                } else {
                    feedback.text(response.data.message).addClass('text-danger');
                }
            },
            complete: function() {
                form.find('button').prop('disabled', false).text('Create Client');
            }
        });
    });

    // --- Project Details ---
    $('#area-project-list-container').on('click', '.project-card', function() {
        const projectId = $(this).data('project-id');
        loadProjectDetails(projectId);
    });

    $('#back-to-projects-list').on('click', function() {
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
            success: function(response) {
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
    $('#vendor-submissions-list').on('click', '.review-btn', function() {
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
            beforeSend: function() {
                button.prop('disabled', true).text('Processing...');
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    loadProjectDetails(button.closest('.project-detail-card').find('.award-bid-btn').data('project-id'));
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            complete: function() {
                button.prop('disabled', false).text(decision.charAt(0).toUpperCase() + decision.slice(1));
            }
        });
    });

    // --- Award Bid ---
    $('#project-bids-list').on('click', '.award-bid-btn', function() {
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
            beforeSend: function() {
                button.prop('disabled', true).text('Awarding...');
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    loadProjectDetails(projectId);
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            complete: function() {
                button.prop('disabled', false).text('Award Project');
            }
        });
    });
});
