<?php
/**
 * Cleaner Dashboard View
 * 
 * Dashboard for solar cleaners to view assigned visits and mark them complete.
 * 
 * @package Krtrim_Solar_Core
 * @since 1.1.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode callback for cleaner dashboard
 */
function ksc_cleaner_dashboard_shortcode() {
    ob_start();
    ksc_render_cleaner_dashboard_content();
    return ob_get_clean();
}

/**
 * Render cleaner dashboard content
 */
function ksc_render_cleaner_dashboard_content() {

// Verify user is logged in and has cleaner role
if (!is_user_logged_in()) {
    echo '<div class="cleaner-login-prompt"><p>Please <a href="' . wp_login_url(get_permalink()) . '">login</a> to access your dashboard.</p></div>';
    return;
}

$current_user = wp_get_current_user();
if (!in_array('solar_cleaner', (array) $current_user->roles) && !in_array('administrator', (array) $current_user->roles)) {
    echo '<div class="access-denied"><p>Access denied. This dashboard is for Solar Cleaners only.</p></div>';
    return;
}

$cleaner_name = $current_user->display_name;
$cleaner_phone = get_user_meta($current_user->ID, 'phone', true);
?>

<div class="cleaner-dashboard">
    <!-- Header -->
    <div class="cleaner-header">
        <div class="cleaner-info">
            <h1>🧹 Cleaner Dashboard</h1>
            <p>Welcome, <strong><?php echo esc_html($cleaner_name); ?></strong></p>
        </div>
        <div class="cleaner-actions">
            <a href="<?php echo wp_logout_url(home_url()); ?>" class="btn btn-outline">Logout</a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="cleaner-stats" id="cleaner-stats">
        <div class="stat-card">
            <div class="stat-icon">📅</div>
            <div class="stat-info">
                <span class="stat-value" id="stat-scheduled">-</span>
                <span class="stat-label">Scheduled</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-info">
                <span class="stat-value" id="stat-completed">-</span>
                <span class="stat-label">Completed</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📆</div>
            <div class="stat-info">
                <span class="stat-value" id="stat-today">-</span>
                <span class="stat-label">Today</span>
            </div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="filter-tabs">
        <button class="filter-tab active" data-filter="scheduled">📅 Upcoming</button>
        <button class="filter-tab" data-filter="today">📆 Today</button>
        <button class="filter-tab" data-filter="completed">✅ Completed</button>
        <button class="filter-tab" data-filter="">All</button>
    </div>

    <!-- Visits List -->
    <div class="visits-container" id="visits-container">
        <p>Loading visits...</p>
    </div>

    <!-- Complete Visit Modal -->
    <div id="complete-visit-modal" class="cleaner-modal" style="display:none;">
        <div class="cleaner-modal-content">
            <span class="close-modal">&times;</span>
            <h3>✅ Mark Visit Complete</h3>
            <form id="complete-visit-form" enctype="multipart/form-data">
                <input type="hidden" id="complete_visit_id" name="visit_id">
                <?php wp_nonce_field('ksc_cleaner_complete_nonce', 'cleaner_complete_nonce'); ?>
                
                <div class="form-group">
                    <label for="completion_photo">📷 Upload Completion Photo *</label>
                    <input type="file" id="completion_photo" name="completion_photo" accept="image/*" required>
                    <small>Take a photo of the cleaned solar panels</small>
                </div>
                
                <div class="form-group">
                    <label for="completion_notes">Notes (optional)</label>
                    <textarea id="completion_notes" name="completion_notes" rows="3" placeholder="Any observations about the cleaning..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width:100%;">✅ Mark Complete</button>
            </form>
            <div id="complete-visit-feedback" style="margin-top:15px;"></div>
        </div>
    </div>
</div>

<style>
.cleaner-dashboard {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.cleaner-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e5e7eb;
}

.cleaner-header h1 {
    margin: 0;
    font-size: 24px;
}

.cleaner-header p {
    margin: 5px 0 0;
    color: #6b7280;
}

.cleaner-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.stat-icon {
    font-size: 32px;
}

.stat-value {
    display: block;
    font-size: 28px;
    font-weight: 700;
    color: #1f2937;
}

.stat-label {
    font-size: 13px;
    color: #6b7280;
}

.filter-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 10px 20px;
    border: 2px solid #e5e7eb;
    background: white;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s;
}

.filter-tab:hover {
    border-color: #4f46e5;
}

