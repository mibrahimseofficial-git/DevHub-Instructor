<?php
			$output = null;
			
			global $listingpro_options;
			$default_feature_img= lp_theme_option_url('lp_def_featured_image');
			$lp_review_switch = $listingpro_options['lp_review_switch'];

			if(!isset($postGridCountnew)){
				$postGridCountnew = '0';
			}
			global $postGridCountnew;			
			$postGridCountnew++;
			
			$listing_style = '';
				$listing_style = $listingpro_options['listing_style'];
				if(isset($_GET['list-style']) && !empty($_GET['list-style'])){
					$listing_style = esc_html($_GET['list-style']);
				}
				if(is_front_page()){
					$listing_style = 'col-md-4 col-sm-6';
					$postGridnumber = 3;
				}else{
					if($listing_style == '1'){
						$listing_style = 'col-md-4 col-sm-6';
						$postGridnumber = 3;
					}elseif($listing_style == '3' && !is_page() ||  $listing_style == '6'){
						$listing_style = 'col-md-6 col-sm-12';
						$postGridnumber = 2;
					}elseif($listing_style == '4'){
						$listing_style = 'col-md-6 col-sm-12';
						$postGridnumber = 2;
					
					}elseif($listing_style == '5'){
						$listing_style = 'col-md-12 col-sm-12';
						$postGridnumber = 2;
					}else{
						$listing_style = 'col-md-4 col-sm-6';
						$postGridnumber =3;
					}
				}
				if(is_page_template('template-favourites.php')){
					$listing_style = 'col-md-4 col-sm-6';
					$postGridnumber =3;
				}
				$menu_check = get_post_meta( get_the_ID(), 'lp-listing-menu', true );
				$phone =   listing_get_metabox('phone');
				$latitude = listing_get_metabox('latitude');
				$longitude = listing_get_metabox('longitude');
				$lp_lat = listing_get_metabox_by_ID('latitude', get_the_ID());
				$lp_lng = listing_get_metabox_by_ID('longitude', get_the_ID());
				$gAddress = listing_get_metabox('gAddress');
				$isfavouriteicon = listingpro_is_favourite_grids(get_the_ID(),$onlyicon=true);
				$isfavouritetext = listingpro_is_favourite_grids(get_the_ID(),$onlyicon=false);
				$tags       =   get_the_terms( get_the_ID(), 'list-tags' );
				
				$adStatus = apply_filters('lp_get_ad_status', '', get_the_ID());
				$CHeckAd = '';
				$adClass = '';
				if($adStatus == 'active'){
					$CHeckAd = '<span class="listing-pro">'.esc_html__('Ad','listingpro').'</span>';
					$adClass = 'promoted';
				}
				$claimed_section = listing_get_metabox('claimed_section');

				$claim = '';
				$claimStatus = '';
				
				if($claimed_section == 'claimed') {
					if(is_singular( 'listing' ) ){
						$claimStatus = esc_html__('Claimed', 'listingpro');
					}
					$claim = '<span class="verified simptip-position-top simptip-movable" data-tooltip="'. esc_html__('Claimed', 'listingpro').'"><i class="fa fa-check"></i> '.$claimStatus.'</span>';

				}elseif($claimed_section == 'not_claimed') {
					$claim = '';

				}
				$listing_layout = $listingpro_options['listing_views'];
				$PM_btns_all    =   '';
				$is_phone       =   '';
				$is_menu        =   '';
				$is_gAddress    =   '';

				if( !empty( $phone ) && !empty( $menu_check ) && is_array( $menu_check ) && !empty( $gAddress ) )
				{
					$PMG_btns_both   =   'ok';
				}
				if( !empty( $phone ) )
				{
					$is_phone   =   'ok';
				}
				if( !empty( $menu_check ) && is_array( $menu_check ) )
				{
					$is_menu    =   'ok';
				}
				if( !empty( $gAddress ) )
				{
					$is_gAddress    =   'ok';
				}
				$b_logo =   $listingpro_options['business_logo_switch'];
				$verify_badge_class = '';
				$__plan_id = listing_get_metabox_by_ID('Plan_id', get_the_ID());
				$__plan_mo = null;
				if (!empty($__plan_id)) {
					$__plan_post = get_post($__plan_id);
					if ($__plan_post) {
						$__plan_mo = intval($__plan_post->menu_order);
					}
				}
				$__show_verified = isset($listingpro_options['lp_verified_badge_pro']) ? $listingpro_options['lp_verified_badge_pro'] : true;
								$__show_verified_free = false;
												$lp_show_verified_badge_free = get_post_meta(get_the_ID(), 'lp_show_verified_badge_free', true);
												if (!empty($lp_show_verified_badge_free) && $lp_show_verified_badge_free === 'yes') {
													$__show_verified_free = true;
												}
								if ( ($__show_verified && $__plan_mo === 1) || $__show_verified_free ) {
									$verify_badge_class = ' verify-badge';
								}
					?>							
					<div class="<?php echo esc_attr($listing_style); ?> <?php echo esc_attr($adClass); ?> lp-grid-box-contianer grid_view6 grid_view_s5 card1 lp-grid-box-contianer1 listing-grid-view2-outer" data-title="<?php echo get_the_title(); ?>" data-postid="<?php echo get_the_ID(); ?>"   data-lattitue="<?php echo esc_attr($latitude); ?>" data-longitute="<?php echo esc_attr($longitude); ?>" data-posturl="<?php echo get_the_permalink(); ?>">
						<?php if(is_page_template('template-favourites.php')){ ?>
							<div class="remove-fav md-close" data-post-id="<?php echo get_the_ID(); ?>">
								<i class="fa fa-close"></i>
							</div>
						<?php } ?>
						<div class="lp-grid-box<?php echo esc_attr($verify_badge_class); ?>">
							<div class="lp-grid-box-thumb-container" >
								<div class="hide">
                                    <span class="gaddress"><?php echo esc_attr($gAddress); ?></span>
                                    <?php
                                    $cats = get_the_terms( get_the_ID(), 'listing-category' );
                                    if(!empty($cats)){
                                        foreach ( $cats as $cat ) {
                                            $category_image = listing_get_tax_meta($cat->term_id,'category','image');
                                            if (!empty($category_image)) {
												if( hasFontAwesomeIconClass($category_image) ){
													echo '<span class="cat-icon"><i class="icon icons-search-cat '.$category_image.'"></i></span>'; 
												}else{
													echo '<span class="cat-icon"><img class="icon icons8-Food" src="' . $category_image . '" alt="cat-icon"></span>';
												}
											}
                                        }
                                    }
                                    ?>
                                </div>
								<div class="lp-grid-box-thumb">
									<div class="show-img">
										<?php
											if ( has_post_thumbnail()) {
												$image = wp_get_attachment_image_src( get_post_thumbnail_id( get_the_ID()), 'listingpro-blog-grid2' );
													if(!empty($image[0])){
														echo "<a href='".get_the_permalink()."' >
																<img alt='image' src='" . $image[0] . "' />
															</a>";
													}elseif (!empty($default_feature_img)) {
                                                        echo '
                                                        <a href="' . get_the_permalink() . '" >
                                                           <img src="' . $default_feature_img . '" alt="image">
                                                        </a>';
                                                    }else {
														echo '
														<a href="'.get_the_permalink().'" >
															<img src="'.esc_html__('https://placehold.co/372x400', 'listingpro').'" alt="image">
														</a>';
													}	
											}elseif(!empty($default_feature_img)){
												echo '
												<a href="'.get_the_permalink().'" >
													<img src="'.$default_feature_img.'" alt="image">
												</a>';
											}else {
												echo '
												<a href="'.get_the_permalink().'" >
													<img src="'.esc_html__('https://placehold.co/372x400', 'listingpro').'" alt="image">
												</a>';
											}
										?>
									</div>
									<div class="hide-img listingpro-list-thumb">
										<?php
											if ( has_post_thumbnail()) {
												$image = wp_get_attachment_image_src( get_post_thumbnail_id( get_the_ID()), 'listingpro-blog-grid' );
													if(!empty($image[0])){
														echo "<a href='".get_the_permalink()."' >
																<img alt='image' src='" . $image[0] . "' />
															</a>";
													}else {
														echo '
														<a href="'.get_the_permalink().'" >
															<img src="'.esc_html__('https://placehold.co/372x240', 'listingpro').'" alt="image">
														</a>';
													}	
											}else {
												echo '
												<a href="'.get_the_permalink().'" >
													<img src="'.esc_html__('https://placehold.co/372x240', 'listingpro').'" alt="image">
												</a>';
											}
										?>
									</div>
									<?php
											$openStatus = listingpro_check_time(get_the_ID());
											if(!empty($openStatus)){
												echo '
												<div class="lp-grid6-status">
													<a class="status-btn">';

														echo wp_kses_post($openStatus);
														echo ' 
													</a>
												</div>';
											}
										?>
										
									 <?php
									
									$NumberRating = listingpro_ratings_numbers($post->ID);
									if( $NumberRating != 0 )
									{
										
										$rating = get_post_meta( get_the_ID(), 'listing_rate', true );
										$rating_num_bg  =   '';
										$rating_num_clr  =   '';

										if( $rating < 3 ){ $rating_num_bg  =   'num-level1'; $rating_num_clr  =   'level1'; }
										if( $rating < 4 ){ $rating_num_bg  =   'num-level2'; $rating_num_clr  =   'level2'; }
										if( $rating < 5 ){ $rating_num_bg  =   'num-level3'; $rating_num_clr  =   'level3'; }
										if( $rating >= 5 ){ $rating_num_bg  =   'num-level4'; $rating_num_clr  =   'level4'; }
										?>
										<div class="lp-grid6-rating">
											<i class="fa fa-star" aria-hidden="true"></i>
											<span class="lp-rating-num"><?php echo esc_attr($rating); ?></span>
										</div>
										<?php
									}

									?>	
									
							   	</div>
								<div class="lp-grid-box-quick">
									<ul class="lp-post-quick-links clearfix">
										<?php
											$favrt  =   listingpro_is_favourite_new(get_the_ID());
										 ?>
										<li class="pull-left">
											<a href="#" data-post-type="grids" data-post-id="<?php echo esc_attr(get_the_ID()); ?>" data-success-text="<?php echo esc_html__('Saved', 'listingpro') ?>" class="status-btn <?php if($favrt == 'yes'){echo 'remove-fav';}else{echo 'add-to-fav';} ?> lp-add-to-fav">
												<i class="<?php echo esc_attr($isfavouriteicon); ?>"></i> <span><?php echo wp_kses_post($isfavouritetext); ?></span>
											</a>
										</li>
										
									</ul>
								</div>
								<div class="lp-grid6-top-container">
									<div class="lp-grid6-top-container-inner">
										 <?php echo listingpro_price_dynesty($post->ID); ?>
										<h4 class="lp-h4">
											<a href="<?php echo get_the_permalink(); ?>">
												<?php echo wp_kses_post($CHeckAd); ?>
												<?php echo esc_html(get_the_title()); ?>
												<?php echo wp_kses_post($claim); ?>
												<?php echo ip_availability_badge_html( get_the_ID() ); ?>
											</a>
										</h4>	
										<div class="lp-listing-cats">

											<?php
											
											if( $tags ):

												$total_tags =   count($tags);

												$counter    =   1;
												$counterr    =   0;

												foreach ( $tags as $tag ):
													
													$tag_link   =   get_term_link( $tag );

													$tag_name  =   $tag->name;
													if( $counterr <= 2 ){			
													?>
														
													<a href="<?php echo esc_url($tag_link); ?>">

														<?php echo esc_attr($tag_name); ?><?php if($counter != $total_tags){ echo ',';}; ?>

													</a>
													<?php }?>
													<?php $counter++; $counterr++; endforeach; else: echo '<a href=""></a>'; endif; ?>



										</div>
                                        <div class="lp-listing-logo-outer">
									        <?php

										    if( $b_logo == true):
											$business_logo_url  =   '';
										    $author_avatar  =   listingpro_author_image();
											$b_logo_default =   $listingpro_options['business_logo_default']['url'];
											$business_logo = listing_get_metabox_by_ID('business_logo',get_the_ID());


											$image_thumb = '';
											global $wpdb;
											$attachment = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid='%s';", $business_logo ));
											if(!empty($attachment)){
												$image_thumb = wp_get_attachment_image_src($attachment[0], 'thumbnail');
											}
											if( empty( $business_logo ) )
											{
												$business_logo_url  =   $b_logo_default;
											}
											else
											{
                                                $business_logo_url  =   $business_logo;
											}
											if( !empty( $business_logo_url ) ){
                                                $business_logo_url  =   $business_logo_url;
											}else{
                                                $business_logo_url  =   $author_avatar;
											  } endif; ?>

                                             <div class="lp-listing-logo">

                                                 <img src="<?php echo esc_url($business_logo_url); ?>" alt="Listing Logo">

                                             </div>
                                         </div>
									</div>
									<div class="clearfix">
										<div class="pull-left lp-grid6-cate">
											<?php
													$cats = get_the_terms( get_the_ID(), 'listing-category' );
													if(!empty($cats)){
													$category_links = array();
													$icon_printed = false;
													foreach ( $cats as $cat ) {
														if ( ! ( $cat instanceof WP_Term ) ) {
															continue;
														}
														if ( ! $icon_printed ) {
															$category_image = listing_get_tax_meta($cat->term_id,'category','image');
															if (!empty($category_image)) {
																if( hasFontAwesomeIconClass($category_image) ){
																	echo '<span class="cat-icon"><i class="icon icons-search-cat '.$category_image.'"></i></span>'; 
																}else{
																	echo '<span class="cat-icon"><img class="icon icons8-Food" src="' . esc_url($category_image) . '" alt="cat-icon"></span>';
																}
															}
															$icon_printed = true;
														}
														$term_link = get_term_link( $cat );
														if ( ! is_wp_error( $term_link ) ) {
															$category_links[] = '<a href="' . esc_url( $term_link ) . '">' . esc_html( $cat->name ) . '</a>';
														}
													}
													echo implode( ', ', $category_links );
													}
												?>
										
										
										</div>
										<?php
									
										$cats = get_the_terms( get_the_ID(), 'location' );
										if(!empty($cats)){
										?>
											<div class="lp-grid-box-bottom-grid6">
												<div class="pull-right">
													<div class="show">
														<?php
															$cats = get_the_terms( get_the_ID(), 'location' );
															if(!empty($cats)){
																echo '<span class="cat-icon"><i class="fa fa-map-marker" aria-hidden="true"></i></span>';
																foreach ( $cats as $cat ) {
																	$term_link = get_term_link( $cat );
																	echo '
																	<a href="'.$term_link.'">
																		'.$cat->name.'
																	</a>';
																}
															}
															
														?>
													</div>
													
												</div>
												
												
											</div>
										
										<?php } ?>
									
									</div>
								</div>
							</div>
							 <?php
								if( ( !empty( $menu_check ) && is_array( $menu_check ) ) || ( !empty( $phone )  ) || ( !empty( $gAddress )  ) ):
									?>
									<div class="lp-new-grid-bottom-button">
										<ul class="clearfix">
											<?php
											if( $is_menu == 'ok' ):
												?>
												<li style="<?php //if( $is_phone == '' ){ echo 'width:50%;'; } ?>">
													<a href="<?php echo get_permalink( get_the_ID() ); ?>/#lp-listing-menuu-wrap"><i class="fa fa-cutlery" aria-hidden="true"></i> <?php esc_html_e('Menu','listingpro'); ?></a>
												</li>
											<?php endif; ?>
											
											<?php
											if( $is_gAddress == 'ok' ):
												?>
												<li style="<?php //if( $is_phone == '' ){ echo 'width:50%;'; } ?>">
													<a href="" data-lid="<?php echo get_the_ID(); ?>" data-lat="<?php echo esc_attr($lp_lat); ?>" data-lng="<?php echo esc_attr($lp_lng); ?>" class="show-loop-map-popup"><i class="fa fa-map-pin" aria-hidden="true"></i> <?php esc_html_e('Direction','listingpro'); ?></a>
												</li>
											<?php endif; ?>
											<?php
											if( $is_phone == 'ok' ):
											?>
												<li class="show-number-wrap" onclick="myFuction(this)" style="<?php //if( $is_menu == '' ){ echo 'width:100%;'; } ?>">
													<p><i class="fa fa-phone" aria-hidden="true"></i> <span class="show-number"><?php esc_html_e('call Now','listingpro'); ?></span><a href="tel:<?php echo esc_attr($phone); ?>" class="grind-number"><?php echo esc_attr($phone); ?></a></p>
												</li>
											<?php endif; ?>
											
										</ul>
									</div>
								<?php endif; ?>
						</div>
					</div>
					
					<?php get_template_part('templates/preview'); ?>
								 
				<?php 
					
					if($postGridCountnew%$postGridnumber == 0){
						echo '<div class="clearfix lp-archive-clearfix"></div>';
					}
?>						