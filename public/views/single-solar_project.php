<?php
/**
 * The template for displaying single Solar Project posts
 */

get_header(); ?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">

    <?php
    while ( have_posts() ) :
        the_post();
        $project_id = get_the_ID();
        $current_user = wp_get_current_user();
        $is_vendor = in_array('solar_vendor', (array)$current_user->roles);

        // --- Get Project Data ---
        $project_state = get_post_meta($project_id, '_project_state', true) ?: 'N/A';
        $project_city = get_post_meta($project_id, '_project_city', true) ?: 'N/A';
        $system_size = get_post_meta($project_id, '_solar_system_size_kw', true) ?: 'N/A';
        $total_cost = get_post_meta($project_id, '_total_project_cost', true) ?: 'N/A';
        $start_date = get_post_meta($project_id, '_project_start_date', true) ?: 'N/A';
        $assignment_method = get_post_meta($project_id, '_vendor_assignment_method', true);

        ?>
        <!-- Back to Marketplace Button -->
        <div class="project-breadcrumb" style="margin-bottom: 30px;">
            <a href="<?php echo home_url('/project-marketplace/'); ?>" class="back-to-marketplace" style="display: inline-flex; align-items: center; gap: 8px; color: #667eea; text-decoration: none; font-weight: 600; padding: 10px 20px; background: #f7fafc; border-radius: 8px; transition: all 0.3s ease;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                Back to Marketplace
            </a>
        </div>
        
        <article id="post-<?php the_ID(); ?>" <?php post_class('solar-project-single'); ?>>
            <header class="entry-header">
                <?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
            </header>

            <div class="entry-content">
                <div class="project-details-grid">
                    <div class="detail-item"><strong>State:</strong> <?php echo esc_html($project_state); ?></div>
                    <div class="detail-item"><strong>City:</strong> <?php echo esc_html($project_city); ?></div>
                    <div class="detail-item"><strong>System Size:</strong> <?php echo esc_html($system_size); ?> kW</div>
                    <?php if ($start_date !== 'N/A'): ?>
                    <div class="detail-item"><strong>Start Date:</strong> <?php echo esc_html(date('F j, Y', strtotime($start_date))); ?></div>
                    <?php endif; ?>
                </div>

                <?php the_content(); ?>

                <hr>

                <!-- Bidding Section -->
                <section id="bidding-section" class="bidding-section">
                    <h2>Bids</h2>

                    <!-- Open Bids List -->
                    <div id="open-bids-list" class="open-bids-list">
                        <h3>Public Bids</h3>
                        <?php
                        global $wpdb;
                        $bids_table = $wpdb->prefix . 'project_bids';
                        $open_bids = $wpdb->get_results($wpdb->prepare(
                            "SELECT b.*, u.display_name FROM {$bids_table} b JOIN {$wpdb->users} u ON b.vendor_id = u.ID WHERE b.project_id = %d AND (b.bid_type = 'open' OR b.bid_type IS NULL) ORDER BY b.created_at DESC",
                            $project_id
                        ));

                        if ($open_bids) {
                            foreach ($open_bids as $bid) {
                                ?>
                                <div class="bid-card">
                                    <div class="bid-amount">₹<?php echo number_format($bid->bid_amount); ?></div>
                                    <div class="bid-vendor">by <?php echo esc_html($bid->display_name); ?></div>
                                    <div class="bid-details"><?php echo esc_html($bid->bid_details); ?></div>
                                    <div class="bid-time"><?php echo human_time_diff(strtotime($bid->created_at), current_time('timestamp')) . ' ago'; ?></div>
                                </div>
                                <?php
                            }
                        } else {
                            echo '<p>No public bids have been placed yet.</p>';
                        }
                        ?>
                    </div>

                    <!-- Place Bid Form -->
                <div class="place-bid-form-wrapper">
                    <h3>Place Your Bid</h3>
                    <?php if (is_user_logged_in()): ?>
                        <?php if ($is_vendor): ?>
                            <form id="place-bid-form" class="place-bid-form">
                                <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                                <?php wp_nonce_field('submit_bid_nonce_' . $project_id, 'submit_bid_nonce'); ?>

                                <div class="form-group">
                                    <label for="bid_amount">Your Bid Amount (₹)</label>
                                    <input type="number" id="bid_amount" name="bid_amount" required>
                                </div>

                                <div class="form-group">
                                    <label for="bid_details">Details (Optional)</label>
                                    <textarea id="bid_details" name="bid_details" rows="3"></textarea>
                                </div>

                                <div class="form-group">
                                    <label>Bid Type</label>
                                    <div class="radio-group">
                                        <label><input type="radio" name="bid_type" value="open" checked> Open Bid (Visible to everyone)</label>
                                        <label><input type="radio" name="bid_type" value="hidden"> Hidden Bid (Visible to managers only)</label>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary">Submit Bid</button>
                                <div id="bid-form-feedback" style="display:none; margin-top:15px;"></div>
                            </form>
                        <?php else: ?>
                            <div class="vendor-registration-notice" style="text-align: center; padding: 40px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; color: white;">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin: 0 auto 20px;">
                                    <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                                    <path d="M2 17l10 5 10-5M2 12l10 5 10-5"></path>
                                </svg>
                                <h4 style="margin: 0 0 15px; font-size: 20px;">Become a Vendor to Bid</h4>
                                <p style="margin: 0 0 25px; opacity: 0.9;">Register as a solar vendor to submit bids on projects. It's quick and easy!</p>
                                <a href="<?php echo home_url('/vendor-registration/'); ?>" class="btn-vendor-register" style="display: inline-block; background: white; color: #667eea; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 16px; transition: transform 0.2s;">
                                    Register as Vendor →
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="login-notice" style="text-align: center; padding: 40px 20px; background: #f7fafc; border: 2px dashed #cbd5e0; border-radius: 12px;">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#667eea" stroke-width="2" style="margin: 0 auto 20px;">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            <h4 style="margin: 0 0 15px; color: #2d3748; font-size: 20px;">Login or Register to Bid</h4>
                            <p style="margin: 0 0 25px; color: #4a5568;">You need to be logged in as a vendor to place bids on this project.</p>
                            <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
                                <a href="<?php echo wp_login_url(get_permalink()); ?>" class="btn" style="display: inline-block; background: #667eea; color: white; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: 600;">
                                    Login
                                </a>
                                <a href="<?php echo home_url('/vendor-registration/'); ?>" class="btn" style="display: inline-block; background: white; color: #667eea; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: 600; border: 2px solid #667eea;">
                                    Register as Vendor
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                </section>

                <!-- Related Projects Section -->
                <?php
                // Query for related projects from same location
                $related_args = [
                    'post_type' => 'solar_project',
                    'post_status' => 'publish',
                    'posts_per_page' => 3,
                    'post__not_in' => [$project_id],
                    'meta_query' => [
                        'relation' => 'AND',
                        [
                            'key' => '_vendor_assignment_method',
                            'value' => 'bidding',
                            'compare' => '='
                        ],
                        [
                            'key' => '_project_city',
                            'value' => $project_city,
                            'compare' => '='
                        ]
                    ]
                ];
                
                $related_query = new WP_Query($related_args);
                
                if ($related_query->have_posts()): ?>
                <section class="related-projects-section" style="margin-top: 60px; padding-top: 40px; border-top: 2px solid #e2e8f0;">
                    <div class="related-header" style="margin-bottom: 30px;">
                        <h2 style="font-size: 28px; font-weight: 700; color: #1a202c; margin: 0 0 10px 0;">
                            More Projects in <?php echo esc_html($project_city); ?>
                        </h2>
                        <p style="color: #718096; margin: 0;">Explore other solar projects available for bidding in this location</p>
                    </div>
                    
                    <div class="related-projects-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px; margin-bottom: 30px;">
                        <?php while ($related_query->have_posts()): $related_query->the_post();
                            $rel_id = get_the_ID();
                            $rel_state = get_post_meta($rel_id, '_project_state', true);
                            $rel_city = get_post_meta($rel_id, '_project_city', true);
                            $rel_size = get_post_meta($rel_id, '_solar_system_size_kw', true);
                            $rel_location = trim($rel_city . ', ' . $rel_state, ', ');
                            
                            $rel_thumbnail = get_the_post_thumbnail_url($rel_id, 'medium');
                            if (!$rel_thumbnail) {
                                $default_image = get_option('ksc_default_project_image', '');
                                $rel_thumbnail = $default_image ?: 'https://via.placeholder.com/400x250/667eea/ffffff?text=Solar+Project';
                            }
                        ?>
                        <div class="project-card">
                            <div class="project-card-image">
                                <img src="<?php echo esc_url($rel_thumbnail); ?>" alt="<?php echo esc_attr(get_the_title()); ?>">
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
                                <h3 class="project-card-title"><?php echo esc_html(get_the_title()); ?></h3>
                                
                                <div class="project-card-details">
                                    <div class="project-detail-item">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                            <circle cx="12" cy="10" r="3"></circle>
                                        </svg>
                                        <span><?php echo esc_html($rel_location ?: 'Location not specified'); ?></span>
                                    </div>
                                    
                                    <?php if ($rel_size): ?>
                                    <div class="project-detail-item">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                            <line x1="9" y1="9" x2="15" y2="15"></line>
                                            <line x1="15" y1="9" x2="9" y2="15"></line>
                                        </svg>
                                        <span><?php echo esc_html($rel_size); ?> kW System</span>
                                    </div>
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
                        <?php endwhile; wp_reset_postdata(); ?>
                    </div>
                    
                    <!-- See More Button -->
                    <div style="text-align: center;">
                        <a href="<?php echo home_url('/project-marketplace/?filter_city=' . urlencode($project_city) . '&filter_state=' . urlencode($project_state)); ?>" 
                           class="see-more-btn" 
                           style="display: inline-flex; align-items: center; gap: 10px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 14px 32px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 16px; transition: all 0.3s ease;">
                            See All Projects in <?php echo esc_html($project_city); ?>
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </a>
                    </div>
                </section>
                <?php endif; ?>

            </div><!-- .entry-content -->
        </article><!-- #post-<?php the_ID(); ?> -->
    <?php
    endwhile; // End of the loop.
    ?>

    </main><!-- #main -->
