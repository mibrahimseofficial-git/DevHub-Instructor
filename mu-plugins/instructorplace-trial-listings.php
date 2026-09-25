<?php
/**
 * Plugin Name: Instructor Place — Trial Listings System
 * Description: Gives a new instructor's first listing a free 30-day Premium
 * trial (if they submit without paying), shows a countdown badge, emails
 * them at trial start and 3 days before expiry, and downgrades to the Free
 * plan when the trial ends. That Free listing then runs for another 30
 * days before it expires the same way any ListingPro listing expires. The
 * real countdown shows in the admin "Expire After" column and the
 * front-end dashboard (via a small child-theme template override —
 * see child-theme/templates/dashboard/listings.php in this repo).
 * Version: 1.5.0
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
 * Two conditions, both required:
 *  1. Once per user, ever. Tracked on the user account in user meta
 *     '_ip_trial_used' (IP_TRIAL_USER_META). This is the rule that closes
 *     the old hole: eligibility used to be inferred from listings alone,
 *     checked with post_status='publish', and since ListingPro sets a lapsed
 *     listing to 'expired' and new submissions sit at 'pending', neither
 *     matched — so a user could collect a fresh trial by letting listings
 *     lapse or by submitting a second one. A user-level flag is immune to
 *     all of that. It is written when the trial is granted and never
 *     cleared, so the user's one trial is spent for good.
 *  2. On the author's first published listing, excluding the listing being
 *     published now. Anything else of theirs already live means this is a
 *     later listing.
 *
 * Both are checked before anything is written. An earlier version of rule 1
 * replaced rule 2 outright, which removed the "first listing" intent and let
 * a user with zero published listings collect a trial on a second
 * submission while the first was still awaiting approval.
 *
 * A user with no published listing of their own therefore always gets the
 * trial, however many attempts or pending drafts precede it. That is the
 * intended meaning of "first published listing" and also matches the
 * admin-approval flow, where a pending listing sits unpublished until an
 * admin approves it.
 *
 * Only if the listing was submitted without payment (Plan_id is empty,
 * 'none', or already the Free plan). Anyone who pays for Standard or Premium
 * at signup is never touched — this mirrors a real purchase, so it's left
 * alone, and paying does not consume the trial.
 *
 * WHERE THE BADGE GOES
 * ----------------------
 * ip_trial_badge_html() and the [ip_trial_badge] shortcode return the
 * markup. The badge is NOT injected automatically — place the shortcode in
 * the single-listing template or an Elementor Shortcode widget.
 *
 * PHASE 2 — THE FREE LISTING ALSO EXPIRES
 * -----------------------------------------
 * The downgrade to Free isn't the end of the lifecycle. That Free listing
 * lives for IP_TRIAL_FREE_GRACE_DAYS more days (a reminder email
 * IP_TRIAL_FREE_REMINDER_DAYS_BEFORE days out), then expires — exactly the
 * way any other ListingPro listing expires: post_status becomes the
 * theme's own custom 'expired' status (registered in
 * plugin/listingpro-plugin/inc/register_new_status.php). Every ListingPro
 * search/archive query already filters on post_status = 'publish', so
 * setting this one status is enough to drop it out of search — no separate
 * visibility flag needed, and it plugs into whatever admin UI (status
 * filter, "Expired" label) ListingPro already has for that status.
 *
 * Scoped to trial-descended listings only, via the '_ip_trial_ended' meta
 * key — the only thing that ever writes it is ip_trial_downgrade() above,
 * so a listing that was always Free/Standard/Premium, or a Free listing
 * that never went through a trial, has no such meta and this never touches
 * it. See ip_trial_run_free_expiry_check() and ip_trial_expire_free_listing().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'IP_TRIAL_DAYS', 30 );
define( 'IP_TRIAL_REMINDER_DAYS_BEFORE', 7 ); // Send the "ending soon" email this many days before expiry.
define( 'IP_TRIAL_USER_META', '_ip_trial_used' ); // User meta: set once the user has ever been granted a trial.
define( 'IP_TRIAL_FREE_GRACE_DAYS', 30 ); // How long a downgraded Free listing stays live before it expires (Phase 2).
define( 'IP_TRIAL_FREE_REMINDER_DAYS_BEFORE', 3 ); // Send the "listing expiring soon" email this many days before that.

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
 * Days left in the Premium trial itself (Phase 1), or null if the listing
 * was never granted one. Single source of truth shared by the badge, the
 * cron, and the admin "Expire After" column override, so the three can
 * never drift out of sync with each other.
 *
 * @param int $listing_id
 * @return int|null
 */
