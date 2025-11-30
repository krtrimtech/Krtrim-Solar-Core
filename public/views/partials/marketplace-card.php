<?php
/**
 * Marketplace Project Card Template
 * Displays a single project card in the marketplace
 */

// Get coverage data if available (set by API)
$has_coverage = get_query_var('has_coverage', false);
$is_vendor = get_query_var('is_vendor', false);

// Get project data
$project_id = get_the_ID();
$project_title = get_the_title();
$project_state = get_post_meta($project_id, '_project_state', true);
$project_city = get_post_meta($project_id, '_project_city', true);
$system_size = get_post_meta($project_id, '_solar_system_size_kw', true);
$project_status = get_post_meta($project_id, 'project_status', true);
?>

<div class="project-card">
    <div class="project-card-content">
        <h3><?php echo esc_html($project_title); ?></h3>
        
        <div class="project-info">
            <strong>📍 Location:</strong> <?php echo esc_html($project_city . ', ' . $project_state); ?>
            
            <?php if ($is_vendor && $has_coverage !== false): ?>
                <?php if ($has_coverage): ?>
                    <span class="coverage-badge in-coverage">
                        <span class="icon">✓</span> In Your Coverage
                    </span>
                <?php else: ?>
                    <span class="coverage-badge outside-coverage">
                        <span class="icon">⚠</span> Outside Coverage
                    </span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <?php if ($system_size): ?>
            <div class="project-info">
                <strong>⚡ System Size:</strong> <?php echo esc_html($system_size); ?> kW
            </div>
        <?php endif; ?>
        
        <?php if ($project_status): ?>
            <div class="project-info">
                <strong>Status:</strong> <span class="status-badge"><?php echo esc_html(ucfirst(str_replace('_', ' ', $project_status))); ?></span>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="project-card-footer">
        <div class="card-buttons">
            <a href="<?php echo get_permalink(); ?>" class="btn-view-card">View Details</a>
        </div>
    </div>
</div>
