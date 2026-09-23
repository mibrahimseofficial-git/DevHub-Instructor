<div class="post-meta-info-classic">
    <div class="container">
        <div class="row">
            <div class="col-md-8 col-sm-8 col-xs-12">
                <div class="post-meta-left-box">
                    <?php if (function_exists('listingpro_breadcrumbs')) listingpro_breadcrumbs(); ?>
                    <?php
                    $b_logo =   $listingpro_options['business_logo_switch'];
                    $allow_logo =   isset($listingpro_options['listingpro_allow_logo_styles_switch']) ? $listingpro_options['listingpro_allow_logo_styles_switch'] : '';
                    if ($b_logo == true && $allow_logo == "yes") :
                        $business_logo_url  =   '';
                        $page_style =   $listingpro_options['listing_submit_page_style'];

                        $b_logo_default =   $listingpro_options['business_logo_default']['url'];

                        $business_logo = listing_get_metabox_by_ID('business_logo', get_the_ID());
                        $image_thumb = '';
                        global $wpdb;
                        $attachment = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid='%s';", $business_logo));
                        if (!empty($attachment)) {
                            $image_thumb = wp_get_attachment_image_src($attachment[0], 'thumbnail');
                        }
                        if (empty($business_logo)) {
                            $business_logo_url  =   $b_logo_default;
                        } else {
                            $business_logo_url  =   $business_logo;
                        }
                        if (!empty($business_logo_url)) :
                    ?>
                            <div class="lp-listing-logo">
                                <img src="<?php echo esc_attr($business_logo_url); ?>" alt="Listing Logo">
                            </div>
                    <?php endif;
                    endif; ?>
                    <h1><?php the_title(); ?> <?php echo wp_kses_post($claim); ?> <?php echo ip_availability_badge_html( get_the_ID() ); ?></h1>
                    <?php if (!empty($tagline_text)) {
                        if ($tagline_show == "true") { ?>
                            <p><?php echo esc_attr($tagline_text); ?></p>
                        <?php } ?>
                    <?php } ?>
                    <ul class="classic-detail-reviews">
                        <li class="lp-classic-reviews">
                            <?php
                            $NumberRating = listingpro_ratings_numbers(get_the_ID());
                            if ($NumberRating != 0) {


                                $rating = get_post_meta(get_the_ID(), 'listing_rate', true);
                                echo '<span>' . $rating . '</span>';
                                $rating_num_bg = '';

                                $rating_num_clr = '';



                                if ($rating < 3) {
                                    $rating_num_bg = 'num-level1';
                                    $rating_num_clr = 'level1';
                                }
                                if ($rating < 4) {
                                    $rating_num_bg = 'num-level2';
                                    $rating_num_clr = 'level2';
                                }
                                if ($rating < 5) {
                                    $rating_num_bg = 'num-level3';
                                    $rating_num_clr = 'level3';
                                }
                                if ($rating >= 5) {
                                    $rating_num_bg = 'num-level4';
                                    $rating_num_clr = 'level4';
                                }

                            ?>



                                <div class="lp-rating-stars-outers lp-listing-stars">

                                    <span class="lp-star-box <?php if ($rating >= 1) {
                                                                    echo 'filled' . ' ' . $rating_num_clr;
                                                                } ?>"><i class="fa fa-star" aria-hidden="true"></i></span>



                                    <span class="lp-star-box <?php if ($rating >= 2) {
                                                                    echo 'filled' . ' ' . $rating_num_clr;
                                                                } ?>"><i class="fa fa-star" aria-hidden="true"></i></span>



                                    <span class="lp-star-box <?php if ($rating >= 3) {
                                                                    echo 'filled' . ' ' . $rating_num_clr;
                                                                } ?>"><i class="fa fa-star" aria-hidden="true"></i></span>



                                    <span class="lp-star-box <?php if ($rating >= 4) {
                                                                    echo 'filled' . ' ' . $rating_num_clr;
                                                                } ?>"><i class="fa fa-star" aria-hidden="true"></i></span>



                                    <span class="lp-star-box <?php if ($rating >= 5) {
                                                                    echo 'filled' . ' ' . $rating_num_clr;
                                                                } ?>"><i class="fa fa-star" aria-hidden="true"></i></span>

                                </div>
                                <span>
                                    (<?php echo esc_attr($NumberRating); ?>)
                                </span>
                            <?php
                            }
                            ?>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="col-md-4 col-sm-4 col-xs-12">
                <div class="post-meta-right-box text-right clearfix margin-top-20">
                    <div class="rating-section-container">
                        <span class="rating-section">
                            <?php
                            $NumberRating = listingpro_ratings_numbers($post->ID);
                            if ($NumberRating != 0) {
                                echo lp_cal_listing_rate(get_the_ID());
                            ?>
                                <span>
                                    <small><?php echo esc_attr($NumberRating); ?></small>
                                    <?php echo esc_html__('Ratings', 'listingpro'); ?>
                                </span>
                            <?php
                            } else {
                                echo lp_cal_listing_rate(get_the_ID());
                            }
                            ?>
                        </span>
                        <?php if (!empty($resurva_url)) { ?>
                            <a href="" class="secondary-btn make-reservation">
                                <i class="fa fa-calendar-check-o"></i>
                                <?php echo esc_html__('Book Now', 'listingpro'); ?>
                            </a>
                            <div class="ifram-reservation">
                                <div class="inner-reservations">
                                    <a href="#" class="close-btn"><i class="fa fa-times"></i></a>
                                    <iframe src="<?php echo esc_url($resurva_url); ?>" name="resurva-frame" frameborder="0"></iframe>
                                </div>
                            </div>
                            <?php } else {
                            if (class_exists('ListingReviews')) {
                                $allowedReviews = $listingpro_options['lp_review_switch'];
                                if (!empty($allowedReviews) && $allowedReviews == "1") {
                                    if (get_post_status($post->ID) == "publish") {

                            ?>
                                        <a href="#reply-title" class="secondary-btn" id="clicktoreview">
                                            <i class="fa fa-star"></i>
                                            <?php echo esc_html__('Submit Review', 'listingpro'); ?>
                                        </a>
                        <?php
                                    }
                                }
                            }
                        }
                        ?>
                    </div>
                    <ul class="post-stat">
                        <?php
                        $favrt  =   listingpro_is_favourite_v2(get_the_ID());

                        ?>
                        <li id="fav-container">
                            <a href="" class="<?php if ($favrt == 'yes') {
                                                    echo 'remove-fav-v2';
                                                } else {
                                                    echo 'add-to-fav-v2';
                                                } ?>" data-post-id="<?php echo get_the_ID(); ?>" data-post-type="detail">

                                <i class="fa <?php if ($favrt == 'yes') {
                                                    echo 'fa-bookmark';
                                                } else {
                                                    echo 'fa-bookmark-o';
                                                } ?>" aria-hidden="true"></i>

                                <?php if ($favrt == 'yes') {
                                    echo esc_html__('Saved', 'listingpro');
                                } else {
                                    echo esc_html__('Save', 'listingpro');
                                } ?>

                            </a>
                        </li>
                        <li class="reviews sbutton">
                            <?php listingpro_sharing(); ?>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>