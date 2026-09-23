<?php
/**
 * Plugin Name: Instructor Place — Traffic Light Availability
 * Description: Lets an instructor set their availability (Green/Amber/Red)
 * from their dashboard, shows it as a badge on their listing card and
 * profile, disables the contact form and booking widget when Red, and
 * auto-downgrades a stale (30+ day untouched) status to Amber.
 * Version: 1.3.0
 *
 * DESIGN NOTES — read before changing anything
 * ---------------------------------------------
 * STATUS DEFAULT: a listing that has never had its availability touched
 * shows Green (IP_AVAIL_DEFAULT_STATUS) rather than nothing. The
 * alternative — showing no badge until the instructor first sets one — was
 * rejected: a missing indicator reads as broken, not "unset", to a learner
 * browsing search results. The 30-day staleness cron does NOT touch a
 * listing that has never been set at all (there's no honest "last updated"
 * date to measure staleness against) — see ip_availability_run_daily_check().
 *
 * STALENESS APPLIES TO GREEN ONLY: 30 days with no update forces a Green
 * listing to Amber — it never touches an Amber (nothing further to decay)
 * or a deliberately-set Red. A stale Green is an unverified "yes, still
 * available" claim, worth walking back to "unconfirmed". A stale Red is a
 * deliberate "not available" the instructor chose; staleness shouldn't
 * quietly override that, since the failure mode of leaving it Red too long
 * (a learner skips someone who's actually free again) is far less costly
 * than the failure mode of un-reddening it automatically (a learner
 * contacts someone who's actually still unavailable).
 *
 * TIMESTAMP HANDLING ON AUTO-DECAY: when the cron forces a stale Green to
 * Amber, it does NOT reset '_ip_availability_updated' to now — that
 * timestamp is meant to record the last time the INSTRUCTOR actually
 * touched this, not the last time the cron touched it. Re-processing the
 * same listing every day forever is already prevented on its own once the
 * status is no longer Green (see the loop's own status check) —
 * '_ip_availability_auto_amber' isn't needed for that, but is still
 * recorded as a bookkeeping flag distinguishing an auto-decayed Amber from
 * one the instructor chose themselves. Any manual change by the instructor
 * clears that flag and resets the real timestamp.
 *
 * HARD BLOCK ON RED: disables (not hides) the contact form's submit button
 * and the booking widget's controls, with an inline message explaining why.
 * Implemented in JS rather than PHP because there are two different
 * booking mechanisms live in this codebase depending on configuration —
 * the ListingPro Bookings plugin's own widget (wrapped in
 * .classic-booking-appointment, see theme/listingpro/templates/
 * listing_detail6.php) and a separate third-party "Resurva" iframe booking
 * button (.make-reservation / .ifram-reservation, see
 * templates/single-list/listing-details-style6/content/title-bar.php).
 * Targeting both by their existing CSS selectors works regardless of which
 * one (if either) is actually active, without needing to hook into
 * ListingPro Bookings' own plugin code, which isn't in this repo. The
 * contact/lead form itself is targeted via its existing id, #contactOwner
 * (theme/listingpro/templates/single-list/listing-details-style6/sidebar/
 * leadform.php).
 *
 * WHERE THE DASHBOARD CONTROLS AND BADGES ACTUALLY LIVE:
 * - Dashboard "set availability" buttons: added to the existing per-listing
 *   dropdown menu in the child theme's own copy of
 *   templates/dashboard/listings.php (already overridden by the trial
 *   listings plugin for its own countdown fix — this adds to the same
 *   file rather than creating a second, conflicting override).
 * - Listing card badge on /find-instructor/: child-theme override of
 *   templates/loop/loop3.php. NOT listing-loop.php, despite that being
 *   the file find-instructor-ajax.php's own get_template_part() call
 *   names directly — listing-loop.php checks
 *   $listing_layout == 'grid_view_v3' (this site's actual configured
 *   Listing Layout option) and delegates to
 *   get_template_part('templates/loop/loop3') before ever reaching the
 *   card markup this plugin first patched there. That first attempt was a
 *   real miss, caught by comparing the exact card class string
 *   ("grid_view6 grid_view_s5 ... listing-grid-view2-outer") against the
 *   live page's actual HTML — it matches loop3.php's card div verbatim,
 *   not any of the five variants inside listing-loop.php.
 *   listing-loop.php's own child-theme override is left in place in case
 *   a different Listing Layout setting or another page ever routes
 *   through it, but it does nothing for this site's actual search
 *   results as currently configured.
 * - Profile page badge: child-theme override of the small
 *   content/title-bar.php partial, next to the existing "Claimed" badge.
 * - Homepage grid: that one is an Elementor Loop Grid template (lives in
 *   the database, not as a file) — see the separate [listing_availability]
 *   shortcode below, styled to match the site's own existing
 *   'listing_status' Elementor shortcode.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'IP_AVAIL_STALE_DAYS', 30 );
define( 'IP_AVAIL_META_STATUS', '_ip_availability_status' );
define( 'IP_AVAIL_META_UPDATED', '_ip_availability_updated' );
define( 'IP_AVAIL_META_AUTO', '_ip_availability_auto_amber' );
define( 'IP_AVAIL_DEFAULT_STATUS', 'green' );
define( 'IP_AVAIL_NONCE_ACTION', 'ip_availability_nonce' );

/* =====================================================================
 * 1. STATUS READ / WRITE
 * ===================================================================== */