function ip_trial_days_remaining_in_trial( $listing_id ) {
	$start = (int) get_post_meta( $listing_id, '_ip_trial_start', true );
	if ( ! $start ) {
		return null;
	}
	$now          = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
	$days_elapsed = floor( ( $now - $start ) / DAY_IN_SECONDS );
	// '_ip_trial_extension_days' is added to by the admin panel's "Extend"
	// action. Kept as a separate, cumulative value rather than moving
	// '_ip_trial_start' itself, so the real start date stays an honest
	// record and every extension is independently auditable.
	$extension    = (int) get_post_meta( $listing_id, '_ip_trial_extension_days', true );
	$total_days   = IP_TRIAL_DAYS + $extension;
	return max( 0, $total_days - $days_elapsed );
}

/**
 * Days left in the Free grace period (Phase 2), or null if this listing
 * hasn't downgraded from a trial. Same sharing rationale as above.
 *
 * @param int $listing_id
 * @return int|null
 */
function ip_trial_days_remaining_in_free_grace( $listing_id ) {
	$ended = (int) get_post_meta( $listing_id, '_ip_trial_ended', true );
	if ( ! $ended ) {
		return null;
	}
	$now          = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
	$days_elapsed = floor( ( $now - $ended ) / DAY_IN_SECONDS );
	$extension    = (int) get_post_meta( $listing_id, '_ip_trial_free_extension_days', true );
	$total_days   = IP_TRIAL_FREE_GRACE_DAYS + $extension;
	return max( 0, $total_days - $days_elapsed );
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
 * 3. GRANT THE TRIAL — once per user, on first publish, if no payment was made
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

	// A trial is once per user, ever. Keyed on the account rather than on
	// listings, so it can't be re-earned by letting a listing lapse, by
	// deleting the trial listing, or by submitting another one.
	if ( get_user_meta( $post->post_author, IP_TRIAL_USER_META, true ) ) {
		update_post_meta( $listing_id, '_ip_trial_decided', 'trial_already_used' );
		return;
	}

	// ...and only on the author's first *published* listing. Both rules apply:
	// a user gets one trial, on the first listing of theirs that goes live.
	// Current listing excluded; anything else already published means this is
	// a later listing.
	$already_published = get_posts( array(
		'post_type'      => 'listing',
		'post_status'    => 'publish',
		'author'         => $post->post_author,
		'posts_per_page' => 1,
		'post__not_in'   => array( $listing_id ),
		'fields'         => 'ids',
	) );
	if ( ! empty( $already_published ) ) {
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

	// Claim the user-level flag, marking the trial as spent. Done here rather
	// than at downgrade so that a user with a running trial can't start a
	// second one; done last so a request that dies part-way leaves the
	// listing marked granted rather than burning the user's one trial with
	// nothing to show for it.
	update_user_meta( $post->post_author, IP_TRIAL_USER_META, $listing_id );

	ip_trial_send_email( $listing_id, 'start' );
}


/* =====================================================================
 * 4. ONE-TIME BACKFILL — seed the user flag from existing trials
 * ===================================================================== */

add_action( 'admin_init', 'ip_trial_backfill_user_flags' );
/**
 * Runs once. Any listing granted a trial before the flag existed would
 * otherwise leave its author eligible for a second one, since the old code
 * tracked eligibility on listings, not users.
 */
function ip_trial_backfill_user_flags() {
	if ( get_option( 'ip_trial_user_flags_backfilled' ) ) {
		return;
	}

	$granted = get_posts( array(
		'post_type'      => 'listing',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array(
			array(
				'key'   => '_ip_trial_decided',
				'value' => 'trial_granted',
			),
		),
	) );

	foreach ( $granted as $listing_id ) {
		$author_id = (int) get_post_field( 'post_author', $listing_id );
		if ( $author_id && ! get_user_meta( $author_id, IP_TRIAL_USER_META, true ) ) {
			update_user_meta( $author_id, IP_TRIAL_USER_META, $listing_id );
		}
	}

	update_option( 'ip_trial_user_flags_backfilled', 1 );
}

/* =====================================================================
 * 5. DAILY CHECK — reminder email, then downgrade at expiry
 * ===================================================================== */

add_action( 'wp', 'ip_trial_schedule_cron' );
function ip_trial_schedule_cron() {
	if ( ! wp_next_scheduled( 'ip_trial_daily_check' ) ) {
		wp_schedule_event( strtotime( '04:00:00' ), 'daily', 'ip_trial_daily_check' );
	}
}

add_action( 'ip_trial_daily_check', 'ip_trial_run_daily_check' );
add_action( 'ip_trial_daily_check', 'ip_trial_run_free_expiry_check' );
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

	foreach ( $trials as $listing_id ) {
		$days_remaining = ip_trial_days_remaining_in_trial( $listing_id );
		if ( null === $days_remaining ) {
			continue;
		}

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

/**
 * Phase 2: the Free listing a trial downgraded to has its own
 * IP_TRIAL_FREE_GRACE_DAYS lifespan. Runs on the same daily cron as the
 * trial check above. Scoped entirely by '_ip_trial_ended' existing — the
 * only writer of that key is ip_trial_downgrade(), so this can never touch
 * a listing that didn't come through a trial.
 */
function ip_trial_run_free_expiry_check() {
	$downgraded = get_posts( array(
		'post_type'      => 'listing',
		'post_status'    => 'publish', // Already-expired listings aren't 'publish' any more, so this naturally excludes them too.
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array(
			array(
				'key'     => '_ip_trial_ended',
				'compare' => 'EXISTS',
			),
		),
	) );

	if ( empty( $downgraded ) ) {
		return;
	}

	foreach ( $downgraded as $listing_id ) {
		$days_remaining = ip_trial_days_remaining_in_free_grace( $listing_id );
		if ( null === $days_remaining ) {
			continue;
		}

		if ( $days_remaining <= 0 ) {
			ip_trial_expire_free_listing( $listing_id );
			continue;
		}

		$reminder_sent = get_post_meta( $listing_id, '_ip_trial_free_reminder_sent', true );
		if ( 'yes' !== $reminder_sent && $days_remaining <= IP_TRIAL_FREE_REMINDER_DAYS_BEFORE ) {
			ip_trial_send_email( $listing_id, 'free_reminder', $days_remaining );
			update_post_meta( $listing_id, '_ip_trial_free_reminder_sent', 'yes' );
		}
	}
}

/**
 * Expire a downgraded-to-Free listing the same way ListingPro expires any
 * listing: post_status -> its own custom 'expired' status. Every
 * ListingPro search/archive query filters on post_status = 'publish', so
 * this alone drops it out of search — no separate visibility flag, and it
 * plugs into whatever admin UI ListingPro already has for that status.
 *
 * @param int $listing_id
 */
function ip_trial_expire_free_listing( $listing_id ) {
	wp_update_post( array(
		'ID'          => $listing_id,
		'post_status' => 'expired',
	) );

	update_post_meta( $listing_id, '_ip_trial_free_expired', current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp

	ip_trial_send_email( $listing_id, 'free_expired' );
}

/* =====================================================================
 * 6. RE-ASSERT ON SAVE — stop ListingPro's metabox save reverting a trial
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

	// '_ip_trial_ended' is a timestamp (set by ip_trial_downgrade()), never
	// the string 'yes' — check for presence, not a specific value.
	if ( get_post_meta( $post_id, '_ip_trial_ended', true ) ) {
		// Trial finished: it must stay on Free, and must stay out of the expiry cron.
		ip_trial_purge_purchase_days( $post_id );
	}
}


/* =====================================================================
 * 7. COUNTDOWN BADGE — call from a template, or use the shortcode
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

	$days_remaining = ip_trial_days_remaining_in_trial( $listing_id );
	if ( null === $days_remaining || $days_remaining <= 0 ) {
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
 * 8. EMAILS
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

		case 'free_reminder':
			$day_word = ( 1 === (int) $days_remaining ) ? 'day' : 'days';
			$subject  = sprintf( '[%s] Your free listing expires in %d %s', $site_name, $days_remaining, $day_word );
			$body     = "<p>Hi {$user->display_name},</p>"
				. "<p>Your listing <strong>{$listing_title}</strong> is currently live on our free Basic plan, and that listing period ends in <strong>{$days_remaining} {$day_word}</strong>.</p>"
				. "<p>After that, your listing will stop appearing in search until you renew or upgrade it from your <a href=\"{$dashboard_url}\">dashboard</a>.</p>"
				. "<p><a href=\"{$listing_url}\">View your listing</a></p>"
				. "<p>Thanks,<br>{$site_name}</p>";
			break;

		case 'free_expired':
			$subject = sprintf( '[%s] Your listing has expired', $site_name );
			$body    = "<p>Hi {$user->display_name},</p>"
				. "<p>Your listing <strong>{$listing_title}</strong> has expired and is no longer visible in search results.</p>"
				. "<p>You can renew or upgrade it any time from your <a href=\"{$dashboard_url}\">dashboard</a>.</p>"
				. "<p>Thanks,<br>{$site_name}</p>";
			break;

		default:
			return;
	}

	wp_mail( $user->user_email, $subject, $body );
}

/* =====================================================================
 * 9. ADMIN "EXPIRE AFTER" COLUMN — show the real countdown, not "Unlimited"
 * ===================================================================== */

/**
 * ListingPro's own admin list column (plugin/functions.php,
 * listingpro_columns_content(), column 'expires') reads the nested
 * 'lp_purchase_days' meta to calculate its countdown, and prints
 * "Unlimited Days Left" whenever that key is absent. This plugin
 * deliberately deletes that key on every trial-related listing (see
 * ip_trial_purge_purchase_days()) so ListingPro's own expiry cron never
 * unpublishes it — the side effect is that this admin column always shows
 * "Unlimited" for any listing this plugin manages, even though a real
 * countdown is running.
 *
 * Fixed here by wrapping ListingPro's own column output in an output
 * buffer and substituting an accurate value for trial-related listings
 * only. Every other listing's cell — including real paid ones — passes
 * through completely untouched, byte for byte, because the override
 * function below returns null for anything that isn't trial-related, and
 * a null result means "print what ListingPro would have printed".
 *
 * Two hooks on the same action, not a filter, because
 * manage_listing_posts_custom_column is action-based: ListingPro's
 * handler echoes directly rather than returning a value, so there's
 * nothing to filter. Priority 5 opens the buffer before ListingPro's own
 * handler runs at its default priority 10; priority 20 closes it after.
 */
add_action( 'manage_listing_posts_custom_column', 'ip_trial_expires_column_buffer_open', 5, 2 );
function ip_trial_expires_column_buffer_open( $column_name, $post_id ) {
	if ( 'expires' === $column_name ) {
		ob_start();
	}
}

add_action( 'manage_listing_posts_custom_column', 'ip_trial_expires_column_buffer_close', 20, 2 );
function ip_trial_expires_column_buffer_close( $column_name, $post_id ) {
	if ( 'expires' !== $column_name ) {
		return;
	}

	$listingpro_output = ob_get_clean();

	$override = ip_trial_expires_column_override( $post_id );
	if ( null === $override ) {
		echo $listingpro_output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- passthrough of ListingPro's own already-escaped output, unchanged.
		return;
	}

	echo $override; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped in ip_trial_expires_column_override().
}

/**
 * The replacement text for a trial-related listing's "Expire After" cell,
 * or null to leave ListingPro's own output alone.
 *
 * @param int $post_id
 * @return string|null
 */
function ip_trial_expires_column_override( $post_id ) {
	if ( 'yes' === get_post_meta( $post_id, '_ip_trial_active', true ) ) {
		$days = ip_trial_days_remaining_in_trial( $post_id );
		if ( null === $days ) {
			return null;
		}
		return esc_html( $days ) . esc_html__( ' Days Left (Premium Trial)', 'listingpro-plugin' );
	}

	// Downgraded to Free and still live — Phase 2's own countdown.
	if ( get_post_meta( $post_id, '_ip_trial_ended', true ) && 'publish' === get_post_status( $post_id ) ) {
		$days = ip_trial_days_remaining_in_free_grace( $post_id );
		if ( null === $days ) {
			return null;
		}
		return esc_html( $days ) . esc_html__( ' Days Left (Free Listing)', 'listingpro-plugin' );
	}

	// Not a trial-related listing, or already expired — let ListingPro's own
	// output stand (blank for expired, matching how it treats any other
	// expired listing).
	return null;
}

/* =====================================================================
 * 10. FRONT-END DASHBOARD "EXPIRE AFTER" — same fix, different rendering path
 * ===================================================================== */

/**
 * The front-end dashboard's own "My Listings" template
 * (theme/listingpro/templates/dashboard/listings.php) has the exact same
 * root cause as the admin column above — it computes its own $expiry
 * variable from 'lp_purchase_days' and defaults to 'Unlimited' when that
 * key is empty, which is always true for a trial-related listing since
 * ip_trial_purge_purchase_days() deletes it on purpose.
 *
 * Unlike the admin column, this is plain procedural template code with no
 * action or filter to hook — get_template_part() either finds a file or it
 * doesn't, there's nothing in between to intercept. So the fix lives in a
 * child theme override instead: an exact copy of that template
 * (wp-content/themes/listingpro-child/templates/dashboard/listings.php,
 * which WordPress's own template hierarchy already prefers over the
 * parent's copy) with one line added after each of its four
 * "if (!empty($plan_id))" blocks:
 *
 *     $expiry = ip_trial_dashboard_expiry_override( $expiry, $postID );
 *
 * That's the only change in the child copy — everything else is untouched,
 * so a future diff against the parent theme's own updates to this file
 * stays a four-line diff. This function is what that line calls.
 *
 * @param string $listingpro_value ListingPro's own computed $expiry string.
 * @param int    $listing_id
 * @return string
 */
function ip_trial_dashboard_expiry_override( $listingpro_value, $listing_id ) {
	if ( 'yes' === get_post_meta( $listing_id, '_ip_trial_active', true ) ) {
		$days = ip_trial_days_remaining_in_trial( $listing_id );
		if ( null !== $days ) {
			/* translators: %d: number of days remaining in the free trial. */
			return sprintf( esc_html__( '%d Days (Premium Trial)', 'listingpro' ), $days );
		}
	}

	if ( get_post_meta( $listing_id, '_ip_trial_ended', true ) && 'publish' === get_post_status( $listing_id ) ) {
		$days = ip_trial_days_remaining_in_free_grace( $listing_id );
		if ( null !== $days ) {
			/* translators: %d: number of days remaining on the free listing. */
			return sprintf( esc_html__( '%d Days (Free Listing)', 'listingpro' ), $days );
		}
	}

	// Not a trial-related listing — leave ListingPro's own value exactly as it was.
	return $listingpro_value;
}

/* =====================================================================
 * 11. ADMIN PANEL — view, extend, or end trials
 * ===================================================================== */

/**
 * A dedicated wp-admin page listing every listing currently in either
 * phase of the trial lifecycle (Premium trial, or the post-trial Free
 * grace period), with per-row actions to extend the remaining time or end
 * that phase immediately.
 *
 * Restricted to 'edit_others_posts' — the same capability level ListingPro
 * itself expects of anyone managing listings site-wide (typically Editor
 * or Admin), not something an instructor's own account has.
 *
 * "End Now" reuses the exact same ip_trial_downgrade() /
 * ip_trial_expire_free_listing() functions the daily cron calls — so
 * manually ending a Premium trial still sends the normal 'ended'
 * (downgrade confirmation) email, and manually ending a Free grace period
 * still sends the normal 'free_expired' email. No separate manual-action
 * email variant was needed.
 *
 * "Extend" adds days via '_ip_trial_extension_days' /
 * '_ip_trial_free_extension_days' rather than moving the original start
 * timestamp — see ip_trial_days_remaining_in_trial() /
 * ip_trial_days_remaining_in_free_grace() above. It also resets that
 * phase's own reminder-sent flag, so the 7-day reminder can correctly
 * fire again once the extended listing approaches its new, later expiry.
 */
add_action( 'admin_menu', 'ip_trial_register_admin_page' );
function ip_trial_register_admin_page() {
	add_submenu_page(
		'edit.php?post_type=listing',
		esc_html__( 'Trial Listings', 'listingpro' ),
		esc_html__( 'Trial Listings', 'listingpro' ),
		'edit_others_posts',
		'ip-trial-listings',
		'ip_trial_render_admin_page'
	);
}

/**
 * Process an Extend or End submission from the admin page, if present.
 *
 * @return string A one-line success message to display, or '' if no
 *                action was submitted this request.
 */
function ip_trial_handle_admin_actions() {
	if ( ! isset( $_POST['ip_trial_action'], $_POST['ip_trial_listing_id'], $_POST['ip_trial_phase'] ) ) {
		return '';
	}

	$listing_id = (int) $_POST['ip_trial_listing_id'];

	check_admin_referer( 'ip_trial_admin_action_' . $listing_id, 'ip_trial_admin_nonce' );

	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'You do not have permission to do that.', 'listingpro' ) );
	}

	$action = sanitize_key( wp_unslash( $_POST['ip_trial_action'] ) );
	$phase  = sanitize_key( wp_unslash( $_POST['ip_trial_phase'] ) );

	if ( ! in_array( $phase, array( 'trial', 'free' ), true ) ) {
		return '';
	}

	if ( 'extend' === $action ) {
		$days = isset( $_POST['ip_trial_extend_days'] ) ? max( 1, (int) $_POST['ip_trial_extend_days'] ) : 7;

		if ( 'trial' === $phase ) {
			$current = (int) get_post_meta( $listing_id, '_ip_trial_extension_days', true );
			update_post_meta( $listing_id, '_ip_trial_extension_days', $current + $days );
			update_post_meta( $listing_id, '_ip_trial_reminder_sent', 'no' );
		} else {
			$current = (int) get_post_meta( $listing_id, '_ip_trial_free_extension_days', true );
			update_post_meta( $listing_id, '_ip_trial_free_extension_days', $current + $days );
			update_post_meta( $listing_id, '_ip_trial_free_reminder_sent', 'no' );
		}

		/* translators: 1: number of days added, 2: listing title. */
		return sprintf( esc_html__( 'Extended by %1$d day(s) for "%2$s".', 'listingpro' ), $days, get_the_title( $listing_id ) );
	}

	if ( 'end' === $action ) {
		if ( 'trial' === $phase ) {
			ip_trial_downgrade( $listing_id );
		} else {
			ip_trial_expire_free_listing( $listing_id );
		}

		/* translators: %s: listing title. */
		return sprintf( esc_html__( 'Ended the trial for "%s".', 'listingpro' ), get_the_title( $listing_id ) );
	}

	return '';
}