</div><!-- #primary -->

<style>
/* Project Detail Page Styles */
.solar-project-single .entry-content {
    max-width: 1200px;
    margin: 0 auto;
}
.project-details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    padding: 25px;
    border-radius: 12px;
    margin-bottom: 2em;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
}
.detail-item {
    font-size: 16px;
    padding: 10px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

/* Back Button Hover Effect */
.back-to-marketplace:hover {
    background: #667eea !important;
    color: white !important;
    transform: translateX(-4px);
}
.back-to-marketplace svg {
    transition: transform 0.3s ease;
}
.back-to-marketplace:hover svg {
    transform: translateX(-4px);
}

/* Bidding Section */
.bidding-section {
    margin-top: 2em;
    padding-top: 2em;
    border-top: 1px solid #eee;
}
.open-bids-list {
    margin-bottom: 2em;
}
.bid-card {
    background: #fff;
    border: 1px solid #e5e5e5;
    border-left: 4px solid #667eea;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 15px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
}
.bid-card:hover {
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
    transform: translateY(-2px);
}
.bid-amount {
    font-size: 20px;
    font-weight: 700;
    color: #333;
}
.bid-vendor {
    font-size: 14px;
    color: #777;
    margin-bottom: 10px;
}
.bid-details {
    font-size: 15px;
    color: #555;
    margin-bottom: 10px;
}
.bid-time {
    font-size: 12px;
    color: #999;
    text-align: right;
}
.place-bid-form-wrapper {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
}
.place-bid-form .form-group {
    margin-bottom: 1.5em;
}
.place-bid-form label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5em;
}
.place-bid-form input[type="number"],
.place-bid-form textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    transition: all 0.3s ease;
}
.place-bid-form input[type="number"]:focus,
.place-bid-form textarea:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    outline: none;
}
.place-bid-form .radio-group label {
    font-weight: normal;
    display: block;
    margin-bottom: 0.5em;
}
#bid-form-feedback {
    padding: 15px;
    border-radius: 8px;
}
#bid-form-feedback.success {
    background-color: #d4edda;
    color: #155724;
}
#bid-form-feedback.error {
    background-color: #f8d7da;
    color: #721c24;
}

