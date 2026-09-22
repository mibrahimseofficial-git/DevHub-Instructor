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
 * Trial state lives in its own meta keys (prefixed _ip_trial_) and is
 * managed by its own daily cron, so a trial downgrades to a *visible* Free
 * listing instead of disappearing. Nothing here changes any global
 * ListingPro setting.
 *
 * ListingPro's own expiry system is deliberately kept out of the picture.
 * lp_expire_this_listing (theme functions.php) selects every listing whose
 * nested options array contains 'lp_purchase_days', then sets post_status to
 * 'expired' (unpublishes it) once that many days have passed, regardless of
 * any fallback plan. That's right for a real paid plan lapsing, wrong for a
 * trial. ListingPro's submit handler always writes that key — including for
 * free listings (submit-ajax.php) — so a trial has to actively remove it.
 * See ip_trial_purge_purchase_days(). The key is deleted rather than zeroed,
 * because the cron matches on the key merely existing.
 *
 * Two different meta keys carry a listing's plan, and both must be written
 * for a trial to behave like a real Premium purchase:
 *  - 'lp_listingpro_options'['Plan_id'] (nested) — read by ListingPro's
 *    expiry cron and its metabox helpers.
 *  - 'plan_id' (top-level) — read by the search-sort JOIN that ranks
 *    listings, in theme/listingpro/include/find-instructor-ajax.php. A
 *    trial that sets only the nested key still sorts as Free.
 * ip_trial_apply_plan() writes both.
 *
 * ListingPro's save_post handler (plugin/listingpro-plugin/functions.php)
 * unconditionally copies Plan_id and plan_time back from the edit screen, so
 * saving a trial listing in wp-admin reverted the plan and re-added
 * 'lp_purchase_days'. ip_trial_reassert_on_save() runs after it to undo that.
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
 * markup. The badge is NOT injected automatically — place the shortcode in
 * the single-listing template or an Elementor Shortcode widget.
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
 * 2. PLAN APPLICATION — write both plan keys, clear the expiry field
 * ===================================================================== */

/**
 * Point a listing at a plan, in both places ListingPro reads a plan from.
 *
 * The nested options key is what the metabox helpers and the expiry cron
 * read; the top-level key is what the search-sort JOIN reads. Writing only
 * the nested one leaves the listing ranked as though it were still on the
 * plan it was submitted with.
 *
 * @param int $listing_id
 * @param int $plan_id
 */
function ip_trial_apply_plan( $listing_id, $plan_id ) {
	listing_set_metabox( 'Plan_id', $plan_id, $listing_id );
	update_post_meta( $listing_id, 'plan_id', $plan_id );
}

/**
 * Remove 'lp_purchase_days' from a listing's nested options array.
 *
 * ListingPro's submit handler writes this for every listing it creates,
 * free ones included. Its daily cron selects any listing whose options
 * array contains the key — matching on the key existing, not on its value —
 * and unpublishes the listing once that many days have elapsed. Deleting the
 * key (rather than setting it to 0 or '') is what takes the listing out of
 * that query.
 *
 * @param int $listing_id
 */
function ip_trial_purge_purchase_days( $listing_id ) {
	$metabox = get_post_meta( $listing_id, 'lp_' . strtolower( THEMENAME ) . '_options', true );
	if ( ! is_array( $metabox ) || ! array_key_exists( 'lp_purchase_days', $metabox ) ) {
		return;
	}

	unset( $metabox['lp_purchase_days'] );
	update_post_meta( $listing_id, 'lp_' . strtolower( THEMENAME ) . '_options', $metabox );
}


/* =====================================================================
 * 3. GRANT THE TRIAL — on first publish, if no payment was made
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
	ip_trial_apply_plan( $listing_id, $premium_id );
	ip_trial_purge_purchase_days( $listing_id );

	$now = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
	update_post_meta( $listing_id, '_ip_trial_active', 'yes' );
	update_post_meta( $listing_id, '_ip_trial_start', $now );
	update_post_meta( $listing_id, '_ip_trial_reminder_sent', 'no' );
	update_post_meta( $listing_id, '_ip_trial_decided', 'trial_granted' );

	ip_trial_send_email( $listing_id, 'start' );
}

/* =====================================================================
 * 4. DAILY CHECK — reminder email, then downgrade at expiry
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
		ip_trial_apply_plan( $listing_id, $free_id );
	}
	ip_trial_purge_purchase_days( $listing_id );

	update_post_meta( $listing_id, '_ip_trial_active', 'no' );
	update_post_meta( $listing_id, '_ip_trial_ended', current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp

	ip_trial_send_email( $listing_id, 'ended' );
}

/* =====================================================================
 * 5. RE-ASSERT ON SAVE — stop ListingPro's metabox save reverting a trial
 * ===================================================================== */

add_action( 'save_post', 'ip_trial_reassert_on_save', 99, 3 );
/**
 * Re-apply the correct plan after any listing save.
 *
 * ListingPro's own save_post handlers read Plan_id, plan_time and the other
 * metabox fields straight from the edit screen and write them back, so merely
 * opening a trial listing in wp-admin and clicking Update silently reverted it
 * to whatever plan the form had selected — and re-added 'lp_purchase_days'
 * along with it. Both the free-submit path and admin approval publish run
 * through here.
 *
 * Hook choice matters. WordPress fires save_post_{$post_type} *before*
 * save_post (wp-includes/post.php, wp_insert_post), and ListingPro's
 * Plan_id writer (listingpro_update_features_in_list) is on plain save_post.
 * So hooking save_post_listing — even at priority 99 — would run *before*
 * the very handler it is meant to correct. Hook plain save_post at priority
 * 99 instead, which puts this after ListingPro's writers at priority 10.
 *
 * @param int     $post_id
 * @param WP_Post $post
 * @param bool    $update
 */
function ip_trial_reassert_on_save( $post_id, $post, $update ) {
	if ( ! $post || 'listing' !== $post->post_type ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	$decided = get_post_meta( $post_id, '_ip_trial_decided', true );

	if ( 'trial_granted' === $decided ) {
		$premium_id = ip_trial_get_premium_plan_id();
		if ( $premium_id ) {
			ip_trial_apply_plan( $post_id, $premium_id );
		}
		ip_trial_purge_purchase_days( $post_id );
		return;
	}

	if ( 'yes' === get_post_meta( $post_id, '_ip_trial_active', true ) ) {
		// Defensive: an active trial whose decided marker went missing. Re-assert
		// the plan but don't re-stamp a start time or re-send the email.
		$premium_id = ip_trial_get_premium_plan_id();
		if ( $premium_id ) {
			ip_trial_apply_plan( $post_id, $premium_id );
		}
		ip_trial_purge_purchase_days( $post_id );
		return;
	}

	if ( 'yes' === get_post_meta( $post_id, '_ip_trial_ended', true ) ) {
		// Trial finished: it must stay on Free, and must stay out of the expiry cron.
		ip_trial_purge_purchase_days( $post_id );
	}
}


/* =====================================================================
 * 6. COUNTDOWN BADGE — call from a template, or use the shortcode
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
 * 7. EMAILS
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

	// Resolve the dashboard the same way ListingPro's own templates do, so the
	// link keeps working if the slug changes or the site moves.
	$dashboard_url = function_exists( 'listingpro_url' ) ? listingpro_url( 'listing-author' ) : '';
	if ( empty( $dashboard_url ) ) {
		$dashboard_url = home_url( '/' );
	}

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
