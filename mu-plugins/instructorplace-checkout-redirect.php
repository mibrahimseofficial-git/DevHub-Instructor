<?php
/**
 * Plugin Name: Instructor Place — Direct Checkout Redirect
 * Description: When a submitted listing is on a paid plan, sends the user
 * straight to the payment checkout page instead of the listing's own page
 * (where they'd otherwise have to find and click a separate "Pay" button,
 * either on that page's confirmation bar or later from their dashboard).
 * Free-plan listings are unaffected — they keep going to the listing page
 * as before.
 *
 * HOW THIS WORKS — read before changing anything
 * ------------------------------------------------
 * ListingPro's own submission handler is
 * listingpro_submit_listing_ajax() in the ListingPro plugin's
 * inc/submit-ajax.php. On success it dies with:
 *   json_encode(array('response' => 'success', 'status' => <permalink>, 'msg' => ...))
 * and the front-end JS (submit-listing.js) reads resp.status as the
 * URL to redirect the browser to.
 *
 * That handler is third-party plugin code — it must never be edited
 * directly, since any ListingPro update overwrites the file and silently
 * discards the change. There's also no filter hook around that $response
 * value to tap into.
 *
 * So this intercepts the RAW HTTP RESPONSE instead, via a standard PHP
 * output buffer: when the incoming request is specifically this AJAX
 * action, an output buffer wraps the whole request. ListingPro's handler
 * runs completely untouched and die()s as normal; the buffer callback
 * receives that JSON as a string, and only if it's a success response
 * whose underlying listing turns out to need payment does it rewrite the
 * 'status' URL to the checkout page before letting the response go out.
 * Every other request, and every non-paid submission, passes through
 * completely unchanged.
 *
 * SESSION HANDLING: the existing "Pay" button (list-confirmation.php +
 * single-ajax.js' lp_save_thisid_in_session AJAX call) sets BOTH
 * $_SESSION['listing_id_checkout'] and a 'listing_id_checkout' user meta
 * before sending the browser to the checkout page, since that's how the
 * checkout page itself knows which listing/plan to bill. This does the
 * same two writes so the checkout page behaves identically regardless of
 * which path got the user there. session_start() is called from 'init'
 * (before any output), not from inside the buffer callback, to avoid the
 * "headers already sent" risk of starting a session that late.
 *
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'IP_CHECKOUT_REDIRECT_ACTION', 'listingpro_submit_listing_ajax' );

/**
 * Starts the session (if needed) and opens the output buffer, but only
 * for the exact request this feature cares about.
 */
add_action( 'init', 'ip_checkout_redirect_maybe_start_buffer', 1 );
function ip_checkout_redirect_maybe_start_buffer() {
	if ( ! isset( $_POST['action'] ) || IP_CHECKOUT_REDIRECT_ACTION !== $_POST['action'] ) {
		return;
	}

	if ( ! session_id() ) {
		session_start();
	}

	ob_start( 'ip_checkout_redirect_filter_response' );
}

/**
 * The actual interception. Runs against the raw response body once
 * ListingPro's own handler has finished and produced it.
 *
 * @param string $output
 * @return string
 */
function ip_checkout_redirect_filter_response( $output ) {
	$data = json_decode( $output, true );

	if ( ! is_array( $data ) || ! isset( $data['response'], $data['status'] ) || 'success' !== $data['response'] ) {
		return $output; // Not a success response (or not JSON at all) -- leave it exactly as ListingPro produced it.
	}

	$post_id = url_to_postid( $data['status'] );
	if ( ! $post_id || ! ip_checkout_redirect_listing_needs_payment( $post_id ) ) {
		return $output; // Free plan, or couldn't resolve the listing -- leave the original permalink redirect in place.
	}

	global $listingpro_options;
	$checkout_page_id = isset( $listingpro_options['payment-checkout'] ) ? $listingpro_options['payment-checkout'] : 0;
	$checkout_url      = $checkout_page_id ? get_permalink( $checkout_page_id ) : '';

	if ( empty( $checkout_url ) ) {
		return $output; // Checkout page isn't configured -- fall back to ListingPro's own default behavior rather than sending the user nowhere.
	}

	// Same two writes the existing "Pay" button makes, so the checkout
	// page recognizes this listing/plan exactly as it would from that path.
	update_user_meta( get_current_user_id(), 'listing_id_checkout', $post_id );
	$_SESSION['listing_id_checkout'] = $post_id;

	$data['status'] = $checkout_url;

	return wp_json_encode( $data );
}

/**
 * Mirrors the same "does this listing need payment" check
 * list-confirmation.php already uses for its own "Pay & Publish" button,
 * so a listing is judged the same way in both places.
 *
 * @param int $post_id
 * @return bool
 */
function ip_checkout_redirect_listing_needs_payment( $post_id ) {
	global $listingpro_options;

	$paid_mode = isset( $listingpro_options['enable_paid_submission'] ) ? $listingpro_options['enable_paid_submission'] : 'no';
	if ( 'yes' !== $paid_mode ) {
		return false;
	}

	$postmeta = get_post_meta( $post_id, 'lp_listingpro_options', true );
	$plan_id  = is_array( $postmeta ) && isset( $postmeta['Plan_id'] ) ? $postmeta['Plan_id'] : 0;
	if ( ! $plan_id ) {
		return false;
	}

	$plan_price = get_post_meta( $plan_id, 'plan_price', true );

	return ! empty( $plan_price );
}
