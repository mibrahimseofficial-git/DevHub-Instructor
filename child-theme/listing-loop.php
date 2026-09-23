	<?php
	$output = null;

	$favrt  =   listingpro_is_favourite_new(get_the_ID());

	global $listingpro_options;
	$lp_review_switch = $listingpro_options['lp_review_switch'];

	$lp_default_map_pin = $listingpro_options['lp_map_pin']['url'];
	if (empty($lp_default_map_pin)) {
		$lp_default_map_pin = get_template_directory() . '/assets/images/pins/pin.png';
	}

	if (!isset($postGridCount)) {
		$postGridCount = '0';
	}
	global $postGridCount;
	$postGridCount++;

	$listing_style = '';
	$listing_style = $listingpro_options['listing_style'];
	if (isset($_GET['list-style']) && !empty($_GET['list-style'])) {
		$listing_style = esc_html($_GET['list-style']);
	}
	if (is_front_page()) {
		$listing_style = 'col-md-4 col-sm-6';
		$postGridnumber = 3;
	} else {
		if ($listing_style == '1') {
			$listing_style = 'col-md-4 col-sm-6';
			$postGridnumber = 3;
		} elseif ($listing_style == '3' && !is_page()) {
			$listing_style = 'col-md-6 col-sm-12';
			$postGridnumber = 2;
		} elseif ($listing_style == '5') {
			$listing_style = 'col-md-12 col-sm-12';
			$postGridnumber = 2;
		} else {
			$listing_style = 'col-md-6 col-sm-6';
			$postGridnumber = 2;
		}
	}
	if (is_page_template('template-favourites.php')) {
		$listing_style = 'col-md-4 col-sm-6';
		$postGridnumber = 3;
	}
	$gAddress = listing_get_metabox('gAddress');
	lp_get_lat_long_from_address($gAddress, get_the_ID());
	$latitude = listing_get_metabox_by_ID('latitude', get_the_ID());
	$longitude = listing_get_metabox_by_ID('longitude', get_the_ID());
	$gAddress = listing_get_metabox('gAddress');
	$phone = listing_get_metabox('phone');
	if (!empty($latitude)) {
		$latitude = str_replace(",", ".", $latitude);
	}
	if (!empty($longitude)) {
		$longitude = str_replace(",", ".", $longitude);
	}
	$isfavouriteicon = listingpro_is_favourite_grids(get_the_ID(), $onlyicon = true);
	$isfavouritetext = listingpro_is_favourite_grids(get_the_ID(), $onlyicon = false);

	$adStatus = apply_filters('lp_get_ad_status', '', get_the_ID());

	$CHeckAd = '';
	$adClass = '';
	if ($adStatus == 'active') {
		$CHeckAd = '<span class="listing-pro">' . esc_html__('Ad', 'listingpro') . '</span>';
		$adClass = 'promoted';
	}
	$claimed_section = listing_get_metabox('claimed_section');

	$claim = '';
	$__ip_availability_badge = ip_availability_badge_html( get_the_ID() );
	$claimStatus = '';

	$deafaultFeatImg = lp_default_featured_image_listing();
	if ( !has_post_thumbnail() && $listingpro_options['lp_def_featured_image_from_gallery'] == 'enable')  {
        $IDs = get_post_meta(get_the_ID(), 'gallery_image_ids', true);


        $IDs = explode(',', $IDs);
        $imgurl_bgd = '';

        if (is_array($IDs)) {
            shuffle($IDs);

            $img_url = wp_get_attachment_image_src($IDs[0], 'listingpro-blog-grid');


            if(!empty( $img_url )){
                $deafaultFeatImg = $img_url[0];
                $deafaultFeatImg = $img_url[0];
            }

            if (!empty($imgurl)) {
                $deafaultFeatImg = $imgurl;
            } elseif (!empty($deafaultFeatImg)) {
                $deafaultFeatImg = $deafaultFeatImg;
            } else {
                $deafaultFeatImg = 'https://placehold.co/372x240';
            }

        }
    }

	if ($claimed_section == 'claimed') {
		if (is_singular('listing')) {
			$claimStatus = esc_html__('Claimed', 'listingpro');
		}
		$claim = '<span class="verified simptip-position-top simptip-movable" data-tooltip="' . esc_html__('Claimed', 'listingpro') . '"><i class="fa fa-check"></i> ' . $claimStatus . '</span>';
	} elseif ($claimed_section == 'not_claimed') {
		$claim = '';
	}
	$listing_layout = $listingpro_options['listing_views'];
	if (is_author()) {
		$listing_layout =   $listingpro_options['my_listing_views'];
	}
	if (isset($GLOBALS['listing_layout_element']) && !empty($GLOBALS['listing_layout_element']) && $GLOBALS['listing_layout_element'] != '') {
		$listing_layout = $GLOBALS['listing_layout_element'];
	}
	$grid_view_element  =   '';
	if (isset($GLOBALS['grid_view_element'])) {
		$grid_view_element  =   $GLOBALS['grid_view_element'];
	}

	if (!is_front_page() && isset($listingpro_options['listing_views']) && !empty($listingpro_options['listing_views'])) {
		// WeddingPro
		if ($listingpro_options['listing_views'] == "wpro_gridStyle_1") {
			if (class_exists('WeddingPro')) {
				$is_ad = false;
				if ($adStatus == 'active') $is_ad = true;
				echo wpro_listing_grid(get_the_ID(), null, $is_ad, $listing_style, true);
				return;
			}
		}
		// BlackPro
		elseif ($listingpro_options['listing_views'] == "bpro_gridStyle_1") {
			if (class_exists('BlackPro')) {
				$is_ad = false;
				if ($adStatus == 'active') $is_ad = true;
				echo bpro_listing_grid(get_the_ID(), null, $is_ad, $listing_style, true);
				return;
			}
		}
		// PetPro
		elseif ($listingpro_options['listing_views'] == "ppro_gridStyle_1") {
			if (class_exists('PetFinder')) {
				$is_ad = false;
				if ($adStatus == 'active') $is_ad = true;
				echo ppro_listing_grid(get_the_ID(), null, $is_ad, $listing_style, true);
				return;
			}
		}
		// CbdPro
		elseif ($listingpro_options['listing_views'] == "cbdpro_gridStyle_1") {
			if (class_exists('CbdPro')) {
				$is_ad = false;
				if ($adStatus == 'active') $is_ad = true;
				echo cbdpro_listing_grid(get_the_ID(), null, $is_ad, $listing_style, true);
				return;
			}
		}
		// LawyerPro
		elseif ($listingpro_options['listing_views'] == "lpro_gridStyle_1") {
			if (class_exists('LawyerPro')) {
				$is_ad = false;
				if ($adStatus == 'active') $is_ad = true;
				echo lpro_listing_grid(get_the_ID(), null, $is_ad, $listing_style, true);
				return;
			}
		}elseif ($listing_layout == 'grid_view_classic') {
			get_template_part('listing-loop-classic');
			return;
		}

	}

	if (isset($GLOBALS['my_listing_views']) && $GLOBALS['my_listing_views'] != '') {
		$listing_layout =   $GLOBALS['my_listing_views'];
	}
	if ($listing_layout == 'grid_view' && $grid_view_element != 'grid_view4') {
		$listing_stylee = $listingpro_options['listing_style'];
		$featureImg = '';

		if (has_post_thumbnail()) {

			$image = wp_get_attachment_image_src(get_post_thumbnail_id(get_the_ID()), 'listingpro-blog-grid');

			if (!empty($image[0])) {
				$featureImg = $image[0];
			} elseif (!empty($deafaultFeatImg)) {

				$featureImg = $deafaultFeatImg;
			} else {
				$featureImg = 'https://placehold.co/372x240';
			}

			//$featureImg = $image[0];
		} else if ($listingpro_options['lp_def_featured_image_from_gallery'] == 'enable') {

			//  echo "yes";
			$IDs = get_post_meta(get_the_ID(), 'gallery_image_ids', true);

			$IDs = explode(',', $IDs);

			if (is_array($IDs)) {
				shuffle($IDs);

				$img_url = wp_get_attachment_image_src($IDs[0], 'listingpro-blog-grid');

				$imgurl = $img_url[0];
				if (!empty($imgurl)) {
					$featureImg = $imgurl;
				} elseif (!empty($deafaultFeatImg)) {

					$featureImg = $deafaultFeatImg;
				} else {
					$featureImg = 'https://placehold.co/372x240';
				}
			}
		} elseif (!empty($deafaultFeatImg)) {

			$featureImg = $deafaultFeatImg;
		} else {

			$featureImg = 'https://placehold.co/372x240';
		}



	?>
		<div data-feaimg="<?php echo esc_url($featureImg); ?>" class="<?php echo esc_attr($listing_style); ?> <?php echo esc_attr($adClass); ?> lp-grid-box-contianer grid_view_s1 grid_view2 card1 lp-grid-box-contianer1" data-title="<?php echo get_the_title(); ?>" data-postid="<?php echo get_the_ID(); ?>" data-lattitue="<?php echo esc_attr($latitude); ?>" data-longitute="<?php echo esc_attr($longitude); ?>" data-posturl="<?php echo get_the_permalink(); ?>" data-lppinurl="<?php echo esc_url($lp_default_map_pin); ?>">
			<?php if (is_page_template('template-favourites.php')) { ?>
				<div class="remove-fav md-close" data-post-id="<?php echo get_the_ID(); ?>">
					<i class="fa fa-close"></i>
				</div>
			<?php } ?>
			<?php
			if (has_post_thumbnail()) {
				$image = wp_get_attachment_image_src(get_post_thumbnail_id(get_the_ID()), 'listingpro-blog-grid');
				if (!empty($image[0])) {
					echo "<a href='" . get_the_permalink() . "' >
														<img alt='image' src='" . $image[0] . "' />
													</a>";
				} elseif (!empty($deafaultFeatImg)) {
					echo "<a href='" . get_the_permalink() . "' >
														<img alt='image' src='" . $deafaultFeatImg . "' />
													</a>";
				} else {
					echo '
													<a href="' . get_the_permalink() . '" >
														<img src="' . esc_html__('https://placehold.co/372x240', 'listingpro') . '" alt="image">
													</a>';
				}
			} elseif (!empty($deafaultFeatImg)) {
				echo "<a href='" . get_the_permalink() . "' >
													<img alt='image' src='" . $deafaultFeatImg . "' />
												</a>";
			} else {
				echo '
											<a href="' . get_the_permalink() . '" >
												<img src="' . esc_html__('https://placehold.co/372x240', 'listingpro') . '" alt="image">
											</a>';
			}
			?>
						</div>
					</div>
					<div class="lp-grid-box-quick">
						<ul class="lp-post-quick-links">
							<li>
								<a href="#" data-post-type="grids" data-post-id="<?php echo esc_attr(get_the_ID()); ?>" data-success-text="<?php echo esc_html__('Saved', 'listingpro') ?>" class="status-btn <?php if ($favrt == 'yes') {
																																																					echo 'remove-fav';
																																																				} else {
																																																					echo 'add-to-fav';
																																																				} ?> lp-add-to-fav">
									<i class="<?php echo esc_attr($isfavouriteicon); ?>"></i> <span><?php echo wp_kses_post($isfavouritetext); ?></span>
								</a>
							</li>
							<li>
								<a class="icon-quick-eye md-trigger qickpopup" data-mappin="<?php echo esc_url($lp_default_map_pin); ?>" data-modal="modal-126"><i class="fa fa-eye"></i> <?php echo esc_html__('Preview', 'listingpro'); ?></a>
							</li>
						</ul>
					</div>

					<?php
					// Show verified badge when attached price_plan has menu_order == 1
					$__plan_id = listing_get_metabox_by_ID('Plan_id', get_the_ID());
					$__plan_mo = null;
					if (!empty($__plan_id)) {
						$__plan_post = get_post($__plan_id);
						if ($__plan_post) {
							$__plan_mo = intval($__plan_post->menu_order);
						}
					}
					$__show_verified = isset($listingpro_options['lp_verified_badge_pro']) ? $listingpro_options['lp_verified_badge_pro'] : true;
					if ($__show_verified && $__plan_mo === 1) {
						echo '<div class="lp-grid6-verified">\n\t\t\t\t\t<a class="status-btn"><span class="grid-closed status-red li-listing-clock-outer">Verified</span></a>\n\t\t\t\t</div>';
					}

					// Admin-only debug: print attached Plan_id and menu_order as an HTML comment
					if (is_user_logged_in() && current_user_can('manage_options')) {
						echo '<!-- PLAN DEBUG: Plan_id=' . esc_html($__plan_id) . ' menu_order=' . esc_html($__plan_mo) . ' -->';
					}

					?>

				</div>
				<div class="lp-grid-desc-container lp-border clearfix">
					<div class="lp-grid-box-description ">
						<div class="lp-grid-box-left pull-left">
							<h4 class="lp-h4">
								<a href="<?php echo get_the_permalink(); ?>">
									<?php echo wp_kses_post($CHeckAd); ?>
									<?php echo mb_substr(get_the_title(), 0, 40) ?>
									<?php echo wp_kses_post($claim); ?>
									<?php echo $__ip_availability_badge; ?>
								</a>
							</h4>
							<ul>
								<?php
								if ($lp_review_switch == 1) { ?>
									<li>
										<?php
										$NumberRating = listingpro_ratings_numbers($post->ID);
										if ($NumberRating != 0) {
											if ($NumberRating <= 1) {
												$review = esc_html__('Rating', 'listingpro');
											} else {
												$review = esc_html__('Ratings', 'listingpro');
											}
											echo lp_cal_listing_rate(get_the_ID());
										?>
											<span>
												<?php echo esc_attr($NumberRating); ?>
												<?php echo esc_attr($review); ?>
											</span>
										<?php
										} else {
											echo lp_cal_listing_rate(get_the_ID());
										}
										?>
									</li>
								<?php } ?>
								<li class="middle">
									<?php echo listingpro_price_dynesty_text($post->ID); ?>
								</li>
								<li>
									<?php
									$cats = get_the_terms(get_the_ID(), 'listing-category');
									if (!empty($cats)) {
										$categoryOutput = array();
										foreach ($cats as $cat) {
											if (!($cat instanceof WP_Term)) {
												continue;
											}
											$term_link = get_term_link($cat);
											if (!is_wp_error($term_link)) {
												$categoryOutput[] = '<a href="' . esc_url($term_link) . '">' . esc_html($cat->name) . '</a>';
											}
										}
										echo implode(', ', $categoryOutput);
									}
									?>
								</li>
							</ul>
							<?php echo listingpro_last_review_by_list_ID(get_the_ID()); ?>
						</div>
						<div class="lp-grid-box-right pull-right">
						</div>
					</div>
					<?php
					$openStatus = listingpro_check_time(get_the_ID());
					$cats = get_the_terms(get_the_ID(), 'location');
					if(!empty($openStatus) || !empty($cats) || !empty($gAddress)){
					?>
						<div class="lp-grid-box-bottom">
							<div class="pull-left">
								<div class="show">
									<?php
									$countlocs = 1;
									$cats = get_the_terms(get_the_ID(), 'location');
									if (!empty($cats)) {
										echo '<span class="cat-icon">' . listingpro_icons('mapMarkerGrey') . '</span>';
										foreach ($cats as $cat) {
											if ($countlocs == 1) {
												$term_link = get_term_link($cat);
												echo '
																<a href="' . $term_link . '">
																	' . $cat->name . '
																</a>';
											}
											$countlocs++;
										}
									}

									?>
								</div>
								<?php if (!empty($gAddress)) { ?>
									<div class="hide">
										<span class="cat-icon">
											<?php echo listingpro_icons('mapMarkerGrey'); ?>
										</span>
										<span class="text gaddress"><?php echo mb_substr($gAddress, 0, 30); ?>...</span>
									</div>
								<?php } ?>
							</div>
							<?php
							$openStatus = listingpro_check_time(get_the_ID());
							if (!empty($openStatus)) {
								echo '
												<div class="pull-right">
													<a class="status-btn">';
								echo wp_kses_post($openStatus);
								echo ' 
													</a>
												</div>';
							}
							?>
							<div class="clearfix"></div>
						</div>

					<?php } ?>
				</div>
			</div>
		</div>
	<?php
	} elseif ($listing_layout == 'grid_view2') {
	?>
		<div class="<?php echo esc_attr($listing_style); ?> <?php echo esc_attr($adClass); ?> lp-grid-box-contianer grid_view2 card1 lp-grid-box-contianer1 listing-grid-view2-outer" data-title="<?php echo get_the_title(); ?>" data-postid="<?php echo get_the_ID(); ?>" data-lattitue="<?php echo esc_attr($latitude); ?>" data-longitute="<?php echo esc_attr($longitude); ?>" data-posturl="<?php echo get_the_permalink(); ?>" data-lppinurl="<?php echo esc_url($lp_default_map_pin); ?>">
			<?php if (is_page_template('template-favourites.php')) { ?>
				<div class="remove-fav md-close" data-post-id="<?php echo get_the_ID(); ?>">
					<i class="fa fa-close"></i>
				</div>
			<?php } ?>
			<div class="lp-grid-box">
				<div class="lp-grid-box-thumb-container">
					<div class="lp-grid-box-thumb">
						<div class="show-img">
							<?php
							if (has_post_thumbnail()) {
								$image = wp_get_attachment_image_src(get_post_thumbnail_id(get_the_ID()), 'listingpro-blog-grid2');
								if (!empty($image[0])) {
									echo "<a href='" . get_the_permalink() . "' >
																<img alt='image' src='" . $image[0] . "' />
															</a>";
								} elseif (!empty($deafaultFeatImg)) {
									echo "<a href='" . get_the_permalink() . "' >
                                                               <img alt='image' src='" . $deafaultFeatImg . "' />
                                                            </a>";
								} else {
									echo '
														<a href="' . get_the_permalink() . '" >
															<img src="' . esc_html__('https://placehold.co/372x400', 'listingpro') . '" alt="image">
														</a>';
								}
							} elseif (!empty($deafaultFeatImg)) {
								echo "<a href='" . get_the_permalink() . "' >
													<img alt='image' src='" . $deafaultFeatImg . "' />
												</a>";
							} else {
								echo '
												<a href="' . get_the_permalink() . '" >
													<img src="' . esc_html__('https://placehold.co/372x400', 'listingpro') . '" alt="image">
												</a>';
							}
							?>
						</div>
						<div class="hide-img listingpro-list-thumb">
							<?php
							if (has_post_thumbnail()) {
								$image = wp_get_attachment_image_src(get_post_thumbnail_id(get_the_ID()), 'listingpro-blog-grid');
								if (!empty($image[0])) {
									echo "<a href='" . get_the_permalink() . "' >
																<img alt='image' src='" . $image[0] . "' />
															</a>";
								} elseif (!empty($deafaultFeatImg)) {
									echo "<a href='" . get_the_permalink() . "' >
                                                               <img alt='image' src='" . $deafaultFeatImg . "' />
                                                            </a>";
								} else {
									echo '
														<a href="' . get_the_permalink() . '" >
															<img src="' . esc_html__('https://placehold.co/372x240', 'listingpro') . '" alt="image">
														</a>';
								}
							} elseif (!empty($deafaultFeatImg)) {
								echo "<a href='" . get_the_permalink() . "' >
													<img alt='image' src='" . $deafaultFeatImg . "' />
												</a>";
							} else {
								echo '
												<a href="' . get_the_permalink() . '" >
													<img src="' . esc_html__('https://placehold.co/372x240', 'listingpro') . '" alt="image">
												</a>';
							}
							?>
						</div>
					</div>
					<div class="lp-grid-box-quick">
						<ul class="lp-post-quick-links clearfix">
							<li class="pull-left">
								<a href="#" data-post-type="grids" data-post-id="<?php echo esc_attr(get_the_ID()); ?>" data-success-text="<?php echo esc_html__('Saved', 'listingpro') ?>" class="status-btn <?php if ($favrt == 'yes') {
																																																					echo 'remove-fav';
																																																				} else {
																																																					echo 'add-to-fav';
																																																				} ?> lp-add-to-fav">
									<i class="<?php echo esc_attr($isfavouriteicon); ?>"></i> <span><?php echo wp_kses_post($isfavouritetext); ?></span>
								</a>
							</li>

						</ul>
					</div>
				</div>
				<div class="lp-grid-desc-container lp-border clearfix">
					<div class="lp-grid-box-description ">
						<div class="lp-grid-box-left pull-left">
							<h4 class="lp-h4">
								<a href="<?php echo get_the_permalink(); ?>">
									<?php echo wp_kses_post($CHeckAd); ?>
									<?php echo mb_substr(get_the_title(), 0, 40); ?>
									<?php echo wp_kses_post($claim); ?>
									<?php echo $__ip_availability_badge; ?>
								</a>
							</h4>
							<ul>
								<?php
								if ($lp_review_switch == 1) { ?>
									<li>
										<?php
										$NumberRating = listingpro_ratings_numbers($post->ID);
										if ($NumberRating != 0) {
											if ($NumberRating <= 1) {
												$review = esc_html__('Rating', 'listingpro');
											} else {
												$review = esc_html__('Ratings', 'listingpro');
											}
											echo lp_cal_listing_rate(get_the_ID());
										?>
											<span>
												<?php echo esc_attr($NumberRating); ?>
												<?php echo esc_attr($review); ?>
											</span>
										<?php
										} else {
											echo lp_cal_listing_rate(get_the_ID());
										}
										?>
									</li>
								<?php } ?>

								<li>
									<?php
									$cats = get_the_terms(get_the_ID(), 'listing-category');
									if (!empty($cats)) {
										$catCount = 1;
										foreach ($cats as $cat) {
											if ($catCount == 1) {
												$category_image = listing_get_tax_meta($cat->term_id, 'category', 'image');
												if (!empty($category_image)) {
													if( hasFontAwesomeIconClass($category_image) ){
														echo '<span class="cat-icon"><i class="icon icons-search-cat '.$category_image.'"></i></span>'; 
													}else{
														echo '<span class="cat-icon"><img class="icon icons8-Food" src="' . $category_image . '" alt="cat-icon"></span>';
													}
												}
												$term_link = get_term_link($cat);
												echo '
																<a href="' . $term_link . '">
																	' . $cat->name . '
																</a>';
												$catCount++;
											}
										}
									}
									?>
								</li>
							</ul>
							<div class="clearfix"></div>
							<p class="description-container"><?php echo mb_substr(strip_tags(get_the_content()), 0, 100) ?></p>
						</div>
						<div class="lp-grid-box-right pull-right">
						</div>
					</div>
					<?php
					$openStatus = listingpro_check_time(get_the_ID());
					$cats = get_the_terms(get_the_ID(), 'location');
					if (!empty($openStatus) || !empty($cats)) {
					?>
						<div class="lp-grid-box-bottom">
							<div class="pull-left">
								<div class="show">
									<?php
									$countlocs = 1;
									$cats = get_the_terms(get_the_ID(), 'location');
									if (!empty($cats)) {
										echo '<span class="cat-icon">' . listingpro_icons('mapMarkerGrey') . '</span>';
										foreach ($cats as $cat) {
											if ($countlocs == 1) {
												$term_link = get_term_link($cat);
												echo '
																<a href="' . $term_link . '">
																	' . $cat->name . '
																</a>';
											}
											$countlocs++;
										}
									}

									?>
								</div>
								<?php if (!empty($gAddress)) { ?>
									<div class="hide">
										<span class="cat-icon">
											<?php echo listingpro_icons('mapMarkerGrey'); ?>
										</span>
										<span class="text gaddress"><?php echo mb_substr($gAddress, 0, 30); ?>...</span>
									</div>
								<?php } ?>
							</div>
							<?php
							$openStatus = listingpro_check_time(get_the_ID());
							if (!empty($openStatus)) {
								echo '
												<div class="pull-right">
													<a class="status-btn">';
								echo wp_kses_post($openStatus);
								echo ' 
													</a>
												</div>';
							}
							?>
							<div class="clearfix"></div>
						</div>

					<?php } ?>
				</div>
			</div>
		</div>

	<?php
	} elseif ($listing_layout == 'grid_view3' || $grid_view_element == 'grid_view4') {
	?>
		<div class="<?php echo esc_attr($listing_style); ?> <?php echo esc_attr($adClass); ?> lp-grid-box-contianer grid_view2 card1 grid_view_s3 lp-grid-box-contianer1" data-title="<?php echo get_the_title(); ?>" data-postid="<?php echo get_the_ID(); ?>" data-lattitue="<?php echo esc_attr($latitude); ?>" data-longitute="<?php echo esc_attr($longitude); ?>" data-posturl="<?php echo get_the_permalink(); ?>">
			<?php if (is_page_template('template-favourites.php')) { ?>
				<div class="remove-fav md-close" data-post-id="<?php echo get_the_ID(); ?>">
					<i class="fa fa-close"></i>
				</div>
			<?php } ?>
			<div class="lp-grid-box lp-grid-style3-outer">
				<div class="lp-grid-box-thumb-container clearfix">

					<div class="lp-grid-box-thumb">
						<div class="show-img">
							<?php
							if (has_post_thumbnail()) {
								$image = wp_get_attachment_image_src(get_post_thumbnail_id(get_the_ID()), 'listingpro-blog-grid');
								if (!empty($image[0])) {
									echo "<a href='" . get_the_permalink() . "' >
																<img alt='image' src='" . $image[0] . "' />
															</a>";
								} elseif (!empty($deafaultFeatImg)) {
									echo "<a href='" . get_the_permalink() . "' >
                                                               <img alt='image' src='" . $deafaultFeatImg . "' />
                                                            </a>";
								} else {
									echo '
                                                <a href="' . get_the_permalink() . '" >
                                                    <img src="' . esc_html__('https://placehold.co/372x400', 'listingpro') . '" alt="image">
                                                </a>';
								}
							} elseif (!empty($deafaultFeatImg)) {
								echo "<a href='" . get_the_permalink() . "' >
													<img alt='image' src='" . $deafaultFeatImg . "' />
												</a>";
							} else {
								echo '
												<a href="' . get_the_permalink() . '" >
													<img src="' . esc_html__('https://placehold.co/372x400', 'listingpro') . '" alt="image">
												</a>';
							}
							?>
						</div>
						<div class="hide-img listingpro-list-thumb">
							<?php
							if (has_post_thumbnail()) {
								$image = wp_get_attachment_image_src(get_post_thumbnail_id(get_the_ID()), 'listingpro_liststyle181_172');
								if (!empty($image[0])) {
									echo "<a href='" . get_the_permalink() . "' >
																<img alt='image' src='" . $image[0] . "' />
															</a>";
								} elseif (!empty($deafaultFeatImg)) {
									echo "<a href='" . get_the_permalink() . "' >
                                                               <img alt='image' src='" . $deafaultFeatImg . "' />
                                                            </a>";
								} else {
									echo '
														<a href="' . get_the_permalink() . '" >
															<img src="' . esc_html__('https://placehold.co/181x172', 'listingpro') . '" alt="image">
														</a>';
								}
							} elseif (!empty($deafaultFeatImg)) {
								echo "<a href='" . get_the_permalink() . "' >
													<img src='" . $deafaultFeatImg . "' />
												</a>";
							} else {
								echo '
												<a href="' . get_the_permalink() . '" >
													<img src="' . esc_html__('https://placehold.co/181x172', 'listingpro') . '" alt="image">
												</a>';
							}
							?>
						</div>

						<?php
						$cats = get_the_terms(get_the_ID(), 'listing-category');
						if (!empty($cats)) {
							$catCount = 1;
							foreach ($cats as $cat) {
								if ($catCount == 1) {
									$category_image = listing_get_tax_meta($cat->term_id, 'category', 'image');
									if (!empty($category_image)) {
										if( hasFontAwesomeIconClass($category_image) ){
											$term_link = get_term_link($cat);
											echo '<div class="lp-category-icon-outer"><a href="' . $term_link . '"><span class="cat-icon"><i class="icon icons-search-cat '.$category_image.'"></i></span></a></div>';
										}else{
											$term_link = get_term_link($cat);
											echo '<div class="lp-category-icon-outer"><a href="' . $term_link . '"><span class="cat-icon"><img class="icon icons8-Food" src="' . $category_image . '" alt="cat-icon"></span></a></div>';
										}
									}
									$catCount++;
								}
							}
						}
						?>
						<a href="#" data-post-type="grids" data-post-id="<?php echo esc_attr(get_the_ID()); ?>" data-success-text="<?php echo esc_html__('Saved', 'listingpro') ?>" class="status-btn <?php if ($favrt == 'yes') {
																																																			echo 'remove-fav';
																																																		} else {
																																																			echo 'add-to-fav';
																																																		} ?> lp-add-to-fav lp-add-to-fav-grid3">
							<i class="<?php echo esc_attr($isfavouriteicon); ?>"></i>
						</a>
					</div>

				</div>

				<div class="lp-grid-desc-container lp-border clearfix">

					<div class="lp-grid-box-description ">
						<?php
						$author_avatar_url = get_user_meta(get_the_author_meta('ID'), "listingpro_author_img_url", true);
						$avatar = '';
						if (!empty($author_avatar_url)) {
							$avatar =  $author_avatar_url;
						} else {
							$avatar_url = listingpro_get_avatar_url(get_the_author_meta('ID'), $size = '94');
							$avatar =  $avatar_url;
						}
						?>
						<div class="author-img-outer pos-relative">
							<div class="author-img">
								<a href="<?php echo get_author_posts_url(get_the_author_meta('ID')); ?>"><img src="<?php echo esc_url($avatar); ?>" alt="image"></a>

							</div>
							<?php echo wp_kses_post($CHeckAd); ?>
						</div>
						<div class="lp-grid-box-left pull-left">
							<h4 class="lp-h4">
								<a href="<?php echo get_the_permalink(); ?>">
									<?php echo wp_kses_post($CHeckAd); ?>
									<?php echo mb_substr(get_the_title(), 0, 40); ?>
									<?php echo wp_kses_post($claim); ?>
									<?php echo $__ip_availability_badge; ?>
								</a>
							</h4>
							<div class="lp-grid3-category-outer">
								<?php
								$cats = get_the_terms(get_the_ID(), 'features');
								if (!empty($cats)) {
									$catCount = 3;
									foreach ($cats as $cat) {
										if ($catCount == 3) {

											$term_link = get_term_link($cat);
											echo '
																<a href="' . $term_link . '">
																	' . $cat->name . '
																</a>';
											$catCount++;
										}
									}
								}
								?>
							</div>
							<div class="lp-grid3-category-outer description-container">
								<?php
								$cats = get_the_terms(get_the_ID(), 'listing-category');
								if (!empty($cats)) {
									$catCount = 1;
									foreach ($cats as $cat) {
										if ($catCount == 1) {
											$category_image = listing_get_tax_meta($cat->term_id, 'category', 'image');
											if (!empty($category_image)) {
												if( hasFontAwesomeIconClass($category_image) ){
													echo '<span class="cat-icon"><i class="icon icons-search-cat '.$category_image.'"></i></span>'; 
												}else{
													echo '<span class="cat-icon"><img class="icon icons8-Food" src="' . $category_image . '" alt="cat-icon"></span>';
												}
											}
											$term_link = get_term_link($cat);
											echo '
																<a href="' . $term_link . '">
																	' . $cat->name . '
																</a>';
											$catCount++;
										}
									}
								}
								?>
							</div>

							<ul>
								<?php
								if ($lp_review_switch == 1) { ?>
									<li>
										<?php
										$NumberRating = listingpro_ratings_numbers($post->ID);
										if ($NumberRating != 0) {
											if ($NumberRating <= 1) {
												$review = esc_html__('Rating', 'listingpro');
											} else {
												$review = esc_html__('Ratings', 'listingpro');
											}
											echo lp_cal_listing_rate(get_the_ID());
										?>
											<span>
												<?php echo esc_attr($NumberRating); ?>
												<?php echo esc_attr($review); ?>
											</span>
										<?php
										} else {
											echo lp_cal_listing_rate(get_the_ID());
										}
										?>
									</li>
								<?php } ?>


							</ul>

						</div>
						<div class="lp-grid-box-right pull-right">
						</div>
					</div>
					<?php
					$openStatus = listingpro_check_time(get_the_ID());
					$cats = get_the_terms(get_the_ID(), 'location');
					if (!empty($openStatus) || !empty($cats)) {
					?>
						<div class="lp-grid-box-bottom clearfix">
							<?php if (!empty($phone)) { ?>
								<div class="pull-left lp-grid3-phone">
									<i class="fa fa-phone" aria-hidden="true"></i> <a href="tel:<?php echo wp_kses_post($phone); ?>"><?php echo esc_html($phone); ?></a>
								</div>
							<?php } ?>
							<div class="pull-left">
								<div class="show">
									<?php
									$countlocs = 1;
									$cats = get_the_terms(get_the_ID(), 'location');
									if (!empty($cats)) {
										echo '<i class="fa fa-map-marker" aria-hidden="true"></i>';
										foreach ($cats as $cat) {
											if ($countlocs == 1) {
												$term_link = get_term_link($cat);
												echo '
																<a href="' . $term_link . '">
																	' . $cat->name . '
																</a>';
											}
											$countlocs++;
										}
									}

									?>
								</div>
								<?php if (!empty($gAddress)) { ?>
									<div class="hide">
										<i class="fa fa-map-marker" aria-hidden="true"></i>
										<span class="text gaddress"><?php echo mb_substr($gAddress, 0, 30); ?>...</span>
									</div>
								<?php } ?>
							</div>


							<div class="clearfix"></div>

						</div>

					<?php } ?>
					<div class="lp-grid-style3-outer-hide clearfix">
						<?php
						$author_avatar_url = get_user_meta(get_the_author_meta('ID'), "listingpro_author_img_url", true);
						$avatar = '';
						if (!empty($author_avatar_url)) {
							$avatar =  $author_avatar_url;
						} else {
							$avatar_url = listingpro_get_avatar_url(get_the_author_meta('ID'), $size = '94');
							$avatar =  $avatar_url;
						}
						?>
						<div class="author-img-outer pos-relative">
							<div class="author-img">
								<a href="<?php echo get_author_posts_url(get_the_author_meta('ID')); ?>"><img src="<?php echo esc_url($avatar); ?>" alt="image"></a>

							</div>

						</div>
						<ul class="list-style-none clearfix">
							<?php
							if ($lp_review_switch == 1)
								$NumberRating = listingpro_ratings_numbers($post->ID); { ?>
								<?php if (!empty($NumberRating)) { ?>
									<li class="col-md-3 lp-loop3-rating">
										<?php
										$NumberRating = listingpro_ratings_numbers($post->ID);
										if ($NumberRating != 0) {
											if ($NumberRating <= 1) {
												$review = esc_html__('Rating', 'listingpro');
											} else {
												$review = esc_html__('Ratings', 'listingpro');
											}
											echo lp_cal_listing_rate(get_the_ID());
										?>
											<span>
												<?php echo esc_attr($NumberRating); ?>
												<?php echo esc_attr($review); ?>
											</span>
										<?php
										} else {
											echo lp_cal_listing_rate(get_the_ID());
										}
										?>
									</li>
								<?php } ?>
							<?php } ?>
							<?php
							$lp_loop_phone_box = '';
							if ($lp_review_switch == 1)
								$NumberRating = listingpro_ratings_numbers($post->ID); { ?>
								<?php if (empty($NumberRating)) { ?>
									<?php $lp_loop_phone_box = 'lp-loop-phone-box'; ?>
								<?php } ?>
							<?php } ?>
							<?php if (!empty($phone)) { ?>
								<li class="col-md-3 lp-grid3-phone <?php echo esc_attr($lp_loop_phone_box); ?>">
									<i class="fa fa-phone" aria-hidden="true"></i> <?php echo esc_html($phone); ?>
								</li>
							<?php } ?>

							<?php if (!empty($gAddress)) { ?>
								<li class="col-md-6 ">
									<i class="fa fa-map-marker" aria-hidden="true"></i>
									<span class="text gaddress"><?php echo mb_substr($gAddress, 0, 30); ?>...</span>
								</li>
							<?php } ?>

						</ul>
					</div>
				</div>

			</div>
		</div>

	<?php

	} elseif ($listing_layout == 'grid_view_v2' || $listing_layout == 'list_view_v2') {
		get_template_part('templates/loop-list-view');
	} elseif ($listing_layout == 'grid_view_v3') {
		get_template_part('templates/loop/loop3');
	} elseif ($listing_layout == 'list_view') {
	?>

		<div class="col-md-12 lp-grid-box-contianer list_view card1 lp-grid-box-contianer1 <?php echo esc_attr($adClass); ?>" data-title="<?php echo get_the_title(); ?>" data-postid="<?php echo get_the_ID(); ?>" data-lattitue="<?php echo esc_attr($latitude); ?>" data-longitute="<?php echo esc_attr($longitude); ?>" data-posturl="<?php echo get_the_permalink(); ?>" data-lppinurl="<?php echo esc_url($lp_default_map_pin); ?>">
			<?php if (is_page_template('template-favourites.php')) { ?>
				<div class="remove-fav md-close" data-post-id="<?php echo get_the_ID(); ?>">
					<i class="fa fa-close"></i>
				</div>
			<?php } ?>
			<div class="lp-grid-box lp-border lp-border-radius-8">
				<div class="lp-grid-box-thumb-container">
					<div class="lp-grid-box-thumb">
						<div class="show">
							<?php
							if (has_post_thumbnail()) {
								$image = wp_get_attachment_image_src(get_post_thumbnail_id(get_the_ID()), 'listingpro-blog-grid');
								if (!empty($image[0])) {
									echo "<a href='" . get_the_permalink() . "' >
																<img alt='image' src='" . $image[0] . "' />
															</a>";
								} elseif (!empty($deafaultFeatImg)) {
									echo "<a href='" . get_the_permalink() . "' >
                                                               <img alt='image' src='" . $deafaultFeatImg . "' />
                                                            </a>";
								} else {
									echo '
														<a href="' . get_the_permalink() . '" >
															<img src="' . esc_html__('https://placehold.co/372x240', 'listingpro') . '" alt="image">
														</a>';
								}
							} elseif (!empty($deafaultFeatImg)) {
								echo "<a href='" . get_the_permalink() . "' >
													<img src='" . $deafaultFeatImg . "' />
												</a>";
							} else {
								echo '
												<a href="' . get_the_permalink() . '" >
													<img src="' . esc_html__('https://placehold.co/372x240', 'listingpro') . '" alt="image">
												</a>';
							}
							?>
						</div>
						<div class="hide">
							<?php
							if (has_post_thumbnail()) {
								$image = wp_get_attachment_image_src(get_post_thumbnail_id(get_the_ID()), 'listingpro-blog-grid');
								if (!empty($image[0])) {
									echo "<a href='" . get_the_permalink() . "' >
																<img alt='image' src='" . $image[0] . "' />
															</a>";
								} elseif (!empty($deafaultFeatImg)) {
									echo "<a href='" . get_the_permalink() . "' >
                                                               <img alt='image' src='" . $deafaultFeatImg . "' />
                                                            </a>";
								} else {
									echo '
														<a href="' . get_the_permalink() . '" >
															<img src="' . esc_html__('https://placehold.co/372x240', 'listingpro') . '" alt="image">
														</a>';
								}
							} elseif (!empty($deafaultFeatImg)) {
								echo "<a href='" . get_the_permalink() . "' >
													<img alt='image' src='" . $deafaultFeatImg . "' />
												</a>";
							} else {
								echo '
												<a href="' . get_the_permalink() . '" >
													<img src="' . esc_html__('https://placehold.co/372x240', 'listingpro') . '" alt="image">
												</a>';
							}
							?>
						</div>
					</div><!-- ../grid-box-thumb -->
					<div class="lp-grid-box-quick">
						<ul class="lp-post-quick-links">
							<li>
								<a href="#" data-post-type="grids" data-post-id="<?php echo esc_attr(get_the_ID()); ?>" data-success-text="<?php echo esc_html__('Saved', 'listingpro') ?>" class="status-btn <?php if ($favrt == 'yes') {
																																																					echo 'remove-fav';
																																																				} else {
																																																					echo 'add-to-fav';
																																																				} ?> lp-add-to-fav">
									<i class="<?php echo esc_attr($isfavouriteicon); ?>"></i> <span><?php echo wp_kses_post($isfavouritetext); ?></span>
								</a>
							</li>
							<li>
								<a class="icon-quick-eye md-trigger qickpopup" data-mappin="<?php echo esc_url($lp_default_map_pin); ?>" data-modal="modal-1<?php echo esc_attr(get_the_ID()); ?>"><i class="fa fa-eye"></i> <?php echo esc_html__('Preview', 'listingpro'); ?></a>
							</li>
						</ul>
					</div><!-- ../grid-box-quick-->
				</div>
				<div class="lp-grid-box-description ">
					<div class="lp-grid-box-left pull-left">
						<h4 class="lp-h4">
							<a href="<?php echo esc_url(get_the_permalink()); ?>">
								<?php echo wp_kses_post($CHeckAd); ?>
								<!--<span class="listing-pro">Ad</span>-->
								<?php echo get_the_title(); ?>
								<?php echo wp_kses_post($claim); ?>
									<?php echo $__ip_availability_badge; ?>
							</a>
						</h4>
						<ul>
							<?php
							if ($lp_review_switch == 1) { ?>
								<li>
									<?php
									$NumberRating = listingpro_ratings_numbers($post->ID);
									if ($NumberRating != 0) {
										if ($NumberRating <= 1) {
											$review = esc_html__('Rating', 'listingpro');
										} else {
											$review = esc_html__('Ratings', 'listingpro');
										}
										echo lp_cal_listing_rate(get_the_ID());
									?>
										<span>
											<?php echo esc_attr($NumberRating); ?>
											<?php echo esc_attr($review); ?>
										</span>
									<?php
									} else {
										echo lp_cal_listing_rate(get_the_ID());
									}
									?>
								</li>
							<?php
							}
							?>
							<li class="middle">
								<?php echo listingpro_price_dynesty_text($post->ID); ?>
							</li>
							<li>
								<?php
								$cats = get_the_terms(get_the_ID(), 'listing-category');
								if (!empty($cats)) {
									$catCount = 1;
									foreach ($cats as $cat) {
										if ($catCount == 1) {
											$category_image = listing_get_tax_meta($cat->term_id, 'category', 'image');
											if (!empty($category_image)) {
												if( hasFontAwesomeIconClass($category_image) ){
													echo '<span class="cat-icon"><i class="icon icons-search-cat '.$category_image.'"></i></span>'; 
												}else{
													echo '<span class="cat-icon"><img class="icon icons8-Food" src="' . $category_image . '" alt="cat-icon"></span>';
												}
											}
											$term_link = get_term_link($cat);
											echo '
															<a href="' . $term_link . '">
																' . $cat->name . '
															</a>';
											$catCount++;
										}
									}
								}
								?>
							</li>
						</ul>
						<?php echo listingpro_last_review_by_list_ID(get_the_ID()); ?>
					</div>
					<div class="lp-grid-box-right pull-right">
					</div>
				</div>
				<?php
				$openStatus = listingpro_check_time(get_the_ID());
				$cats = get_the_terms(get_the_ID(), 'location');
				if (!empty($openStatus) || !empty($cats)) {
				?>
					<div class="lp-grid-box-bottom">
						<div class="pull-left">
							<div class="show">
								<?php
								$countlocs = 1;
								$cats = get_the_terms(get_the_ID(), 'location');
								if (!empty($cats)) {
									echo '<span class="cat-icon">' . listingpro_icons('mapMarkerGrey') . '</span>';
									foreach ($cats as $cat) {
										if ($countlocs == 1) {
											$term_link = get_term_link($cat);
											echo '
																<a href="' . $term_link . '">
																	' . $cat->name . '
																</a>';
										}
										$countlocs++;
									}
								}

								?>
							</div>
							<?php if (!empty($gAddress)) { ?>
								<div class="hide">
									<span class="cat-icon">
										<img class="icon icons8-Food" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADAAAAAwCAYAAABXAvmHAAABv0lEQVRoge1ZUbGDMBA8CUhAAhKQUAlIqILsOkBCJSABCUhAAhJ4Hw1vGB4tB82R9A07c7+Z3dxmuQSRCxcuBAPJwjlHAC2ADsDgqwfQOOdIsojN8w9Ilp7wqKyOZBWbt5AsdhJfExKnI37Xhw/ITzWQLL+V/G+dJoJkHpr8rBP2dsIzYUKTn6o3JU/yZkh+xNNKlZkAfJY42hpMyPvI1JJoSZYkC5K571y7owt5cAH+C6shUJPM1tYAUGvWcM4xuAAAjXLnV8nP1tF0orUQ0Ctaf9taRxkE4c+BUkCuEJDFErD58QooYLQQsBmhSguVCgGdhQBNgmwePijDILgAbYy+i0CSd80aAOrgAnaOEd00XZLMvG00Oz8CRuOE9vCFKJMvsYgIgMcJAhoT8iIiJKsTdr+yFGBto8HMPhNga6OHKXkR0y6cc6UUMetC+Ox/BYMu2Ht/CSgvJ8nt/gTfhT4A+X7rEmQpQjNZvi3NBGsK7JhxkrDOEt5KR17q4llniSNWim6dJbAvleJbZw1QPpkkY50lFNGaju9fwT+/r5E//0fGUawd6uQO7Rbmd2iS99h8DsG/QqSZOBcu/BP8AL+XHO7G8elbAAAAAElFTkSuQmCC" alt="cat-icon">
									</span>
									<span class="text gaddress"><?php echo esc_attr($gAddress); ?></span>
								</div>
							<?php } ?>
						</div>
						<?php
						$openStatus = listingpro_check_time(get_the_ID());
						if (!empty($openStatus)) {
							echo '
												<div class="pull-right">
													<a class="status-btn">';
							echo wp_kses_post($openStatus);
							echo ' 
													</a>
												</div>';
						}
						?>
						<div class="clearfix"></div>
					</div>

				<?php } ?>
			</div>
		</div>
	<?php
	} elseif ($listing_layout == 'list_view3') {
	?>
		<div class="col-md-12 lp-grid-box-contianer list_view card1 lp-grid-box-contianer1 <?php echo esc_attr($adClass); ?>" data-title="<?php echo get_the_title(); ?>" data-postid="<?php echo get_the_ID(); ?>" data-lattitue="<?php echo esc_attr($latitude); ?>" data-longitute="<?php echo esc_attr($longitude); ?>" data-posturl="<?php echo get_the_permalink(); ?>">
			<?php if (is_page_template('template-favourites.php')) { ?>
				<div class="remove-fav md-close" data-post-id="<?php echo get_the_ID(); ?>">
					<i class="fa fa-close"></i>
				</div>
			<?php } ?>
			<div class="lp-grid-box lp-grid-style3-outer">
				<div class="lp-grid-box-thumb-container clearfix">

					<div class="lp-grid-box-thumb">
						<div class="show-img">
							<?php
							if (has_post_thumbnail()) {
								$image = wp_get_attachment_image_src(get_post_thumbnail_id(get_the_ID()), 'listingpro-blog-grid');
								if (!empty($image[0])) {
									echo "<a href='" . get_the_permalink() . "' >
																<img alt='image' src='" . $image[0] . "' />
															</a>";
								} elseif (!empty($deafaultFeatImg)) {
									echo "<a href='" . get_the_permalink() . "' >
                                                               <img alt='image' src='" . $deafaultFeatImg . "' />
                                                            </a>";
								} else {
									echo '
														<a href="' . get_the_permalink() . '" >
															<img src="' . esc_html__('https://placehold.co/372x400', 'listingpro') . '" alt="image">
														</a>';
								}
							} elseif (!empty($deafaultFeatImg)) {
								echo "<a href='" . get_the_permalink() . "' >
													<img alt='image' src='" . $deafaultFeatImg . "' />
												</a>";
							} else {
								echo '
												<a href="' . get_the_permalink() . '" >
													<img src="' . esc_html__('https://placehold.co/372x400', 'listingpro') . '" alt="image">
												</a>';
							}
							?>
						</div>
						<div class="hide-img listingpro-list-thumb">
							<?php
							if (has_post_thumbnail()) {
								$image = wp_get_attachment_image_src(get_post_thumbnail_id(get_the_ID()), 'listingpro_liststyle181_172');
								if (!empty($image[0])) {
									echo "<a href='" . get_the_permalink() . "' >
																<img alt='image' src='" . $image[0] . "' />
															</a>";
								} elseif (!empty($deafaultFeatImg)) {
									echo "<a href='" . get_the_permalink() . "' >
                                                               <img alt='image' src='" . $deafaultFeatImg . "' />
                                                            </a>";
								} else {
									echo '
														<a href="' . get_the_permalink() . '" >
															<img src="' . esc_html__('https://placehold.co/181x172', 'listingpro') . '" alt="image">
														</a>';
								}
							} elseif (!empty($deafaultFeatImg)) {
								echo "<a href='" . get_the_permalink() . "' >
													<img alt='image' src='" . $deafaultFeatImg . "' />
												</a>";
							} else {
								echo '
												<a href="' . get_the_permalink() . '" >
													<img src="' . esc_html__('https://placehold.co/181x172', 'listingpro') . '" alt="image">
												</a>';
							}
							?>
						</div>

						<?php
						$cats = get_the_terms(get_the_ID(), 'listing-category');
						if (!empty($cats)) {
							$catCount = 1;
							foreach ($cats as $cat) {
								if ($catCount == 1) {
									$category_image = listing_get_tax_meta($cat->term_id, 'category', 'image');
									if (!empty($category_image)) {
										if( hasFontAwesomeIconClass($category_image) ){
											$term_link = get_term_link($cat);
											echo '<div class="lp-category-icon-outer"><a href="' . $term_link . '"><span class="cat-icon"><i class="icon icons-search-cat '.$category_image.'"></i></span></a></div>';
										}else{
											$term_link = get_term_link($cat);
											echo '<div class="lp-category-icon-outer"><a href="' . $term_link . '"><span class="cat-icon"><img class="icon icons8-Food" src="' . $category_image . '" alt="cat-icon"></span></a></div>';
										}
									}

									$catCount++;
								}
							}
						}
						?>
						<a href="#" data-post-type="grids" data-post-id="<?php echo esc_attr(get_the_ID()); ?>" data-success-text="<?php echo esc_html__('Saved', 'listingpro') ?>" class="status-btn <?php if ($favrt == 'yes') {
																																																			echo 'remove-fav';
																																																		} else {
																																																			echo 'add-to-fav';
																																																		} ?> lp-add-to-fav lp-add-to-fav-grid3">
							<i class="<?php echo esc_attr($isfavouriteicon); ?>"></i>
						</a>
					</div>

				</div>

				<div class="lp-grid-desc-container lp-border clearfix">

					<div class="lp-grid-box-description ">
						<?php
						$author_avatar_url = get_user_meta(get_the_author_meta('ID'), "listingpro_author_img_url", true);
						$avatar = '';
						if (!empty($author_avatar_url)) {
							$avatar =  $author_avatar_url;
						} else {
							$avatar_url = listingpro_get_avatar_url(get_the_author_meta('ID'), $size = '94');
							$avatar =  $avatar_url;
						}
						?>
						<div class="author-img-outer pos-relative">
							<div class="author-img">
								<a href="<?php echo get_author_posts_url(get_the_author_meta('ID')); ?>"><img src="<?php echo esc_url($avatar); ?>" alt="image"></a>

							</div>
							<?php echo wp_kses_post($CHeckAd); ?>
						</div>
						<div class="lp-grid-box-left pull-left">
							<h4 class="lp-h4">
								<a href="<?php echo get_the_permalink(); ?>">
									<?php echo wp_kses_post($CHeckAd); ?>
									<?php echo mb_substr(get_the_title(), 0, 40); ?>
									<?php echo wp_kses_post($claim); ?>
									<?php echo $__ip_availability_badge; ?>
								</a>
							</h4>
							<div class="lp-grid3-category-outer">
								<?php
								$cats = get_the_terms(get_the_ID(), 'features');
								if (!empty($cats)) {
									$catCount = 3;
									foreach ($cats as $cat) {
										if ($catCount == 3) {

											$term_link = get_term_link($cat);
											echo '
																<a href="' . $term_link . '">
																	' . $cat->name . '
																</a>';
											$catCount++;
										}
									}
								}
								?>
							</div>
							<div class="lp-grid3-category-outer description-container">
								<?php
								$cats = get_the_terms(get_the_ID(), 'listing-category');
								if (!empty($cats)) {
									$catCount = 1;
									foreach ($cats as $cat) {
										if ($catCount == 1) {
											$category_image = listing_get_tax_meta($cat->term_id, 'category', 'image');
											if (!empty($category_image)) {
												if( hasFontAwesomeIconClass($category_image) ){
													echo '<span class="cat-icon"><i class="icon icons-search-cat '.$category_image.'"></i></span>'; 
												}else{
													echo '<span class="cat-icon"><img class="icon icons8-Food" src="' . $category_image . '" alt="cat-icon"></span>';
												}
											}
											$term_link = get_term_link($cat);
											echo '
																<a href="' . $term_link . '">
																	' . $cat->name . '
																</a>';
											$catCount++;
										}
									}
								}
								?>
							</div>

							<ul>
								<?php
								if ($lp_review_switch == 1) { ?>
									<li>
										<?php
										$NumberRating = listingpro_ratings_numbers($post->ID);
										if ($NumberRating != 0) {
											if ($NumberRating <= 1) {
												$review = esc_html__('Rating', 'listingpro');
											} else {
												$review = esc_html__('Ratings', 'listingpro');
											}
											echo lp_cal_listing_rate(get_the_ID());
										?>
											<span>
												<?php echo esc_attr($NumberRating); ?>
												<?php echo esc_attr($review); ?>
											</span>
										<?php
										} else {
											echo lp_cal_listing_rate(get_the_ID());
										}
										?>
									</li>
								<?php } ?>


							</ul>

						</div>
						<div class="lp-grid-box-right pull-right">
						</div>
					</div>
					<?php
					$openStatus = listingpro_check_time(get_the_ID());
					$cats = get_the_terms(get_the_ID(), 'location');
					if (!empty($openStatus) || !empty($cats)) {
					?>
						<div class="lp-grid-box-bottom clearfix">
							<div class="pull-right">
								<div class="show">
									<?php
									$countlocs = 1;
									$cats = get_the_terms(get_the_ID(), 'location');
									if (!empty($cats)) {
										echo '<i class="fa fa-map-marker" aria-hidden="true"></i>';
										foreach ($cats as $cat) {
											if ($countlocs == 1) {
												$term_link = get_term_link($cat);
												echo '
																<a href="' . $term_link . '">
																	' . $cat->name . '
																</a>';
											}
											$countlocs++;
										}
									}

									?>
								</div>
								<?php if (!empty($gAddress)) { ?>
									<div class="hide">
										<i class="fa fa-map-marker" aria-hidden="true"></i>
										<span class="text gaddress"><?php echo mb_substr($gAddress, 0, 30); ?>...</span>
									</div>
								<?php } ?>
							</div>

							<?php if (!empty($phone)) { ?>
								<div class="pull-left lp-grid3-phone">
									<i class="fa fa-phone" aria-hidden="true"></i> <?php echo wp_kses_post($phone); ?>
								</div>
							<?php } ?>
							<div class="clearfix"></div>

						</div>

					<?php } ?>
					<div class="lp-grid-style3-outer-hide clearfix">
						<?php
						$author_avatar_url = get_user_meta(get_the_author_meta('ID'), "listingpro_author_img_url", true);
						$avatar = '';
						if (!empty($author_avatar_url)) {
							$avatar =  $author_avatar_url;
						} else {
							$avatar_url = listingpro_get_avatar_url(get_the_author_meta('ID'), $size = '94');
							$avatar =  $avatar_url;
						}
						?>
						<div class="author-img-outer pos-relative">
							<div class="author-img">
								<a href="<?php echo get_author_posts_url(get_the_author_meta('ID')); ?>"><img src="<?php echo esc_url($avatar); ?>" alt="image"></a>

							</div>

						</div>
						<ul class="list-style-none clearfix">
							<?php
							if ($lp_review_switch == 1)
								$NumberRating = listingpro_ratings_numbers($post->ID); { ?>
								<?php if (!empty($NumberRating)) { ?>
									<li class="col-md-3 lp-loop3-rating">
										<?php
										$NumberRating = listingpro_ratings_numbers($post->ID);
										if ($NumberRating != 0) {
											if ($NumberRating <= 1) {
												$review = esc_html__('Rating', 'listingpro');
											} else {
												$review = esc_html__('Ratings', 'listingpro');
											}
											echo lp_cal_listing_rate(get_the_ID());
										?>
											<span>
												<?php echo esc_attr($NumberRating); ?>
												<?php echo esc_attr($review); ?>
											</span>
										<?php
										} else {
											echo lp_cal_listing_rate(get_the_ID());
										}
										?>
									</li>
								<?php } ?>
							<?php } ?>
							<?php
							$lp_loop_phone_box = '';
							if ($lp_review_switch == 1)
								$NumberRating = listingpro_ratings_numbers($post->ID); { ?>
								<?php if (empty($NumberRating)) { ?>
									<?php $lp_loop_phone_box = 'lp-loop-phone-box'; ?>
								<?php } ?>
							<?php } ?>
							<?php if (!empty($phone)) { ?>
								<li class="col-md-3 lp-grid3-phone <?php echo esc_attr($lp_loop_phone_box); ?>">
									<i class="fa fa-phone" aria-hidden="true"></i> <?php echo esc_html($phone); ?>
								</li>
							<?php } ?>

							<?php if (!empty($gAddress)) { ?>
								<li class="col-md-6 ">
									<i class="fa fa-map-marker" aria-hidden="true"></i>
									<span class="text gaddress"><?php echo mb_substr($gAddress, 0, 30); ?>...</span>
								</li>
							<?php } ?>

						</ul>
					</div>
				</div>

			</div>
		</div>
	<?php
	} elseif ($listing_layout == 'lp-list-view-compact') {
		get_template_part('templates/lp-list-view-compact');
	}
	?>

	<?php //get_template_part('templates/preview'); 
	?>

	<?php
	if ($postGridCount % $postGridnumber == 0) {
		echo '<div class="clearfix lp-archive-clearfix"></div>';
	}
	?>