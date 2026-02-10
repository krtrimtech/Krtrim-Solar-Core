// Project & Lead Actions (Delete, etc.)
// Modal logic has been moved to assets/js/components/project-modal-component.js

(function ($) {
    'use strict';

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
                        if (typeof DashboardUtils !== 'undefined') {
                            DashboardUtils.showToast('Project deleted successfully', 'success');
                        } else {
                            alert('Project deleted successfully');
                        }
                        if (typeof loadProjects === 'function') loadProjects();
                    } else {
                        if (typeof DashboardUtils !== 'undefined') {
                            DashboardUtils.showToast(response.data.message || 'Failed to delete project', 'error');
                        } else {
                            alert(response.data.message || 'Failed to delete project');
                        }
                    }
                },
                error: function () {
                    if (typeof DashboardUtils !== 'undefined') {
                        DashboardUtils.showToast('Error deleting project', 'error');
                    } else {
                        alert('Error deleting project');
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
                        if (typeof DashboardUtils !== 'undefined') {
                            DashboardUtils.showToast('Lead deleted successfully', 'success');
                        } else {
                            alert('Lead deleted successfully');
                        }
                        if (typeof loadLeads === 'function') loadLeads();
                    } else {
                        if (typeof DashboardUtils !== 'undefined') {
                            DashboardUtils.showToast(response.data.message || 'Failed to delete lead', 'error');
                        } else {
                            alert(response.data.message || 'Failed to delete lead');
                        }
                    }
                },
                error: function () {
                    if (typeof DashboardUtils !== 'undefined') {
                        DashboardUtils.showToast('Error deleting lead', 'error');
                    } else {
                        alert('Error deleting lead');
                    }
                }
            });
        }
    });

})(jQuery);
