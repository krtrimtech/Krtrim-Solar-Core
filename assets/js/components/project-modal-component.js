/**
 * Project Modal Component
 * Shared logic for displaying Project Details in Manager and Area Manager dashboards.
 * Dependencies: jQuery, DashboardUtils
 */

(function ($) {
    'use strict';

    window.ProjectModalComponent = {
        config: {
            ajaxUrl: '',
            nonces: {},
            selectors: {
                modal: '#projectDetailModal',
                title: '#modalProjectTitle',
                projectInfo: '#modalProjectInfo',
                clientInfo: '#modalClientInfo',
                vendorInfo: '#modalVendorInfo',
                progressSteps: '#modalProgressSteps',
                progressBar: '#modalProgressBar',
                progressPercentage: '#modalProgressPercentage',
                closeBtn: '.modal-close'
            }
        },

        /**
         * Initialize the component
         * @param {object} config - Configuration object (ajaxUrl, nonces)
         */
        init: function (config) {
            this.config = $.extend(true, {}, this.config, config);
            this.bindEvents();
            // console.log('ProjectModalComponent Initialized');
        },

        bindEvents: function () {
            const self = this;
            const s = self.config.selectors;

            // Close modal handlers
            $(document).on('click', s.closeBtn, function (e) {
                e.preventDefault();
                self.closeModal();
            });

            $(document).on('click', s.modal, function (e) {
                if (e.target === this) {
                    self.closeModal();
                }
            });

            $(document).on('keydown', function (e) {
                if (e.key === 'Escape' && $(s.modal).hasClass('active')) {
                    self.closeModal();
                }
            });

            // View Project Details Trigger
            $(document).on('click', '.view-project-details', function (e) {
                e.preventDefault();
                const projectId = $(this).data('id');
                self.openModal(projectId);
            });

            // View Reviews Button (Navigate to reviews tab)
            $(document).on('click', '#viewProjectReviewsBtn', function () {
                const projectId = $(this).data('project-id');
                self.closeModal();
                self.navigateToReviews(projectId);
            });
        },

        openModal: function (projectId) {
            const self = this;
            const s = self.config.selectors;

            // Show modal with loading state
            $(s.modal).addClass('active');
            $(s.title).text('Loading...');
            $(s.projectInfo).html('<div class="loading-spinner"></div> Loading...');
            $(s.clientInfo).empty();
            $(s.vendorInfo).empty();
            $(s.progressSteps).empty();

            // Fetch details
            $.ajax({
                url: self.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'get_project_details',
                    project_id: projectId,
                    nonce: self.config.nonces.project_details_nonce
                },
                success: function (response) {
                    if (response.success) {
                        self.render(response.data);
                    } else {
                        if (typeof DashboardUtils !== 'undefined') {
                            DashboardUtils.showToast(response.data.message || 'Failed to load project details', 'error');
                        }
                        self.closeModal();
                    }
                },
                error: function () {
                    if (typeof DashboardUtils !== 'undefined') {
                        DashboardUtils.showToast('Network error loading project details', 'error');
                    }
                    self.closeModal();
                }
            });
        },

        closeModal: function () {
            $(this.config.selectors.modal).removeClass('active');
        },

        render: function (data) {
            const s = this.config.selectors;

            $(s.title).text(data.title || 'Project Details');

            // Render Project Info
            let projectHtml = `
                <div class="detail-item"><div class="detail-label">Status</div><div class="detail-value">${data.status || 'N/A'}</div></div>
                <div class="detail-item"><div class="detail-label">Location</div><div class="detail-value">${data.project_state || 'N/A'}, ${data.project_city || 'N/A'}</div></div>
                <div class="detail-item"><div class="detail-label">System Size</div><div class="detail-value">${data.solar_system_size_kw || 0} kW</div></div>
                <div class="detail-item"><div class="detail-label">Start Date</div><div class="detail-value">${data.start_date || 'Not set'}</div></div>
                <div class="detail-item"><div class="detail-label">Total Cost</div><div class="detail-value">₹${Number(data.total_cost || 0).toLocaleString('en-IN')}</div></div>
                <div class="detail-item"><div class="detail-label">Profit</div><div class="detail-value">₹${Number(data.company_profit || 0).toLocaleString('en-IN')}</div></div>
            `;
            $(s.projectInfo).html(projectHtml);

            // Render Client Info
            let clientHtml = `
                <div class="detail-item"><div class="detail-label">Name</div><div class="detail-value">${data.client_name || 'N/A'}</div></div>
                <div class="detail-item"><div class="detail-label">Phone</div><div class="detail-value">${data.client_phone_number || 'N/A'}</div></div>
                <div class="detail-item"><div class="detail-label">Address</div><div class="detail-value">${data.client_address || 'N/A'}</div></div>
            `;
            $(s.clientInfo).html(clientHtml);

            // Render Vendor Info
            if (data.vendor_name) {
                let vendorHtml = `
                    <div class="detail-item"><div class="detail-label">Vendor</div><div class="detail-value">${data.vendor_name}</div></div>
                    <div class="detail-item"><div class="detail-label">Payment</div><div class="detail-value">₹${Number(data.vendor_paid_amount || 0).toLocaleString('en-IN')}</div></div>
                `;
                $(s.vendorInfo).html(vendorHtml);
            } else {
                $(s.vendorInfo).html('<p style="color: var(--text-secondary);">No vendor assigned yet</p>');
            }

            // Render Progress
            this.renderProgress(data);
        },

        renderProgress: function (data) {
            const s = this.config.selectors;
            let steps = data.steps || [];
            let completedSteps = steps.filter(step => step.admin_status === 'approved').length;
            let totalSteps = steps.length;
            let progressPercentage = totalSteps > 0 ? Math.round((completedSteps / totalSteps) * 100) : 0;

            $(s.progressPercentage).text(progressPercentage + '%');
            $(s.progressBar).css('width', progressPercentage + '%');

            if (totalSteps > 0) {
                let stepsHtml = '';
                steps.forEach((step, index) => {
                    let statusClass = step.admin_status === 'approved' ? 'completed' : step.admin_status === 'under_review' ? 'in-progress' : 'pending';
                    let icon = step.admin_status === 'approved' ? '✓' : step.admin_status === 'under_review' ? '⏳' : (index + 1);

                    stepsHtml += `
                        <div class="step-item ${statusClass}">
                            <div class="step-icon">${icon}</div>
                            <div class="step-content">
                                <h4 class="step-title">${step.step_name || 'Step ' + (index + 1)}</h4>
                                <p class="step-description">${step.description || ''}</p>
                                ${step.vendor_comment ? `<div class="step-meta"><span>💬 ${step.vendor_comment}</span></div>` : ''}
                            </div>
                        </div>
                    `;
                });

                // Add View Reviews Button if pending reviews exist
                let pendingReviews = steps.filter(step => step.admin_status === 'under_review').length;
                if (pendingReviews > 0) {
                    stepsHtml += `
                        <button id="viewProjectReviewsBtn" 
                                data-project-id="${data.project_id}" 
                                class="btn btn-primary"
                                style="width: 100%; margin-top: 15px;">
                            📝 View Pending Reviews (${pendingReviews})
                        </button>
                    `;
                }

                $(s.progressSteps).html(stepsHtml);
            } else {
                $(s.progressSteps).html('<p style="color: var(--text-secondary);">No progress steps configured</p>');
            }
        },

        navigateToReviews: function (projectId) {
            // Trigger navigation to reviews tab
            // This assumes DashboardUtils or generic tab logic is present
            if (typeof DashboardUtils !== 'undefined') {
                // If using DashboardUtils, we might need a way to trigger a tab switch programmatically if not simple click
                // But usually we can just click the nav item
                $('.nav-item[data-section="project-reviews"]').click();
            } else {
                // Fallback
                $('.nav-btn[data-tab="project-reviews"]').click();
            }

            // Wait for tab to load then expand project
            setTimeout(() => {
                const projectCard = $(`.project-review-card[data-project-id="${projectId}"]`);
                if (projectCard.length) {
                    $('html, body').animate({
                        scrollTop: projectCard.offset().top - 100
                    }, 500);

                    // If toggle function exists globally
                    if (typeof window.toggleProjectReviews === 'function') {
                        // Check if not already expanded
                        if (projectCard.find('.review-steps').is(':hidden')) {
                            window.toggleProjectReviews(projectId);
                        }
                    }
                }
            }, 700);
        }
    };

})(jQuery);