/**
 * A listing's current availability status. Always one of green/amber/red —
 * falls back to IP_AVAIL_DEFAULT_STATUS if never set or the stored value
 * is somehow invalid.
 *
 * @param int $listing_id
 * @return string 'green'|'amber'|'red'
 */
function ip_availability_get_status( $listing_id ) {
	$status = get_post_meta( $listing_id, IP_AVAIL_META_STATUS, true );
	if ( ! in_array( $status, array( 'green', 'amber', 'red' ), true ) ) {
		return IP_AVAIL_DEFAULT_STATUS;
	}
	return $status;
}

/**
 * Set a listing's availability status.
 *
 * @param int    $listing_id
 * @param string $status          'green'|'amber'|'red'
 * @param bool   $is_auto         True when this is the staleness cron
 *                                 forcing a decay to Amber, not the
 *                                 instructor's own action.
 * @param bool   $touch_timestamp Whether to reset '_ip_availability_updated'
 *                                 to now. False for the cron's auto-decay,
 *                                 so that timestamp keeps meaning "the last
 *                                 time the instructor actually did this".
 * @return bool
 */
function ip_availability_set_status( $listing_id, $status, $is_auto = false, $touch_timestamp = true ) {
	if ( ! in_array( $status, array( 'green', 'amber', 'red' ), true ) ) {
		return false;
	}

	update_post_meta( $listing_id, IP_AVAIL_META_STATUS, $status );
	if ( $touch_timestamp ) {
		update_post_meta( $listing_id, IP_AVAIL_META_UPDATED, current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
	}
	update_post_meta( $listing_id, IP_AVAIL_META_AUTO, $is_auto ? 'yes' : 'no' );

	return true;
}

function ip_availability_label( $status ) {
	switch ( $status ) {
		case 'green':
			return esc_html__( 'Available', 'listingpro' );
		case 'amber':
			return esc_html__( 'Limited Slots', 'listingpro' );
		case 'red':
			return esc_html__( 'Not Available', 'listingpro' );
	}
	return '';
}

function ip_availability_color( $status ) {
	switch ( $status ) {
		case 'green':
			return '#2e7d32';
		case 'amber':
			return '#b26a00';
		case 'red':
			return '#c62828';
	}
	return '#666666';
}

function ip_availability_bg( $status ) {
	switch ( $status ) {
		case 'green':
			return '#e6f4ea';
		case 'amber':
			return '#fff4e0';
		case 'red':
			return '#fdeaea';
	}
	return '#f0f0f0';
}

/* =====================================================================
 * 2. AJAX — instructor sets their own listing's availability
 * ===================================================================== */

add_action( 'wp_ajax_ip_set_availability', 'ip_availability_ajax_set' );
/**
 * Logged-in only (wp_ajax_, no wp_ajax_nopriv_ counterpart) — setting
 * availability is something only an authenticated instructor (or an
 * admin) does for their own listing.
 */
function ip_availability_ajax_set() {
	check_ajax_referer( IP_AVAIL_NONCE_ACTION, 'nonce' );

	$listing_id = isset( $_POST['listing_id'] ) ? (int) $_POST['listing_id'] : 0;
	$status     = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';

	if ( ! $listing_id || ! in_array( $status, array( 'green', 'amber', 'red' ), true ) ) {
		wp_send_json_error( array( 'message' => esc_html__( 'Invalid request.', 'listingpro' ) ) );
	}

	$listing = get_post( $listing_id );
	if ( ! $listing || 'listing' !== $listing->post_type ) {
		wp_send_json_error( array( 'message' => esc_html__( 'Listing not found.', 'listingpro' ) ) );
	}

	// Only the listing's own author, or an admin, may change it.
	if ( (int) $listing->post_author !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => esc_html__( 'You do not have permission to do that.', 'listingpro' ) ) );
	}

	ip_availability_set_status( $listing_id, $status, false, true );

	wp_send_json_success( array(
		'status' => $status,
		'label'  => ip_availability_label( $status ),
	) );
}

