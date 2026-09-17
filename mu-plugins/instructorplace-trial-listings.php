<?php
/**
 * Plugin Name: Instructor Place — Trial Listings System
 * Description: Gives a new instructor's first listing a free 30-day Premium
 * trial (if they submit without paying), shows a countdown badge, emails
 * them at trial start and 3 days before expiry, and auto-downgrades to the
 * Free plan when the trial ends.
 * Version: 1.0.0
 *
 * DESIGN NOTES — read before changing anything
 * ---------------------------------------------
 * This is deliberately independent of ListingPro's own plan-expiry system
 * (functions.php: lp_expire_this_listing / lp_daily_cron_listings). That
 * system is driven by the 'lp_purchase_days' sub-key inside the
 * 'lp_listingpro_options' meta array, and — critically — once it matches a
 * listing it sets post_status to 'expired' (unpublishes it) regardless of
 * whether a fallback plan is configured. That's correct for a real paid
 * plan lapsing, but wrong for a trial: the client wants a trial to fall
 * back to a *visible* Free listing, not disappear.
 *
 * So trial state lives entirely in its own meta keys (prefixed _ip_trial_)
 * and is managed by its own daily cron. 'lp_purchase_days' is never set on
 * a trial listing, so ListingPro's own cron never touches it. Nothing here
 * changes any global ListingPro setting.
 *
 * PLAN IDENTIFICATION
 * --------------------
 * Plans are resolved dynamically, never hardcoded, so this survives moving
 * between staging/production or the client reordering/renaming plans:
 * - "Premium" = the price_plan post with menu_order = 1 (this is the exact
 *   rule the site's own search-sort logic already uses to mean "top plan" —
 *   see theme/listingpro/include/find-instructor-ajax.php).
 * - "Free" = the price_plan post whose plan_price meta is empty/zero — this
 *   is the exact rule ListingPro's own submit handler uses to decide
 *   whether a plan requires payment (see
 *   plugin/listingpro-plugin/inc/submit-ajax.php).
 *
 * WHO GETS A TRIAL
 * -----------------
 * Only an author's very first published listing, and only if it was
 * submitted without payment (Plan_id is empty, 'none', or already the Free
 * plan). Anyone who pays for Standard or Premium at signup is never
 * touched — this mirrors a real purchase, so it's left alone.
 *
 * WHERE THE BADGE GOES
 * ----------------------
 * ip_trial_badge_html() and the [ip_trial_badge] shortcode return the
 * markup — call whichever from the actual single-listing template in use.
 * See AUDIT-TRIAL.md for the one line to add and where.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'IP_TRIAL_DAYS', 30 );
define( 'IP_TRIAL_REMINDER_DAYS_BEFORE', 3 ); // Send the "ending soon" email this many days before expiry.

/* =====================================================================
 * 1. PLAN RESOLUTION — dynamic, cached per-request
 * ===================================================================== */

/**
 * The Premium price_plan post ID — the plan with menu_order = 1.
 * This is the same rule the site's own search sorting already relies on.
 *
 * @return int 0 if not found.
 */
function ip_trial_get_premium_plan_id() {
	static $cached = null;
	if ( null !== $cached ) {
		return $cached;
	}

	global $wpdb;
	$id = $wpdb->get_var(
		"SELECT ID FROM {$wpdb->posts}
		 WHERE post_type = 'price_plan' AND post_status = 'publish' AND menu_order = 1
		 ORDER BY ID ASC LIMIT 1"
	);

	$cached = $id ? (int) $id : 0;
	return $cached;
}

/**
 * The Free price_plan post ID — the plan whose plan_price meta is empty or zero.
 * Mirrors ListingPro's own definition of "free" in submit-ajax.php.
 *
 * @return int 0 if not found.
 */
function ip_trial_get_free_plan_id() {
	static $cached = null;
	if ( null !== $cached ) {
		return $cached;
	}

	$plans = get_posts( array(
		'post_type'      => 'price_plan',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'menu_order',
		'order'          => 'DESC', // Free is conventionally the lowest-priority plan; checking from the bottom finds it faster.
		'fields'         => 'ids',
	) );

	$free_id = 0;
	foreach ( $plans as $plan_id ) {
		$price = get_post_meta( $plan_id, 'plan_price', true );
		if ( '' === $price || null === $price || 0 == $price ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			$free_id = (int) $plan_id;
			break;
		}
	}

	$cached = $free_id;
	return $cached;
}

/* =====================================================================
 * 2. GRANT THE TRIAL — on first publish, if no payment was made
 * ===================================================================== */

add_action( 'transition_post_status', 'ip_trial_maybe_grant_on_publish', 10, 3 );
/**
 * Fires on every status transition for every post type; we filter down to
 * "a listing just became published for the first time".
 *
 * transition_post_status (rather than a submit-ajax hook) is used
 * deliberately: it fires no matter which code path published the listing —
 * the free-submit AJAX handler, a payment gateway callback, or an admin
 * manually approving a pending listing — so this doesn't need to be
 * touched if the submission flow itself ever changes.
 *
 * @param string  $new_status
 * @param string  $old_status
 * @param WP_Post $post
 */
