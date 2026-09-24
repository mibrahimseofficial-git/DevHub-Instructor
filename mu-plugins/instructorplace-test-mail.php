<?php
/**
 * Plugin Name: Instructor Place — Test Mail Tool
 * Description: Temporary diagnostic tool. Adds Tools -> Test Email, a page
 * to send a plain wp_mail() test to any address, with the real underlying
 * PHPMailer/SMTP error shown when it fails -- wp_mail() itself only ever
 * returns true/false, with no detail, so this hooks 'wp_mail_failed' to
 * capture the actual exception message instead of guessing.
 *
 * Deliberately sends via plain wp_mail() with no custom headers and no
 * From-address override, so this reflects exactly whatever the site's
 * current default mail setup actually does right now -- useful both
 * before and after changing any SMTP configuration, to see whether that
 * change actually moved the needle.
 *
 * DELETE THIS FILE once mail delivery is confirmed working -- it's a
 * diagnostic tool, not a permanent feature, and there's no reason to
 * leave an admin-triggerable "send arbitrary email to arbitrary address"
 * tool sitting on a production site longer than needed.
 *
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'ip_test_mail_register_page' );
function ip_test_mail_register_page() {
	add_management_page(
		esc_html__( 'Test Email', 'listingpro' ),
		esc_html__( 'Test Email', 'listingpro' ),
		'manage_options',
		'ip-test-mail',
		'ip_test_mail_render_page'
	);
}

/**
 * wp_mail() swallows the real PHPMailer/SMTP exception and just returns
 * false. This is the only way to get the actual error message back.
 *
 * @param WP_Error $wp_error
 */
add_action( 'wp_mail_failed', 'ip_test_mail_capture_error' );
function ip_test_mail_capture_error( $wp_error ) {
	if ( is_wp_error( $wp_error ) ) {
		update_option( 'ip_test_mail_last_error', $wp_error->get_error_message(), false );
	}
}

function ip_test_mail_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'listingpro' ) );
	}

	$result_message = '';
	$result_type    = '';

	if ( isset( $_POST['ip_test_mail_to'], $_POST['ip_test_mail_nonce'] ) ) {
		check_admin_referer( 'ip_test_mail_send', 'ip_test_mail_nonce' );

		$to = sanitize_email( wp_unslash( $_POST['ip_test_mail_to'] ) );

		if ( ! is_email( $to ) ) {
			$result_message = esc_html__( 'That is not a valid email address.', 'listingpro' );
			$result_type    = 'error';
		} else {
			// Clear any previous error first, so a stale error from an
			// earlier attempt can't be mistaken for this one's result.
			delete_option( 'ip_test_mail_last_error' );

			$subject = sprintf(
				/* translators: %s: site name. */
				esc_html__( '[%s] Test email', 'listingpro' ),
				get_bloginfo( 'name' )
			);
			$body = sprintf(
				/* translators: %s: current date and time. */
				esc_html__( 'This is a test email sent from the Test Email tool at %s.', 'listingpro' ),
				current_time( 'mysql' )
			);

			// Plain wp_mail() on purpose -- no custom headers, no From
			// override. This is exactly what every other wp_mail() call
			// on this site does by default right now.
			$sent = wp_mail( $to, $subject, $body );

			if ( $sent ) {
				/* translators: %s: recipient email address. */
				$result_message = sprintf(
					esc_html__( 'wp_mail() reported success sending to %s. Check that inbox (and spam folder) to confirm it actually arrived -- a true result here means WordPress handed the message off without error, not that the receiving server accepted it.', 'listingpro' ),
					$to
				);
				$result_type = 'success';
			} else {
				$last_error = get_option( 'ip_test_mail_last_error', '' );
				if ( $last_error ) {
					/* translators: %s: the underlying mailer error message. */
					$result_message = sprintf( esc_html__( 'wp_mail() failed. Underlying error: %s', 'listingpro' ), $last_error );
				} else {
					$result_message = esc_html__( 'wp_mail() returned false, but no specific error was captured by the wp_mail_failed hook.', 'listingpro' );
				}
				$result_type = 'error';
			}
		}
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Test Email', 'listingpro' ); ?></h1>
		<p>
			<?php esc_html_e( 'Sends a plain wp_mail() test to any address you enter, using whatever the site\'s current default mail configuration is -- no custom From address, nothing overridden. Use this before and after changing any SMTP setup to see whether the change actually made a difference.', 'listingpro' ); ?>
		</p>

		<?php if ( $result_message ) : ?>
			<div class="notice notice-<?php echo ( 'success' === $result_type ) ? 'success' : 'error'; ?>" style="padding:12px;">
				<p><?php echo esc_html( $result_message ); ?></p>
			</div>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'ip_test_mail_send', 'ip_test_mail_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="ip_test_mail_to"><?php esc_html_e( 'Send test email to', 'listingpro' ); ?></label>
					</th>
					<td>
						<input type="email" id="ip_test_mail_to" name="ip_test_mail_to" class="regular-text" required placeholder="you@example.com">
					</td>
				</tr>
			</table>
			<?php submit_button( esc_html__( 'Send Test Email', 'listingpro' ) ); ?>
		</form>
	</div>
	<?php
}