/* =====================================================================
 * 3. DAILY CRON — 30 days untouched forces Amber
 * ===================================================================== */

add_action( 'wp', 'ip_availability_schedule_cron' );
function ip_availability_schedule_cron() {
	if ( ! wp_next_scheduled( 'ip_availability_daily_check' ) ) {
		wp_schedule_event( strtotime( '04:30:00' ), 'daily', 'ip_availability_daily_check' );
	}
}

add_action( 'ip_availability_daily_check', 'ip_availability_run_daily_check' );
function ip_availability_run_daily_check() {
	$listings = get_posts( array(
		'post_type'      => 'listing',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array(
			array(
				'key'     => IP_AVAIL_META_UPDATED,
				'compare' => 'EXISTS',
			),
		),
	) );

	if ( empty( $listings ) ) {
		return;
	}

	$now = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp

	foreach ( $listings as $listing_id ) {
		$updated = (int) get_post_meta( $listing_id, IP_AVAIL_META_UPDATED, true );
		if ( ! $updated ) {
			continue;
		}

		$days_since = floor( ( $now - $updated ) / DAY_IN_SECONDS );
		if ( $days_since < IP_AVAIL_STALE_DAYS ) {
			continue;
		}

		// Staleness only walks back an optimistic Green — it never touches
		// Amber (nothing to decay further) or Red (a deliberate "not
		// available" isn't something staleness should override; leaving it
		// stale is safer than quietly making a learner think a red listing
		// is now open again).
		if ( 'green' !== ip_availability_get_status( $listing_id ) ) {
			continue;
		}

		ip_availability_set_status( $listing_id, 'amber', true, false );
	}
}

/* =====================================================================
 * 4. BADGE — call from a template, or use the shortcode
 * ===================================================================== */

/**
 * @param int $listing_id
 * @return string
 */
function ip_availability_badge_html( $listing_id ) {
	$status = ip_availability_get_status( $listing_id );
	$label  = ip_availability_label( $status );
	$color  = ip_availability_color( $status );
	$bg     = ip_availability_bg( $status );

	return sprintf(
		'<span class="ip-availability-badge ip-availability-%1$s" style="display:inline-flex;align-items:center;gap:6px;background:%2$s;color:%3$s;border:1px solid %3$s;border-radius:20px;padding:3px 10px;font-size:12px;font-weight:600;white-space:nowrap;">'
			. '<span style="width:8px;height:8px;border-radius:50%%;background:%3$s;display:inline-block;flex:none;"></span>%4$s'
			. '</span>',
		esc_attr( $status ),
		esc_attr( $bg ),
		esc_attr( $color ),
		esc_html( $label )
	);
}

add_shortcode( 'ip_availability_badge', 'ip_availability_badge_shortcode' );
function ip_availability_badge_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'id' => get_the_ID() ), $atts );
	return ip_availability_badge_html( (int) $atts['id'] );
}

/* =====================================================================
 * 5. DASHBOARD CONTROLS — the three set-availability buttons + their JS
 * ===================================================================== */

/**
 * Returns the three-button availability control for one listing row in the
 * dashboard's "My Listings" table. Called from the child-theme override of
 * templates/dashboard/listings.php.
 *
 * @param int $listing_id
 * @return string
 */
/**
 * Matches the existing dropdown's own visual language (icon + text list
 * items, same as the Edit/Remove/Change Plan rows already in that menu)
 * rather than a cramped horizontal button row — the dropdown's width is
 * sized for short text links, and three side-by-side buttons clipped
 * against that width in the first version of this.
 *
 * @param int $listing_id
 * @return string
 */