function ip_trial_maybe_grant_on_publish( $new_status, $old_status, $post ) {
	if ( 'listing' !== $post->post_type ) {
		return;
	}
	if ( 'publish' === $old_status || 'publish' !== $new_status ) {
		return; // Only the moment it first becomes published.
	}

	$listing_id = $post->ID;

	// Already decided (trial granted or explicitly skipped) — never re-run.
	if ( get_post_meta( $listing_id, '_ip_trial_decided', true ) ) {
		return;
	}

	// Only an author's very first published listing counts as "a new instructor signing up".
	$existing = get_posts( array(
		'post_type'      => 'listing',
		'post_status'    => 'publish',
		'author'         => $post->post_author,
		'posts_per_page' => 1,
		'exclude'        => array( $listing_id ),
		'fields'         => 'ids',
	) );
	if ( ! empty( $existing ) ) {
		update_post_meta( $listing_id, '_ip_trial_decided', 'not_first_listing' );
		return;
	}

	// Only grant if they did NOT pay: Plan_id is empty/'none', or already the Free plan.
	$plan_id  = listing_get_metabox_by_ID( 'Plan_id', $listing_id );
	$free_id  = ip_trial_get_free_plan_id();
	$paid_for = ( ! empty( $plan_id ) && 'none' !== $plan_id && (int) $plan_id !== $free_id );

	if ( $paid_for ) {
		update_post_meta( $listing_id, '_ip_trial_decided', 'paid_at_signup' );
		return;
	}

	$premium_id = ip_trial_get_premium_plan_id();
	if ( ! $premium_id ) {
		// Premium plan couldn't be resolved — fail safe, don't grant a trial we can't identify correctly.
		update_post_meta( $listing_id, '_ip_trial_decided', 'no_premium_plan_found' );
		return;
	}

	// Grant the trial.
	listing_set_metabox( 'Plan_id', $premium_id, $listing_id );

	$now = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
	update_post_meta( $listing_id, '_ip_trial_active', 'yes' );
	update_post_meta( $listing_id, '_ip_trial_start', $now );
	update_post_meta( $listing_id, '_ip_trial_reminder_sent', 'no' );
	update_post_meta( $listing_id, '_ip_trial_decided', 'trial_granted' );

	ip_trial_send_email( $listing_id, 'start' );
}

/* =====================================================================
 * 3. DAILY CHECK — reminder email, then downgrade at expiry
 * ===================================================================== */

add_action( 'wp', 'ip_trial_schedule_cron' );
function ip_trial_schedule_cron() {
	if ( ! wp_next_scheduled( 'ip_trial_daily_check' ) ) {
		wp_schedule_event( strtotime( '04:00:00' ), 'daily', 'ip_trial_daily_check' );
	}
}

add_action( 'ip_trial_daily_check', 'ip_trial_run_daily_check' );
function ip_trial_run_daily_check() {
	$trials = get_posts( array(
		'post_type'      => 'listing',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array(
			array(
				'key'   => '_ip_trial_active',
				'value' => 'yes',
			),
		),
	) );

	if ( empty( $trials ) ) {
		return;
	}

	$now = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp

	foreach ( $trials as $listing_id ) {
		$start = (int) get_post_meta( $listing_id, '_ip_trial_start', true );
		if ( ! $start ) {
			continue;
		}

		$days_elapsed   = floor( ( $now - $start ) / DAY_IN_SECONDS );
		$days_remaining = IP_TRIAL_DAYS - $days_elapsed;

		if ( $days_remaining <= 0 ) {
			ip_trial_downgrade( $listing_id );
			continue;
		}

		$reminder_sent = get_post_meta( $listing_id, '_ip_trial_reminder_sent', true );
		if ( 'yes' !== $reminder_sent && $days_remaining <= IP_TRIAL_REMINDER_DAYS_BEFORE ) {
			ip_trial_send_email( $listing_id, 'reminder', $days_remaining );
			update_post_meta( $listing_id, '_ip_trial_reminder_sent', 'yes' );
		}
	}
}

/**
 * Downgrade a listing from its Premium trial to the Free plan.
 *
 * @param int $listing_id
 */