/* Beautiful Project Cards for Related Projects */
.project-card {
    background: #ffffff;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.08);
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    position: relative;
}
.project-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.4s ease;
}
.project-card:hover::before {
    transform: scaleX(1);
}
.project-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 16px 32px rgba(102, 126, 234, 0.2);
}
.project-card-image {
    position: relative;
    height: 220px;
    overflow: hidden;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
}
.project-card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.project-card:hover .project-card-image img {
    transform: scale(1.1) rotate(2deg);
}
.project-card-badge {
    position: absolute;
    top: 16px;
    right: 16px;
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(10px);
    color: #667eea;
    padding: 8px 16px;
    border-radius: 24px;
    font-size: 13px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}
.project-card-badge svg {
    animation: spin 4s linear infinite;
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.project-card-content {
    padding: 24px;
}
.project-card-title {
    font-size: 20px;
    font-weight: 700;
    color: #1a202c;
    margin: 0 0 18px 0;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.project-card-details {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 24px;
}
.project-detail-item {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #4a5568;
    font-size: 14px;
    padding: 8px 12px;
    background: #f7fafc;
    border-radius: 8px;
    transition: all 0.3s ease;
}
.project-detail-item:hover {
    background: #edf2f7;
    transform: translateX(4px);
}
.project-detail-item svg {
    color: #667eea;
    flex-shrink: 0;
}
.project-card-btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #ffffff;
    padding: 14px 28px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 700;
    font-size: 15px;
    transition: all 0.3s ease;
    width: 100%;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}
.project-card-btn:hover {
    background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    color: #ffffff;
}
.project-card-btn svg {
    transition: transform 0.3s ease;
}
.project-card-btn:hover svg {
    transform: translateX(6px);
}

/* Related Projects Grid */
.related-projects-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 28px;
    margin-bottom: 40px;
}

/* See More Button Hover Effect */
.see-more-btn:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
}
.see-more-btn svg {
    transition: transform 0.3s ease;
}
.see-more-btn:hover svg {
    transform: translateX(6px);
}

/* Vendor Registration Notice Hover */
.btn-vendor-register:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
}

@media (max-width: 768px) {
    .related-projects-grid {
        grid-template-columns: 1fr;
    }
    .project-details-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
get_sidebar();
get_footer();