function ip_availability_dashboard_controls_html( $listing_id ) {
	$current = ip_availability_get_status( $listing_id );
	$options = array(
		'green' => array(
			'label' => esc_html__( 'Available', 'listingpro' ),
			'color' => ip_availability_color( 'green' ),
		),
		'amber' => array(
			'label' => esc_html__( 'Limited Slots', 'listingpro' ),
			'color' => ip_availability_color( 'amber' ),
		),
		'red'   => array(
			'label' => esc_html__( 'Not Available', 'listingpro' ),
			'color' => ip_availability_color( 'red' ),
		),
	);

	$html  = '<li class="ip-availability-dashboard-heading" style="border-top:1px solid #eee;margin-top:6px;padding-top:6px;">';
	$html .= '<span style="display:block;padding:4px 12px 2px;font-size:10px;letter-spacing:0.05em;text-transform:uppercase;color:#999999;font-weight:700;">' . esc_html__( 'Availability', 'listingpro' ) . '</span>';
	$html .= '</li>';

	foreach ( $options as $status => $opt ) {
		$is_active   = ( $status === $current );
		$active_attr = $is_active ? ' aria-current="true"' : '';
		$label_html  = $is_active ? '<strong>' . $opt['label'] . '</strong>' : $opt['label'];
		$check_icon  = $is_active ? ' <i class="fa fa-check" aria-hidden="true"></i>' : '';

		$html .= sprintf(
			'<li><a href="#" class="ip-availability-btn" data-listing-id="%1$d" data-status="%2$s"%3$s><i class="fa fa-circle" aria-hidden="true" style="color:%4$s;"></i><span>%5$s</span>%6$s</a></li>',
			(int) $listing_id,
			esc_attr( $status ),
			$active_attr,
			esc_attr( $opt['color'] ),
			$label_html,
			$check_icon
		);
	}

	return $html;
}

add_action( 'wp_enqueue_scripts', 'ip_availability_enqueue_dashboard_js' );
/**
 * Only loaded on the dashboard page — detected via is_page_template()
 * against the theme's own confirmed template file
 * (theme/listingpro/template-dashboard.php), not by comparing URLs.
 *
 * An earlier version tried to match the current URL against
 * listingpro_url('listing-author') (the configured dashboard permalink).
 * That broke silently on staging: home_url( add_query_arg( null, null ) )
 * double-prefixed the path, because home_url() already returns
 * '.../staging/3959' there and add_query_arg(null, null) returns the raw
 * request URI, which *also* already contains '/staging/3959/...' — the
 * two concatenated never matched the real dashboard URL, so this function
 * always returned early, the script never loaded, and clicking a status
 * did nothing with no console error, since there was no handler bound at
 * all. is_page_template() sidesteps this entirely: it doesn't care what
 * URL or subdirectory the page is served from.
 */