function ip_trial_downgrade( $listing_id ) {
	$free_id = ip_trial_get_free_plan_id();
	if ( $free_id ) {
		listing_set_metabox( 'Plan_id', $free_id, $listing_id );
	}

	update_post_meta( $listing_id, '_ip_trial_active', 'no' );
	update_post_meta( $listing_id, '_ip_trial_ended', current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp

	ip_trial_send_email( $listing_id, 'ended' );
}

/* =====================================================================
 * 4. COUNTDOWN BADGE — call from a template, or use the shortcode
 * ===================================================================== */

/**
 * Returns the trial countdown badge HTML for a listing, or '' if it isn't
 * currently on trial. Call this directly from the single-listing template
 * wherever the badge should appear, e.g.:
 *
 *     <?php echo ip_trial_badge_html( get_the_ID() ); ?>
 *
 * @param int $listing_id
 * @return string
 */
function ip_trial_badge_html( $listing_id ) {
	if ( 'yes' !== get_post_meta( $listing_id, '_ip_trial_active', true ) ) {
		return '';
	}

	$start = (int) get_post_meta( $listing_id, '_ip_trial_start', true );
	if ( ! $start ) {
		return '';
	}

	$now            = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
	$days_elapsed   = floor( ( $now - $start ) / DAY_IN_SECONDS );
	$days_remaining = max( 0, IP_TRIAL_DAYS - $days_elapsed );

	if ( $days_remaining <= 0 ) {
		return ''; // Cron hasn't run yet today but the trial's effectively over — don't show a stale badge.
	}

	$label = ( 1 === (int) $days_remaining )
		? esc_html__( '1 day left on free trial', 'listingpro-child' )
		/* translators: %d: number of days remaining in the free trial. */
		: sprintf( esc_html__( '%d days left on free trial', 'listingpro-child' ), $days_remaining );

	return '<span class="ip-trial-badge" style="display:inline-flex;align-items:center;gap:6px;background:#fff3cd;color:#7a5b00;border:1px solid #ffe08a;border-radius:20px;padding:4px 12px;font-size:13px;font-weight:600;white-space:nowrap;">'
		. '<i class="fa fa-clock-o" aria-hidden="true"></i> ' . $label
		. '</span>';
}

add_shortcode( 'ip_trial_badge', 'ip_trial_badge_shortcode' );
function ip_trial_badge_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'id' => get_the_ID() ), $atts );
	return ip_trial_badge_html( (int) $atts['id'] );
}

/* =====================================================================
 * 5. EMAILS
 * ===================================================================== */

/**
 * Send a trial-related email to the listing's author.
 *
 * @param int    $listing_id
 * @param string $type            'start' | 'reminder' | 'ended'
 * @param int    $days_remaining  Only used for 'reminder'.
 */
function ip_trial_send_email( $listing_id, $type, $days_remaining = 0 ) {
	$author_id = get_post_field( 'post_author', $listing_id );
	$user      = get_userdata( $author_id );
	if ( ! $user || ! is_email( $user->user_email ) ) {
		return;
	}

	$site_name     = get_bloginfo( 'name' );
	$listing_title = get_the_title( $listing_id );
	$listing_url   = get_permalink( $listing_id );
	$dashboard_url = home_url( '/dashboard/' ); // Adjust if the instructor dashboard lives at a different slug.

	switch ( $type ) {
		case 'start':
			$subject = sprintf( '[%s] Your free %d-day Premium trial has started', $site_name, IP_TRIAL_DAYS );
			$body    = "<p>Hi {$user->display_name},</p>"
				. "<p>Welcome to {$site_name}! Your listing <strong>{$listing_title}</strong> is now live with a free {IP_TRIAL_DAYS}-day Premium trial — that means top placement in search results and your full profile unlocked (contact details, gallery, map, and more).</p>"
				. "<p><a href=\"{$listing_url}\">View your listing</a></p>"
				. "<p>If you'd like to keep Premium visibility after the trial, you can upgrade any time from your <a href=\"{$dashboard_url}\">dashboard</a>. Otherwise your listing will automatically switch to a free Basic listing when the trial ends — it stays live either way.</p>"
				. "<p>Thanks,<br>{$site_name}</p>";
			$body    = str_replace( '{IP_TRIAL_DAYS}', IP_TRIAL_DAYS, $body );
			break;

		case 'reminder':
			$day_word = ( 1 === (int) $days_remaining ) ? 'day' : 'days';
			$subject  = sprintf( '[%s] Your Premium trial ends in %d %s', $site_name, $days_remaining, $day_word );
			$body     = "<p>Hi {$user->display_name},</p>"
				. "<p>Just a heads up — your free Premium trial for <strong>{$listing_title}</strong> ends in <strong>{$days_remaining} {$day_word}</strong>.</p>"
				. "<p>After that, your listing switches to a free Basic listing: it stays live, but loses top placement and some profile features.</p>"
				. "<p>To keep Premium visibility, upgrade from your <a href=\"{$dashboard_url}\">dashboard</a> before the trial ends.</p>"
				. "<p>Thanks,<br>{$site_name}</p>";
			break;

		case 'ended':
			$subject = sprintf( '[%s] Your Premium trial has ended', $site_name );
			$body    = "<p>Hi {$user->display_name},</p>"
				. "<p>Your free Premium trial for <strong>{$listing_title}</strong> has ended, and your listing is now on the free Basic plan. Your listing is still live and searchable.</p>"
				. "<p>You can upgrade to Premium or Standard at any time from your <a href=\"{$dashboard_url}\">dashboard</a> to restore top placement and full profile features.</p>"
				. "<p><a href=\"{$listing_url}\">View your listing</a></p>"
				. "<p>Thanks,<br>{$site_name}</p>";
			break;

		default:
			return;
	}

	wp_mail( $user->user_email, $subject, $body );
}