/**
 * Every listing currently in Phase 1 (active Premium trial) or Phase 2
 * (downgraded to Free, still inside the grace period — post_status is
 * still 'publish', so an already-expired listing is naturally excluded).
 *
 * @return array[] Each row: id, phase ('trial'|'free'), phase_label,
 *                 days_remaining, started_date.
 */
function ip_trial_get_all_trial_listings() {
	$rows = array();

	$phase1_ids = get_posts( array(
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

	foreach ( $phase1_ids as $id ) {
		$start  = (int) get_post_meta( $id, '_ip_trial_start', true );
		$rows[] = array(
			'id'             => $id,
			'phase'          => 'trial',
			'phase_label'    => esc_html__( 'Premium Trial', 'listingpro' ),
			'days_remaining' => ip_trial_days_remaining_in_trial( $id ),
			'started_date'   => $start ? date_i18n( get_option( 'date_format' ), $start ) : '',
		);
	}

	$phase2_ids = get_posts( array(
		'post_type'      => 'listing',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array(
			array(
				'key'     => '_ip_trial_ended',
				'compare' => 'EXISTS',
			),
		),
	) );

	foreach ( $phase2_ids as $id ) {
		$ended  = (int) get_post_meta( $id, '_ip_trial_ended', true );
		$rows[] = array(
			'id'             => $id,
			'phase'          => 'free',
			'phase_label'    => esc_html__( 'Free Grace Period', 'listingpro' ),
			'days_remaining' => ip_trial_days_remaining_in_free_grace( $id ),
			'started_date'   => $ended ? date_i18n( get_option( 'date_format' ), $ended ) : '',
		);
	}

	return $rows;
}

function ip_trial_render_admin_page() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'listingpro' ) );
	}

	$notice     = ip_trial_handle_admin_actions();
	$all_trials = ip_trial_get_all_trial_listings();

	// Search — filters by listing title or instructor display name.
	// Plain substring match over the already-fetched array rather than a
	// second DB query: this list is realistically dozens to a few hundred
	// rows, never the scale where that would matter.
	$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$trials = $all_trials;
	if ( '' !== $search ) {
		$needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( $search ) : strtolower( $search );
		$trials = array_filter( $all_trials, function ( $row ) use ( $needle ) {
			$title      = get_the_title( $row['id'] );
			$instructor = get_the_author_meta( 'display_name', get_post_field( 'post_author', $row['id'] ) );
			$haystack   = function_exists( 'mb_strtolower' ) ? mb_strtolower( $title . ' ' . $instructor ) : strtolower( $title . ' ' . $instructor );
			return false !== strpos( $haystack, $needle );
		} );
	}
	$trials = array_values( $trials );

	// Pagination over the (possibly search-filtered) result set.
	$per_page     = 20;
	$total_items  = count( $trials );
	$total_pages  = max( 1, (int) ceil( $total_items / $per_page ) );
	$current_page = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
	$current_page = min( $current_page, $total_pages );
	$page_trials  = array_slice( $trials, ( $current_page - 1 ) * $per_page, $per_page );
	?>
	<div class="wrap">
		<h1 style="margin-bottom:16px;"><?php esc_html_e( 'Trial Listings', 'listingpro' ); ?></h1>

		<?php if ( $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>

		<form method="get" style="margin-bottom:12px;">
			<input type="hidden" name="page" value="ip-trial-listings">
			<p class="search-box" style="margin:0;">
				<label class="screen-reader-text" for="ip-trial-search-input"><?php esc_html_e( 'Search listings or instructors', 'listingpro' ); ?></label>
				<input type="search" id="ip-trial-search-input" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search by listing or instructor…', 'listingpro' ); ?>">
				<button type="submit" class="button"><?php esc_html_e( 'Search', 'listingpro' ); ?></button>
				<?php if ( '' !== $search ) : ?>
					<a href="<?php echo esc_url( remove_query_arg( array( 's', 'paged' ) ) ); ?>" class="button-link" style="margin-left:6px;">
						<?php esc_html_e( 'Clear', 'listingpro' ); ?>
					</a>
				<?php endif; ?>
			</p>
		</form>

		<?php if ( empty( $all_trials ) ) : ?>
			<p><?php esc_html_e( 'No listings are currently on a trial or in the post-trial Free grace period.', 'listingpro' ); ?></p>
		<?php elseif ( empty( $page_trials ) ) : ?>
			<p>
				<?php
				/* translators: %s: the search term that matched nothing. */
				echo esc_html( sprintf( __( 'No listings or instructors match "%s".', 'listingpro' ), $search ) );
				?>
			</p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped" style="border-radius:6px; overflow:hidden;">
				<thead>
					<tr>
						<th style="padding:12px;"><?php esc_html_e( 'Listing', 'listingpro' ); ?></th>
						<th style="padding:12px;"><?php esc_html_e( 'Instructor', 'listingpro' ); ?></th>
						<th style="padding:12px;"><?php esc_html_e( 'Phase', 'listingpro' ); ?></th>
						<th style="padding:12px;"><?php esc_html_e( 'Days Remaining', 'listingpro' ); ?></th>
						<th style="padding:12px;"><?php esc_html_e( 'Started', 'listingpro' ); ?></th>
						<th style="padding:12px; width:170px;"><?php esc_html_e( 'Actions', 'listingpro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $page_trials as $row ) : ?>
						<?php
						$is_free_phase = ( 'free' === $row['phase'] );
						$phase_bg      = $is_free_phase ? '#f0f0f1' : '#eef1ff';
						$phase_color   = $is_free_phase ? '#50575e' : '#3538cd';

						$days = (int) $row['days_remaining'];
						if ( $days <= 3 ) {
							$days_color = '#c62828'; // red
						} elseif ( $days <= 7 ) {
							$days_color = '#b26a00'; // amber
						} else {
							$days_color = '#2e7d32'; // green
						}
						?>
						<tr>
							<td style="padding:12px;">
								<a href="<?php echo esc_url( get_edit_post_link( $row['id'] ) ); ?>" style="font-weight:600;">
									<?php echo esc_html( get_the_title( $row['id'] ) ); ?>
								</a>
							</td>
							<td style="padding:12px;">
								<?php echo esc_html( get_the_author_meta( 'display_name', get_post_field( 'post_author', $row['id'] ) ) ); ?>
							</td>
							<td style="padding:12px;">
								<span style="display:inline-block; background:<?php echo esc_attr( $phase_bg ); ?>; color:<?php echo esc_attr( $phase_color ); ?>; border-radius:20px; padding:4px 12px; font-size:12px; font-weight:600; white-space:nowrap;">
									<?php echo esc_html( $row['phase_label'] ); ?>
								</span>
							</td>
							<td style="padding:12px;">
								<span style="color:<?php echo esc_attr( $days_color ); ?>; font-weight:700; font-size:14px;">
									<?php echo esc_html( $row['days_remaining'] ); ?>
								</span>
								<span style="color:#787c82;">
									<?php echo 1 === $days ? esc_html__( 'day', 'listingpro' ) : esc_html__( 'days', 'listingpro' ); ?>
								</span>
							</td>
							<td style="padding:12px; color:#50575e;"><?php echo esc_html( $row['started_date'] ); ?></td>
							<td style="padding:12px;">
								<div style="display:flex; align-items:center; gap:4px;">
									<form method="post" style="display:flex; align-items:center; gap:4px;">
										<?php wp_nonce_field( 'ip_trial_admin_action_' . $row['id'], 'ip_trial_admin_nonce' ); ?>
										<input type="hidden" name="ip_trial_listing_id" value="<?php echo esc_attr( $row['id'] ); ?>">
										<input type="hidden" name="ip_trial_phase" value="<?php echo esc_attr( $row['phase'] ); ?>">
										<input
											type="number"
											name="ip_trial_extend_days"
											value="7"
											min="1"
											style="width:48px; padding:2px 4px;"
											aria-label="<?php esc_attr_e( 'Number of days to extend', 'listingpro' ); ?>"
										>
										<button
											type="submit"
											name="ip_trial_action"
											value="extend"
											class="button button-secondary"
											style="padding:0 6px; line-height:28px; height:30px;"
											title="<?php esc_attr_e( 'Extend', 'listingpro' ); ?>"
											aria-label="<?php esc_attr_e( 'Extend by the number of days entered', 'listingpro' ); ?>"
										>
											<span class="dashicons dashicons-plus-alt2" style="line-height:28px;"></span>
										</button>
									</form>
									<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'End this now? This cannot be undone.', 'listingpro' ) ); ?>');">
										<?php wp_nonce_field( 'ip_trial_admin_action_' . $row['id'], 'ip_trial_admin_nonce' ); ?>
										<input type="hidden" name="ip_trial_listing_id" value="<?php echo esc_attr( $row['id'] ); ?>">
										<input type="hidden" name="ip_trial_phase" value="<?php echo esc_attr( $row['phase'] ); ?>">
										<button
											type="submit"
											name="ip_trial_action"
											value="end"
											class="button button-secondary"
											style="padding:0 6px; height:30px; color:#c62828; border-color:#c62828;"
											title="<?php esc_attr_e( 'End Now', 'listingpro' ); ?>"
											aria-label="<?php esc_attr_e( 'End this trial now', 'listingpro' ); ?>"
										>
											<span class="dashicons dashicons-dismiss" style="line-height:28px;"></span>
										</button>
									</form>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav" style="margin-top:12px;">
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php
							/* translators: %s: total number of matching listings. */
							echo esc_html( sprintf( _n( '%s item', '%s items', $total_items, 'listingpro' ), number_format_i18n( $total_items ) ) );
							?>
						</span>
						<?php
						echo paginate_links( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links() output is already safe.
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'current'   => $current_page,
							'total'     => $total_pages,
							'prev_text' => esc_html__( '‹', 'listingpro' ),
							'next_text' => esc_html__( '›', 'listingpro' ),
						) );
						?>
					</div>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}