function ip_availability_enqueue_dashboard_js() {
	if ( ! is_page_template( 'template-dashboard.php' ) ) {
		return;
	}

	wp_register_script( 'ip-availability-dashboard', false, array( 'jquery' ), '1.0', true );
	wp_enqueue_script( 'ip-availability-dashboard' );
	wp_localize_script( 'ip-availability-dashboard', 'ipAvailability', array(
		'ajaxurl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( IP_AVAIL_NONCE_ACTION ),
	) );

	wp_add_inline_script( 'ip-availability-dashboard', "
		(function ($) {
			$(document).on('click', '.ip-availability-btn', function (e) {
				e.preventDefault();
				var \$btn = $(this);
				$.ajax({
					url: ipAvailability.ajaxurl,
					type: 'POST',
					data: {
						action: 'ip_set_availability',
						nonce: ipAvailability.nonce,
						listing_id: \$btn.data('listing-id'),
						status: \$btn.data('status')
					},
					success: function (response) {
						if (response.success) {
							location.reload();
						} else {
							alert((response.data && response.data.message) ? response.data.message : 'Something went wrong.');
						}
					},
					error: function () {
						alert('Something went wrong. Please try again.');
					}
				});
			});
		})(jQuery);
	" );
}

/* =====================================================================
 * 6. HARD BLOCK — disable contact form + booking widget when Red
 * ===================================================================== */

add_action( 'wp_enqueue_scripts', 'ip_availability_enqueue_block_js' );
function ip_availability_enqueue_block_js() {
	if ( ! is_singular( 'listing' ) ) {
		return;
	}

	if ( 'red' !== ip_availability_get_status( get_the_ID() ) ) {
		return;
	}

	$message = esc_js( esc_html__( 'This instructor is not currently accepting new students.', 'listingpro' ) );

	wp_register_script( 'ip-availability-block', false, array( 'jquery' ), '1.0', true );
	wp_enqueue_script( 'ip-availability-block' );
	wp_add_inline_script( 'ip-availability-block', "
		(function (\$) {
			\$(function () {
				var msgHtml = '<p class=\"ip-availability-blocked-msg\" style=\"color:#c62828;font-weight:600;margin-top:10px;\">{$message}</p>';

				// Contact / lead form (theme/listingpro/templates/single-list/
				// listing-details-style6/sidebar/leadform.php, form#contactOwner).
				var \$contactForm = \$('#contactOwner');
				if (\$contactForm.length) {
					\$contactForm.find('input[type=\"submit\"], button[type=\"submit\"]')
						.prop('disabled', true)
						.css({ opacity: 0.5, cursor: 'not-allowed' });
					\$contactForm.append(msgHtml);
				}

				// ListingPro Bookings plugin widget, if that's what's active.
				var \$booking = \$('.classic-booking-appointment');
				if (\$booking.length) {
					\$booking.find('button, input[type=\"submit\"], a.btn, a.book-btn')
						.each(function () {
							\$(this).prop('disabled', true).css({ opacity: 0.5, pointerEvents: 'none', cursor: 'not-allowed' });
						});
					\$booking.prepend(msgHtml);
				}

				// Resurva iframe booking button, if that's what's active instead
				// (templates/single-list/listing-details-style6/content/title-bar.php).
				var \$resurva = \$('.make-reservation');
				if (\$resurva.length) {
					\$resurva.each(function () {
						\$(this).addClass('disabled').attr('aria-disabled', 'true')
							.css({ opacity: 0.5, pointerEvents: 'none', cursor: 'not-allowed' });
					});
					\$resurva.first().after(msgHtml);
				}
			});
		})(jQuery);
	" );
}

/* =====================================================================
 * 7. ELEMENTOR LOOP GRID SHORTCODE — [listing_availability]
 * ===================================================================== */

/**
 * A separate shortcode from [ip_availability_badge] above, deliberately.
 * That one matches the light-background admin/dashboard badge style used
 * on the classic single-listing template and admin column. This one
 * matches the solid-color pill style of the site's own existing
 * 'listing_status' shortcode (Open Now / Closed Now, added independently
 * of this plugin) — same markup shape, same font, same spacing token
 * (var(--kit-widget-spacing)) — so the two sit naturally side by side on
 * an Elementor Loop Grid card. Drop [listing_availability] into a
 * Shortcode widget in that Loop Grid item template, wherever it should
 * appear (e.g. next to the listing_status badge).
 */
add_shortcode( 'listing_availability', function ( $atts ) {
	$atts = shortcode_atts( array(
		'id' => get_the_ID(),
	), $atts );

	$post_id = absint( $atts['id'] );
	if ( ! $post_id ) {
		return '';
	}

	$status = ip_availability_get_status( $post_id );
	$label  = ip_availability_label( $status );
	$color  = ip_availability_color( $status );

	return
		'<span class="listing-availability listing-availability-' . esc_attr( $status ) . '"
			style="
				display:inline-flex;
				align-items:center;
				gap:6px;
				background-color:' . esc_attr( $color ) . ';
				margin:0 0 calc(var(--kit-widget-spacing, 0px) + 0px) 0;
				padding:7px 12px;
				z-index:1;
				border-radius:100px;
				font-family:\'Open Sans\', sans-serif;
				font-style:normal;
				font-weight:400;
				font-size:12px;
				line-height:16px;
				color:#FFFFFF;
			">
			<span
				class="listing-availability-dot"
				style="
					display:inline-block;
					width:7px;
					height:7px;
					min-width:7px;
					border-radius:50%;
					background-color:#FFFFFF;
				">
			</span>
			<span
				class="listing-availability-label"
				style="
					color:#FFFFFF;
					font-family:\'Open Sans\', sans-serif;
					font-style:normal;
					font-weight:400;
					font-size:12px;
					line-height:16px;
				">
				' . esc_html( $label ) . '
			</span>
		</span>';
} );
