// Project Detail Modal Functions
(function ($) {
    'use strict';

    function openProjectModal(projectId) {
        console.log('Opening modal for project:', projectId);

        // Show modal with loading state
        $('#projectDetailModal').addClass('active');
        $('#modalProjectTitle').text('Loading...');
        $('#modalProjectInfo, #modalClientInfo, #modalVendorInfo, #modalProgressSteps').html('<p>Loading...</p>');

        // Fetch project details
        $.ajax({
            url: sp_area_dashboard_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'get_project_details',
                project_id: projectId,
                nonce: sp_area_dashboard_vars.project_details_nonce
            },
            success: function (response) {
                if (response.success) {
                    populateProjectModal(response.data);
                } else {
                    showToast('Failed to load project details', 'error');
                    closeProjectModal();
                }
            },
            error: function () {
                showToast('Error loading project details', 'error');
                closeProjectModal();
            }
        });
    }

    function populateProjectModal(data) {
        $('#modalProjectTitle').text(data.title || 'Project Details');

        let projectHtml = '<div class="detail-item"><div class="detail-label">Status</div><div class="detail-value">' + (data.status || 'N/A') + '</div></div>';
        projectHtml += '<div class="detail-item"><div class="detail-label">Location</div><div class="detail-value">' + (data.project_state || 'N/A') + ', ' + (data.project_city || 'N/A') + '</div></div>';
        projectHtml += '<div class="detail-item"><div class="detail-label">System Size</div><div class="detail-value">' + (data.solar_system_size_kw || 0) + ' kW</div></div>';
        projectHtml += '<div class="detail-item"><div class="detail-label">Start Date</div><div class="detail-value">' + (data.start_date || 'Not set') + '</div></div>';
        projectHtml += '<div class="detail-item"><div class="detail-label">Total Cost</div><div class="detail-value">₹' + Number(data.total_cost || 0).toLocaleString('en-IN') + '</div></div>';
        projectHtml += '<div class="detail-item"><div class="detail-label">Profit</div><div class="detail-value">₹' + Number(data.company_profit || 0).toLocaleString('en-IN') + '</div></div>';
        $('#modalProjectInfo').html(projectHtml);

        let clientHtml = '<div class="detail-item"><div class="detail-label">Name</div><div class="detail-value">' + (data.client_name || 'N/A') + '</div></div>';
        clientHtml += '<div class="detail-item"><div class="detail-label">Phone</div><div class="detail-value">' + (data.client_phone_number || 'N/A') + '</div></div>';
        clientHtml += '<div class="detail-item"><div class="detail-label">Address</div><div class="detail-value">' + (data.client_address || 'N/A') + '</div></div>';
        $('#modalClientInfo').html(clientHtml);

        if (data.vendor_name) {
            let vendorHtml = '<div class="detail-item"><div class="detail-label">Vendor</div><div class="detail-value">' + data.vendor_name + '</div></div>';
            vendorHtml += '<div class="detail-item"><div class="detail-label">Payment</div><div class="detail-value">₹' + Number(data.vendor_paid_amount || 0).toLocaleString('en-IN') + '</div></div>';
            $('#modalVendorInfo').html(vendorHtml);
        } else {
            $('#modalVendorInfo').html('<p style="color: var(--text-secondary);">No vendor assigned yet</p>');
        }

        let steps = data.steps || [];
        let completedSteps = steps.filter(function (s) { return s.admin_status === 'approved'; }).length;
        let progressPercentage = steps.length > 0 ? Math.round((completedSteps / steps.length) * 100) : 0;

        $('#modalProgressPercentage').text(progressPercentage + '%');
        $('#modalProgressBar').css('width', progressPercentage + '%');

        if (steps.length > 0) {
            let stepsHtml = '';
            steps.forEach(function (step, index) {
                let statusClass = step.admin_status === 'approved' ? 'completed' : step.admin_status === 'under_review' ? 'in-progress' : 'pending';
                let icon = step.admin_status === 'approved' ? '✓' : step.admin_status === 'under_review' ? ' ⏳' : (index + 1);

                stepsHtml += '<div class="step-item ' + statusClass + '">';
                stepsHtml += '<div class="step-icon">' + icon + '</div>';
                stepsHtml += '<div class="step-content">';
                stepsHtml += '<h4 class="step-title">' + (step.step_name || 'Step ' + (index + 1)) + '</h4>';
                stepsHtml += '<p class="step-description">' + (step.description || '') + '</p>';
                if (step.vendor_comment) {
                    stepsHtml += '<div class="step-meta"><span>💬 ' + step.vendor_comment + '</span></div>';
                }
                stepsHtml += '</div></div>';
            });
            $('#modalProgressSteps').html(stepsHtml);

            // Add "View Reviews" button if there are pending reviews
            let pendingReviews = steps.filter(s => s.admin_status === 'under_review').length;
            if (pendingReviews > 0) {
                $('#modalProgressSteps').append(`
                    <button id="viewProjectReviewsBtn" 
                            data-project-id="${data.project_id}" 
                            style="width: 100%; margin-top: 15px; padding: 12px; background: #3b82f6; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px; transition: background 0.2s;"
                            onmouseover="this.style.background='#2563eb'"
                            onmouseout="this.style.background='#3b82f6'">
                        📝 View Pending Reviews (${pendingReviews})
                    </button>
                `);
            }
        } else {
            $('#modalProgressSteps').html('<p style="color: var(--text-secondary);">No progress steps configured</p>');
        }
    }

    function closeProjectModal() {
        $('#projectDetailModal').removeClass('active');
    }

    // Close modal button - multiple selectors for compatibility
    $(document).on('click', '.modal-close, .project-modal .modal-close, button.modal-close', function (e) {
        e.preventDefault();
        console.log('Close button clicked');
        closeProjectModal();
    });

    // Close modal on background click
    $(document).on('click', '#projectDetailModal, .project-modal', function (e) {
        if (e.target.id === 'projectDetailModal' || $(e.target).hasClass('project-modal')) {
            closeProjectModal();
        }
    });

    // Close on Escape key
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $('#projectDetailModal').hasClass('active')) {
            closeProjectModal();
        }
    });

    // Delete project handler
    $(document).on('click', '.delete-project', function () {
        const projectId = $(this).data('id');
        if (confirm('Are you sure you want to delete this project? This action cannot be undone.')) {
            $.ajax({
                url: sp_area_dashboard_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'delete_solar_project',
                    project_id: projectId,
                    nonce: sp_area_dashboard_vars.nonce
                },
                success: function (response) {
                    if (response.success) {
                        showToast('Project deleted successfully', 'success');
                        if (typeof loadProjects === 'function') loadProjects();
                    } else {
                        showToast(response.data.message || 'Failed to delete project', 'error');
                    }
                }
            });
        }
    });

    // Delete lead handler
    $(document).on('click', '.delete-lead', function () {
        const leadId = $(this).data('lead-id');
        if (confirm('Are you sure you want to delete this lead?')) {
            $.ajax({
                url: sp_area_dashboard_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'delete_solar_lead',
                    lead_id: leadId,
                    nonce: sp_area_dashboard_vars.nonce
                },
                success: function (response) {
                    if (response.success) {
                        showToast('Lead deleted successfully', 'success');
                        if (typeof loadLeads === 'function') loadLeads();
                    } else {
                        showToast(response.data.message || 'Failed to delete lead', 'error');
                    }
                }
            });
        }
    });

    // View project details button
    $(document).on('click', '.view-project-details', function () {
        const projectId = $(this).data('id');
        openProjectModal(projectId);
    });


    // View project reviews button handler
    $(document).on('click', '#viewProjectReviewsBtn', function () {
        const projectId = $(this).data('project-id');
        
        // Close the modal
        closeProjectModal();
        
        // Switch to project reviews tab
        $('.nav-btn[data-tab="project-reviews"]').click();
        
        // Wait for reviews to load, then expand the specific project
        setTimeout(function () {
            const projectCard = $('.project-review-card[data-project-id="' + projectId + '"]');
            if (projectCard.length) {
                // Scroll to the project card
                $('html, body').animate({
                    scrollTop: projectCard.offset().top - 100
                }, 500);
                
                // Expand the reviews
                if (typeof toggleProjectReviews === 'function') {
                    toggleProjectReviews(projectId);
                }
            }
        }, 700);
    });
    // Make functions globally available
    window.openProjectModal = openProjectModal;
    window.closeProjectModal = closeProjectModal;

})(jQuery);
