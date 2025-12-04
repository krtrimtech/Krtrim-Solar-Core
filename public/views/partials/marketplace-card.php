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
$location = trim($project_city . ', ' . $project_state, ', ');

// Get project thumbnail
$thumbnail = get_the_post_thumbnail_url($project_id, 'medium');
if (!$thumbnail) {
    $default_image = get_option('ksc_default_project_image', '');
    $thumbnail = $default_image ?: 'https://via.placeholder.com/400x250/667eea/ffffff?text=Solar+Project';
}
?>

<div class="project-card">
    <div class="project-card-image">
        <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr($project_title); ?>">
        <div class="project-card-badge">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="5"></circle>
                <line x1="12" y1="1" x2="12" y2="3"></line>
                <line x1="12" y1="21" x2="12" y2="23"></line>
                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                <line x1="1" y1="12" x2="3" y2="12"></line>
                <line x1="21" y1="12" x2="23" y2="12"></line>
                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
            </svg>
            Open for Bids
        </div>
    </div>
    
    <div class="project-card-content">
        <h3 class="project-card-title"><?php echo esc_html($project_title); ?></h3>
        
        <div class="project-card-details">
            <div class="project-detail-item">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                </svg>
                <span><?php echo esc_html($location ?: 'Location not specified'); ?></span>
            </div>
            
            <?php if ($system_size): ?>
            <div class="project-detail-item">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="5"></circle>
                    <line x1="12" y1="1" x2="12" y2="3"></line>
                    <line x1="12" y1="21" x2="12" y2="23"></line>
                    <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                    <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                    <line x1="1" y1="12" x2="3" y2="12"></line>
                    <line x1="21" y1="12" x2="23" y2="12"></line>
                    <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                    <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                </svg>
                <span><?php echo esc_html($system_size); ?> kW System</span>
            </div>
            <?php endif; ?>
            
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
        
        <a href="<?php echo esc_url(get_permalink()); ?>" class="project-card-btn">
            View Details & Bid
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="5" y1="12" x2="19" y2="12"></line>
                <polyline points="12 5 19 12 12 19"></polyline>
            </svg>
        </a>
    </div>
</div>