.filter-tab.active {
    background: #4f46e5;
    color: white;
    border-color: #4f46e5;
}

.visit-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border-left: 4px solid #4f46e5;
}

.visit-card.completed {
    border-left-color: #10b981;
    opacity: 0.8;
}

.visit-card.today {
    border-left-color: #f59e0b;
    background: linear-gradient(135deg, #fffbeb 0%, white 100%);
}

.visit-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.visit-customer {
    font-size: 18px;
    font-weight: 600;
    margin: 0;
}

.visit-status {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.visit-status.scheduled {
    background: #dbeafe;
    color: #1d4ed8;
}

.visit-status.completed {
    background: #d1fae5;
    color: #047857;
}

.visit-details {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-bottom: 15px;
}

.visit-detail {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #4b5563;
}

.visit-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.visit-actions .btn {
    padding: 8px 16px;
    font-size: 13px;
    border-radius: 6px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-primary {
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    color: white;
    border: none;
    cursor: pointer;
}

.btn-outline {
    background: white;
    border: 2px solid #e5e7eb;
    color: #374151;
}

.btn-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border: none;
    cursor: pointer;
}

.cleaner-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.cleaner-modal-content {
    background: white;
    padding: 30px;
    border-radius: 16px;
    max-width: 450px;
    width: 90%;
    position: relative;
}

.close-modal {
    position: absolute;
    top: 15px;
    right: 20px;
    font-size: 24px;
    cursor: pointer;
    color: #6b7280;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #374151;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
}

.form-group small {
    display: block;
    margin-top: 5px;
    color: #9ca3af;
    font-size: 12px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6b7280;
}

.empty-state .icon {
    font-size: 48px;
    margin-bottom: 15px;
}

.team-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border-left: 4px solid #7c3aed;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.team-info h3 { margin: 0 0 5px 0; font-size: 18px; }
.team-role { 
    background: #f3e8ff; color: #7c3aed; 
    padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 500; 
}
.team-contact { font-size: 14px; color: #6b7280; margin-top: 5px; }

@media (max-width: 600px) {
    .cleaner-stats {
        grid-template-columns: 1fr;
    }
    
    .visit-details {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
    let currentFilter = 'scheduled';

    // Load visits
    function loadVisits(filter = '') {
        $('#visits-container').html('<p>Loading visits...</p>');
        $('#cleaner-stats').show(); // Show stats for visits
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_cleaning_visits',
                status: filter === 'today' ? 'scheduled' : filter
            },
            success: function(response) {
                if (response.success) {
                    renderVisits(response.data, filter);
                    updateStats(response.data);
                } else {
                    $('#visits-container').html('<p style="color:red;">Error loading visits</p>');
                }
            }
        });
    }

    // Load Team
    function loadTeam() {
        $('#visits-container').html('<p>Loading team...</p>');
        $('#cleaner-stats').hide(); // Hide stats for team view
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { action: 'get_cleaner_superiors' },
            success: function(response) {
                if (response.success) {
                    renderTeam(response.data);
                } else {
                    $('#visits-container').html('<p style="color:red;">Error loading team</p>');
                }
            }
        });
    }

    // Render Team
    function renderTeam(members) {
        if (!members || members.length === 0) {
            $('#visits-container').html('<div class="empty-state"><h3>No team members found</h3></div>');
            return;
        }

        let html = '';
        members.forEach(m => {
            html += `
                <div class="team-card">
                    <div class="team-info">
                        <h3>${m.name} <span class="team-role">${m.role}</span></h3>
                        <div class="team-contact">📞 ${m.phone}</div>
                        ${m.email ? `<div class="team-contact">✉️ ${m.email}</div>` : ''}
                    </div>
                    <div class="team-actions">
                        <a href="tel:${m.phone}" class="btn btn-outline">📞 Call</a>
                        <a href="https://wa.me/91${m.phone.replace(/\D/g,'').slice(-10)}" target="_blank" class="btn btn-success">💬 WhatsApp</a>
                    </div>
                </div>
            `;
        });
        $('#visits-container').html(html);
    }

    // Render visits
    function renderVisits(visits, filter) {
        if (!visits || visits.length === 0) {
            $('#visits-container').html(`
                <div class="empty-state">
                    <div class="icon">📋</div>
                    <h3>No visits found</h3>
                    <p>You don't have any ${filter || 'assigned'} visits yet.</p>
                </div>
            `);
            return;
        }

        const today = new Date().toISOString().split('T')[0];
        let filtered = visits;

        if (filter === 'today') {
            filtered = visits.filter(v => v.scheduled_date === today);
        }

        if (filtered.length === 0) {
            $('#visits-container').html(`
                <div class="empty-state">
                    <div class="icon">📋</div>
                    <h3>No visits for today</h3>
                    <p>Enjoy your day off! 🌞</p>
                </div>
            `);
            return;
        }

        let html = '';
        filtered.forEach(v => {
            const isToday = v.scheduled_date === today;
            const isCompleted = v.status === 'completed';
            const cardClass = isCompleted ? 'completed' : (isToday ? 'today' : '');
            
            html += `
                <div class="visit-card ${cardClass}">
                    <div class="visit-header">
                        <h3 class="visit-customer">${v.customer_name}</h3>
                        <span class="visit-status ${v.status}">${v.status === 'completed' ? '✅ Completed' : '📅 Scheduled'}</span>
                    </div>
                    <div class="visit-details">
                        <div class="visit-detail">📅 ${formatDate(v.scheduled_date)}</div>
                        <div class="visit-detail">⏰ ${v.scheduled_time || '09:00'}</div>
                        <div class="visit-detail">📞 ${v.customer_phone}</div>
                        <div class="visit-detail">📍 ${v.customer_address || 'N/A'}</div>
                    </div>
                    <div class="visit-actions">
                        <a href="tel:${v.customer_phone}" class="btn btn-outline">📞 Call</a>
                        <a href="https://maps.google.com/?q=${encodeURIComponent(v.customer_address || '')}" target="_blank" class="btn btn-outline">🗺️ Navigate</a>
                        ${!isCompleted ? `<button class="btn btn-success complete-visit-btn" data-id="${v.id}">✅ Mark Complete</button>` : ''}
                    </div>
                </div>
            `;
        });

        $('#visits-container').html(html);
    }

    // Update stats
    function updateStats(visits) {
        const today = new Date().toISOString().split('T')[0];
        const scheduled = visits.filter(v => v.status === 'scheduled').length;
        const completed = visits.filter(v => v.status === 'completed').length;
        const todayVisits = visits.filter(v => v.scheduled_date === today && v.status === 'scheduled').length;

        $('#stat-scheduled').text(scheduled);
        $('#stat-completed').text(completed);
        $('#stat-today').text(todayVisits);
    }

    // Format date
    function formatDate(dateStr) {
        if (!dateStr) return 'N/A';
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    // Filter tabs
    $('.filter-tab').on('click', function() {
        $('.filter-tab').removeClass('active');
        $(this).addClass('active');
        currentFilter = $(this).data('filter');
        
        if (currentFilter === 'team') {
            loadTeam();
        } else {
            loadVisits(currentFilter);
        }
    });

    // Open complete modal
    $(document).on('click', '.complete-visit-btn', function() {
        const visitId = $(this).data('id');
        $('#complete_visit_id').val(visitId);
        $('#complete-visit-modal').show();
    });

    // Close modal
    $(document).on('click', '.close-modal', function() {
        $(this).closest('.cleaner-modal').hide();
    });

    // Complete visit form
    $('#complete-visit-form').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const formData = new FormData(this);
        formData.append('action', 'complete_cleaning_visit');

        const feedback = $('#complete-visit-feedback');
        const submitBtn = form.find('button[type="submit"]');
        
        submitBtn.prop('disabled', true).text('Uploading...');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    feedback.html('<div style="background:#d1fae5;color:#047857;padding:12px;border-radius:8px;">✅ Visit marked complete!</div>');
                    form[0].reset();
                    setTimeout(() => {
                        $('#complete-visit-modal').hide();
                        loadVisits(currentFilter);
                    }, 1500);
                } else {
                    feedback.html('<div style="background:#fee2e2;color:#b91c1c;padding:12px;border-radius:8px;">❌ ' + response.data.message + '</div>');
                }
            },
            error: function() {
                feedback.html('<div style="background:#fee2e2;color:#b91c1c;padding:12px;border-radius:8px;">❌ Error completing visit</div>');
            },
            complete: function() {
                submitBtn.prop('disabled', false).text('✅ Mark Complete');
            }
        });
    });

    // Initial load
    loadVisits('scheduled');
});
</script>
<?php } // End of ksc_render_cleaner_dashboard_content function
