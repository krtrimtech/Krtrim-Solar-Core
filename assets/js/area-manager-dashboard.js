/**
 * Area Manager Dashboard JavaScript  
 */
(function ($) {
    'use strict';

    console.log('Area Manager Dashboard Script Initialized');

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

    //Navigation (Global) ---
    console.log('Attaching click handler to .area-manager-dashboard .nav-item');
    $(document).on('click', '.area-manager-dashboard .nav-item', function (e) {
        console.log('=== NAV CLICK DETECTED ===');
        e.preventDefault();
        e.stopPropagation();

        const section = $(this).data('section');
        const sectionId = '#' + section + '-section';

        console.log('Clicked section:', section);
        console.log('Target selector:', sectionId);
        console.log('Target element exists:', $(sectionId).length > 0);
        console.log('All .section-content elements:', $('.section-content').length);

        // Remove active class
        $('.area-manager-dashboard .nav-item').removeClass('active');
        $(this).addClass('active');
        console.log('Active class updated');

        // Hide all sections
        $('.section-content').each(function () {
            console.log('Hiding:', this.id, 'Current display:', $(this).css('display'));
            $(this).hide();
        });

        // Show target section
        console.log('Showing section:', sectionId);
        $(sectionId).show();
        console.log('Target section display after show():', $(sectionId).css('display'));

        $('#section-title').text($(this).text());

        // Trigger data load if needed
        if (typeof loadSectionData === 'function') {
            loadSectionData(section);
        } else {
            $(document).trigger('area-manager-nav-click', [section]);
        }

        console.log('=== NAV CLICK COMPLETE ===');
    });

    // --- Toast Notification Helper ---
    function showToast(message, type = 'info') {
        const toastContainer = $('#toast-container');
        const toastId = 'toast-' + Date.now();

        const toast = $(`
                <div class="toast ${type}" id="${toastId}">
                    <div class="toast-icon"></div>
                    <div class="toast-message">${message}</div>
                    <button class="toast-close">×</button>
                </div>
            `);

        toastContainer.append(toast);

        // Close button click
        toast.find('.toast-close').on('click', function () {
            removeToast(toastId);
        });

        // Auto remove after 3 seconds
        setTimeout(() => {
            removeToast(toastId);
        }, 3000);
    }

    function removeToast(toastId) {
        const toast = $('#' + toastId);
        if (toast.length) {
            toast.addClass('removing');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }
    }

    // Make showToast globally available
    // --- Initialize AJAX URL and nonces FIRST (before any functions use them) ---
    const ajaxUrl = (typeof sp_area_dashboard_vars !== 'undefined') ? sp_area_dashboard_vars.ajax_url : '';

    if (!ajaxUrl) {
        console.error('Area Manager Dashboard: sp_area_dashboard_vars is undefined or missing ajax_url.');
        return;
    }

    const createProjectNonce = sp_area_dashboard_vars.create_project_nonce;
    const projectDetailsNonce = sp_area_dashboard_vars.project_details_nonce;

    // --- Notification System ---
    function loadNotifications() {
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_user_notifications'
            },
            success: function (response) {
                if (response.success) {
                    const notifications = response.data || [];
                    const notifList = $('#notif-list');
                    const notifCount = $('#notif-count');
                    
                    if (notifications.length > 0) {
                        let html = '';
                        notifications.forEach(n => {
                            const borderColor = n.type === 'approved' ? '#28a745' : n.type === 'rejected' ? '#dc3545' : '#007bff';
                            const bgColor = n.type === 'approved' ? '#f8fff9' : n.type === 'rejected' ? '#fff5f5' : '#f0f7ff';
                            
                            html += `<div class="notification-item" style="padding:12px;position:relative; border-radius:8px; border-left:4px solid ${borderColor}; background:${bgColor}; margin-bottom:10px;">`;
                            html += `<div style="font-weight:600; color:#333;">${n.icon || '🔔'} ${n.title}</div>`;
                            html += `<div style="font-size:12px; color:#666; margin-top:4px;">${n.message}</div>`;
                            if (n.time_ago) {
                                html += `<div style="font-size:10px; color:#999; margin-top:4px;">${n.time_ago}</div>`;
                            }
                            html += `</div>`;
                        });
                        notifList.html(html);
                        notifCount.text(notifications.length).show();
                    } else {
                        notifList.html('<p style="text-align:center; color:#999; padding: 20px; margin: 0;">No new notifications</p>');
                        notifCount.hide();
                    }
                } else {
                    $('#notif-list').html('<p style="color:#dc3545; text-align:center; padding: 20px; margin:0;">Error loading notifications</p>');
                }
            },
            error: function () {
                $('#notif-list').html('<p style="color:#dc3545; text-align:center; padding: 20px; margin:0;">Error loading notifications</p>');
            }
        });
    }

    // Toggle notification panel
    $(document).on('click', '#notification-toggle', function() {
        $('#notification-panel').toggleClass('open');
    });

    // Close notification panel
    $(document).on('click', '#close-notification-panel', function() {
        $('#notification-panel').removeClass('open');
    });

    // Load notifications on page load
    loadNotifications();
    // Refresh every 30 seconds
    setInterval(loadNotifications, 30000);

    // --- End Notification System ---

    const reviewSubmissionNonce = sp_area_dashboard_vars.review_submission_nonce;
    const awardBidNonce = sp_area_dashboard_vars.award_bid_nonce;

    // --- Dashboard Charts & Stats ---
    let projectStatusChart, monthlyTrendChart, financialChart, leadChart;

    function loadDashboardStats() {
        console.log('Loading dashboard stats...');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_area_manager_dashboard_stats',
                nonce: sp_area_dashboard_vars.get_dashboard_stats_nonce,
            },
            success: function (response) {
                console.log('Stats response:', response);
                if (response.success) {
                    updateDashboardStats(response.data);
                    initializeCharts(response.data);
                } else {
                    console.error('Stats error:', response);
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX error loading stats:', error);
            }
        });
    }

    function updateDashboardStats(stats) {
        console.log('Updating stats:', stats);
        $('#total-projects-stat').text(stats.total_projects || 0);
        $('#total-revenue-stat').text('₹' + (stats.total_revenue || 0).toLocaleString('en-IN'));
        $('#client-payments-stat').text('₹' + (stats.total_client_payments || 0).toLocaleString('en-IN'));
        $('#outstanding-balance-stat').text('₹' + (stats.total_outstanding || 0).toLocaleString('en-IN'));
        $('#total-costs-stat').text('₹' + (stats.total_costs || 0).toLocaleString('en-IN'));
        $('#total-profit-stat').text('₹' + (stats.total_profit || 0).toLocaleString('en-IN'));
        $('#profit-margin-stat').text((stats.profit_margin || 0).toFixed(1) + '%');
        $('#collection-rate-stat').text((stats.collection_rate || 0).toFixed(1) + '%');
        $('#total-leads-stat').text(stats.total_leads || 0);
    }

    function initializeCharts(stats) {
        if (typeof Chart === 'undefined') {
            console.log('Chart.js not loaded');
            return;
        }
        console.log('Charts would be initialized here with:', stats);
        // Destroy existing charts if they exist
        if (projectStatusChart) projectStatusChart.destroy();
        if (monthlyTrendChart) monthlyTrendChart.destroy();
        if (financialChart) financialChart.destroy();
        if (leadChart) leadChart.destroy();

        // 1. Project Status Pie Chart
        const statusCtx = document.getElementById('project-status-chart');
        if (statusCtx) {
            projectStatusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Pending', 'In Progress', 'Completed'],
                    datasets: [{
                        data: [
                            stats.project_status?.pending || 0,
                            stats.project_status?.in_progress || 0,
                            stats.project_status?.completed || 0
                        ],
                        backgroundColor: ['#f59e0b', '#3b82f6', '#10b981'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }

        // 2. Monthly Trend Bar Chart
        const trendCtx = document.getElementById('monthly-trend-chart');
        if (trendCtx) {
            monthlyTrendChart = new Chart(trendCtx, {
                type: 'bar',
                data: {
                    labels: stats.monthly_data?.labels || ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Projects',
                        data: stats.monthly_data?.values || [0, 0, 0, 0, 0, 0],
                        backgroundColor: '#4f46e5',
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        // 3. Financial Overview Chart
        const finCtx = document.getElementById('financial-chart');
        if (finCtx) {
            financialChart = new Chart(finCtx, {
                type: 'line',
                data: {
                    labels: stats.monthly_data?.labels || ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [
                        {
                            label: 'Revenue',
                            data: stats.financial_data?.revenue || [0, 0, 0, 0, 0, 0],
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Client Payments',
                            data: stats.financial_data?.payments || [0, 0, 0, 0, 0, 0],
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Vendor Costs',
                            data: stats.financial_data?.costs || [0, 0, 0, 0, 0, 0],
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }

        // 4. Lead Conversion Chart
        const leadCtx = document.getElementById('lead-chart');
        if (leadCtx) {
            leadChart = new Chart(leadCtx, {
                type: 'pie',
                data: {
                    labels: ['Converted', 'Pending', 'Lost'],
                    datasets: [{
                        data: [
                            stats.lead_data?.converted || 0,
                            stats.lead_data?.pending || 0,
                            stats.lead_data?.lost || 0
                        ],
                        backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }
    }

    // Load stats when dashboard section is shown
    $(document).on('area-manager-nav-click', function (e, section) {
        if (section === 'dashboard') {
            loadDashboardStats();
        }
    });

    // Initial load
    if ($('#dashboard-section').is(':visible')) {
        loadDashboardStats();
    }

    // Global section data loader
    window.loadSectionData = function (section) {
        console.log('Loading section data for:', section);
        if (section === 'dashboard') {
            loadDashboardStats();
        } else if (section === 'projects') {
            loadProjects();
        } else if (section === 'project-reviews') {
            loadReviews();
        } else if (section === 'bid-management') {
            loadBids();
        } else if (section === 'vendor-approvals') {
            loadVendorApprovals();
        } else if (section === 'leads') {
            console.log('Triggering loadLeads...');
            loadLeads();
        } else if (section === 'my-clients') {
            loadMyClients();
        }
    };

    // Load data when section becomes visible
    $(document).on('click', '.nav-item', function () {
        const section = $(this).data('section');
        console.log('Nav clicked, section:', section);

        // Reset create form when navigating to it (unless we're in edit mode)
        if (section === 'create-project' && !$('#edit_project_id').val()) {
            resetCreateForm();
        }

        setTimeout(function () {
            loadSectionData(section);
        }, 100);
    });

    // Initial load - check if dashboard is visible and load it
    $(document).ready(function () {
        console.log('Document ready, checking initial section');
        setTimeout(function () {
            if ($('#dashboard-section').is(':visible')) {
                console.log('Dashboard visible on load, loading stats');
                loadDashboardStats();
            }
            if ($('#leads-section').is(':visible')) {
                console.log('Leads visible on load, loading leads');
                loadLeads();
            }
        }, 500);
    });

    // Handle event from global handler
    $(document).on('area-manager-nav-click', function (e, section) {
        window.loadSectionData(section);
    });

    // Initial load if dashboard is visible
    setTimeout(function () {
        if ($('#dashboard-section').is(':visible')) {
            console.log('Initial dashboard stats load');
            loadDashboardStats();
        }
    }, 500);


    // Navigation handler moved to global scope for reliability

    // --- Load Projects with Filters ---
    let allProjects = [];

    function loadProjects() {
        $('#area-project-list-container').html('<div class="loading-spinner"><div class="spinner"></div><p>Loading projects...</p></div>');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_area_manager_projects',
                nonce: sp_area_dashboard_vars.get_projects_nonce,
            },
            success: function (response) {
                if (response.success) {
                    allProjects = response.data.projects;
                    renderProjects(allProjects);
                }
            }
        });
    }

    function renderProjects(projects) {
        if (!projects || projects.length === 0) {
            $('#area-project-list-container').html('<div class="empty-state"><div class="empty-state-icon">📋</div><h3>No Projects</h3></div>');
            return;
        }
        let html = '<div class="leads-grid">';
        projects.forEach(project => {
            const statusClass = 'status-' + (project.status || 'pending');
            const statusText = (project.status || 'pending').replace(/_/g, ' ').toUpperCase();
            const totalCost = Number(project.total_cost || 0);
            const paidAmount = Number(project.paid_amount || 0);
            const balance = totalCost - paidAmount;

            html += `
                    <div class="lead-card">
                        <div class="lead-card-header">
                            <h3 class="lead-card-title">${project.title}</h3>
                            <span class="lead-card-status ${statusClass}">${statusText}</span>
                        </div>
                        <div class="lead-card-body">
                            <div class="lead-info">📍 ${project.project_city || ''}, ${project.project_state || ''}</div>
                            <div class="lead-info">⚡ ${project.solar_system_size_kw || 0} kW</div>
                            <div class="lead-info">💰 Total: ₹${totalCost.toLocaleString()}</div>
                            <div class="lead-info">💵 Paid: ₹${paidAmount.toLocaleString()}</div>
                            <div class="lead-info" style="font-weight: 600; color: ${balance > 0 ? '#ff9800' : '#4CAF50'}">
                                💳 Balance: ₹${balance.toLocaleString()}
                            </div>
                            ${project.vendor_name ? `<div class="lead-info">👤 Vendor: ${project.vendor_name}</div>` : ''}
                            ${project.pending_submissions > 0 ? `<div class="lead-info" style="color: #ff9800;">🟡 ${project.pending_submissions} pending review(s)</div>` : ''}
                        </div>
                        <div class="lead-card-actions">
                            <button class="action-btn action-btn-primary view-project-details" data-id="${project.id}">👁️ View</button>
                            <button class="action-btn action-btn-secondary edit-project" data-id="${project.id}">✏️ Edit</button>
                        </div>
                    </div>
                `;
        });
        html += '</div>';
        $('#area-project-list-container').html(html);
    }

    function filterProjects() {
        const status = $('#filter-status').val();
        const datePreset = $('#filter-date-preset').val();
        const customDate = $('#filter-custom-date').val();

        let filtered = allProjects.filter(p => {
            // Status filter
            if (status && p.status !== status) return false;

            // Date filter
            if (datePreset) {
                const projectDate = new Date(p.start_date || p.created_at);
                const today = new Date();
                today.setHours(0, 0, 0, 0);

                if (datePreset === 'today') {
                    const pDate = new Date(projectDate);
                    pDate.setHours(0, 0, 0, 0);
                    if (pDate.getTime() !== today.getTime()) return false;
                } else if (datePreset === 'yesterday') {
                    const yesterday = new Date(today);
                    yesterday.setDate(yesterday.getDate() - 1);
                    const pDate = new Date(projectDate);
                    pDate.setHours(0, 0, 0, 0);
                    if (pDate.getTime() !== yesterday.getTime()) return false;
                } else if (datePreset === 'week') {
                    const weekAgo = new Date(today);
                    weekAgo.setDate(weekAgo.getDate() - 7);
                    if (projectDate < weekAgo) return false;
                } else if (datePreset === 'custom' && customDate) {
                    const selectedDate = new Date(customDate);
                    const pDate = new Date(projectDate);
                    pDate.setHours(0, 0, 0, 0);
                    selectedDate.setHours(0, 0, 0, 0);
                    if (pDate.getTime() !== selectedDate.getTime()) return false;
                }
            }

            return true;
        });

        renderProjects(filtered);
    }

    // Clear filters button
    $(document).on('click', '.clear-project-filters-btn', function () {
        $('#filter-status, #filter-date-preset, #filter-custom-date').val('');
        $('#custom-date-wrapper').hide();
        renderProjects(allProjects);
    });

    window.clearProjectFilters = function () {
        $('#filter-status, #filter-date-preset, #filter-custom-date').val('');
        $('#custom-date-wrapper').hide();
        renderProjects(allProjects);
    };

    // Show/hide custom date input and trigger filter
    $(document).on('change', '#filter-date-preset', function () {
        const preset = $(this).val();
        if (preset === 'custom') {
            $('#custom-date-wrapper').show();
            // Set default to today if no date selected
            if (!$('#filter-custom-date').val()) {
                const today = new Date().toISOString().split('T')[0];
                $('#filter-custom-date').val(today);
            }
            // Trigger filter with the date
            filterProjects();
        } else {
            $('#custom-date-wrapper').hide();
            $('#filter-custom-date').val(''); // Clear custom date when switching away
            filterProjects();
        }
    });

    // Trigger filter when custom date is changed via calendar
    $(document).on('change', '#filter-custom-date', function () {
        if ($('#filter-date-preset').val() === 'custom') {
            console.log('Custom date selected:', $(this).val());
            filterProjects();
        }
    });

    // Attach filter listeners for status
    $(document).on('change', '#filter-status', filterProjects);

    // View project details button handler
    $(document).on('click', '.view-project-details', function () {
        const projectId = $(this).data('id');
        console.log('View project details:', projectId);
        if (typeof openProjectModal === 'function') {
            openProjectModal(projectId);
        } else {
            console.error('openProjectModal function not found');
        }
    });

    // Edit Project - Navigate to create form and pre-fill
    $(document).on('click', '.edit-project', function () {
        const projectId = $(this).data('id');
        // Navigate to create project section
        $('.nav-item[data-section="create-project"]').click();
        // Wait for section to be visible, then load project data
        setTimeout(() => {
            loadProjectForEdit(projectId);
        }, 100);
    });

    // Load Project Data for Editing (reuses create form)
    function loadProjectForEdit(projectId) {
        console.log('Loading project for edit:', projectId);

        // Show loading state
        const submitBtn = $('#create-project-form button[type="submit"]');
        const originalText = submitBtn.text();
        submitBtn.prop('disabled', true).text('Loading...');

        // Fetch project details
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
                    populateCreateFormForEdit(response.data, projectId);
                } else {
                    showToast('Error loading project: ' + (response.data.message || 'Unknown error'), 'error');
                    submitBtn.prop('disabled', false).text(originalText);
                }
            },
            error: function (xhr, status, error) {
                console.error('Error fetching project:', error);
                showToast('Failed to load project data', 'error');
                submitBtn.prop('disabled', false).text(originalText);
            }
        });
    }

    // Populate Create Form with Project Data for Editing
    function populateCreateFormForEdit(data, projectId) {
        const project = data.project;
        const meta = data.meta;

        console.log('Populating create form for edit:', data);

        // Add hidden field to track edit mode
        if ($('#edit_project_id').length === 0) {
            $('#create-project-form').prepend('<input type="hidden" id="edit_project_id" name="edit_project_id">');
        }
        $('#edit_project_id').val(projectId);

        // Update form title and button
        $('#create-project-section h3').text('✏️ Edit Project: ' + project.title);
        $('#create-project-form button[type="submit"]').text('Update Project').prop('disabled', false);

        // Set basic fields
        $('#project_title').val(project.title || '');
        $('#project_description').val(project.description || '');

        // Set meta fields
        $('#solar_system_size_kw').val(meta.solar_system_size_kw || '');
        $('#client_address').val(meta.client_address || '');
        $('#client_phone_number').val(meta.client_phone_number || '');
        $('#project_start_date').val(meta.project_start_date || '');
        $('#total_project_cost').val(meta.total_project_cost || '');
        $('#paid_amount').val(meta.paid_amount || '');
        $('#project_status').val(meta.project_status || 'pending');
        $('#client_user_id').val(meta.client_user_id || '');

        // Handle state/city - only if dropdowns exist (admin/manager without location)
        if ($('#project_state').is('select')) {
            $('#project_state').val(meta.project_state || '');
            $('#project_state').trigger('change');
            // Set city after cities are loaded
            setTimeout(() => {
                $('#project_city').val(meta.project_city || '');
            }, 150);
        }

        // Set vendor assignment method
        const vendorMethod = meta.vendor_assignment_method || 'bidding';
        if (vendorMethod === 'manual') {
            $('input[name="vendor_assignment_method"][value="manual"]').prop('checked', true).trigger('change');
            $('#assigned_vendor_id').val(meta.assigned_vendor_id || '');
            $('#paid_to_vendor').val(meta.vendor_paid_amount || '');
        } else {
            $('input[name="vendor_assignment_method"][value="bidding"]').prop('checked', true).trigger('change');
        }
    }

    // Reset Create Form to Create Mode
    function resetCreateForm() {
        // Remove edit project ID
        $('#edit_project_id').remove();

        // Reset form
        $('#create-project-form')[0].reset();

        // Reset title and button
        $('#create-project-section h3').text('Create New Solar Project');
        $('#create-project-form button[type="submit"]').text('Create Project');

        // Reset feedback
        $('#create-project-feedback').text('').removeClass('text-success text-danger');
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

    // --- Create/Edit Project Form Submission ---
    $('#create-project-form').on('submit', function (e) {
        e.preventDefault();
        const form = $(this);
        const feedback = $('#create-project-feedback');

        // Check if we're in edit mode
        const projectId = $('#edit_project_id').val();
        const isEdit = !!projectId;
        const action = isEdit ? 'update_solar_project' : 'create_solar_project';
        const nonce = isEdit ? sp_area_dashboard_vars.update_project_nonce : createProjectNonce;
        const nonceField = isEdit ? 'sp_update_project_nonce' : 'sp_create_project_nonce';

        const formData = {
            action: action,
            [nonceField]: nonce,
            project_title: $('#project_title').val(),
            project_description: $('#project_description').val(),
            project_state: $('#project_state').val(),
            project_city: $('#project_city').val(),
            project_status: $('#project_status').val(),
            client_user_id: $('#client_user_id').val(),
            solar_system_size_kw: $('#solar_system_size_kw').val(),
            client_address: $('#client_address').val(),
            client_phone_number: $('#client_phone_number').val(),
            project_start_date: $('#project_start_date').val(),
            total_project_cost: $('#total_project_cost').val(),
            paid_amount: $('#paid_amount').val(),
            vendor_assignment_method: $('input[name="vendor_assignment_method"]:checked').val(),
            assigned_vendor_id: $('#assigned_vendor_id').val(),
            paid_to_vendor: $('#paid_to_vendor').val(),
        };

        // Add project_id if editing
        if (isEdit) {
            formData.project_id = projectId;
        }

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            beforeSend: function () {
                const btnText = isEdit ? 'Updating...' : 'Creating...';
                form.find('button[type="submit"]').prop('disabled', true).text(btnText);
                feedback.text('').removeClass('text-success text-danger');
            },
            success: function (response) {
                if (response.success) {
                    showToast(response.data.message, 'success');
                    feedback.text(response.data.message).addClass('text-success');

                    // Reset form and navigate
                    setTimeout(() => {
                        resetCreateForm();
                        // Navigate to projects section
                        $('.nav-item[data-section="projects"]').click();
                    }, 1500);
                } else {
                    feedback.text(response.data.message || 'Error saving project').addClass('text-danger');
                    showToast(response.data.message || 'Error saving project', 'error');
                }
            },
            error: function (xhr, status, error) {
                console.error('Project save error:', error, xhr.responseText);
                feedback.text('AJAX error: ' + error).addClass('text-danger');
                showToast('Failed to save project. Check console.', 'error');
            },
            complete: function () {
                const btnText = isEdit ? 'Update Project' : 'Create Project';
                form.find('button[type="submit"]').prop('disabled', false).text(btnText);
            }
        });
    });


    // --- Lead Management ---
    function loadLeads() {
        console.log('🔄 Loading leads...');
        $('#area-leads-container').html('<p>Loading leads...</p>');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_area_manager_leads',
                nonce: sp_area_dashboard_vars.get_leads_nonce,
            },
            success: function (response) {
                console.log('✅ Leads loaded:', response);
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
                    $('#area-leads-container').html(html);
                } else {
                    console.error('❌ Lead loading failed:', response);
                    $('#area-leads-container').html('<p class="text-danger">Error loading leads.</p>');
                }
            },
            error: function (xhr, status, error) {
                console.error('❌ AJAX error:', error);
                $('#area-leads-container').html('<p class="text-danger">AJAX error. Check console.</p>');
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
                    showToast(response.data.message, 'success');
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
                    showToast(response.data.message, 'error');
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
                        showToast(response.data.message, 'success');
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
                    showToast(response.data.message, 'success');
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
                    showToast(response.data.message, 'error');
                    loadProjectDetails(button.closest('.project-detail-card').find('.award-bid-btn').data('project-id'));
                } else {
                    showToast('Error: ' + response.data.message, 'error');
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
                    showToast(response.data.message, 'error');
                    loadProjectDetails(projectId);
                } else {
                    showToast('Error: ' + response.data.message, 'error');
                }
            },
            complete: function () {
                button.prop('disabled', false).text('Award Project');
            }
        });
    });
    // --- My Clients ---
    function loadMyClients() {
        $('#my-clients-container').html('<p>Loading clients...</p>');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_area_manager_clients',
                nonce: sp_area_dashboard_vars.get_clients_nonce,
            },
            success: function (response) {
                console.log('Clients loaded:', response);
                if (response.success) {
                    let html = '';
                    if (response.data.clients && response.data.clients.length > 0) {
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
                    $('#my-clients-container').html(html);
                } else {
                    $('#my-clients-container').html('<p class="text-danger">Error: ' + (response.data?.message || 'Failed to load clients') + '</p>');
                }
            },
            error: function (xhr, status, error) {
                console.error('Client load AJAX error:', error);
                $('#my-clients-container').html('<p class="text-danger">Error loading clients. Please try again.</p>');
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
                    showToast(response.data.message, 'success');
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

})(jQuery);