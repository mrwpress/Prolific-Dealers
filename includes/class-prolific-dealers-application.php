<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Prolific_Dealers_Application {

	public static function init() {
		add_shortcode( 'dealer_application', [ __CLASS__, 'render_form' ] );
		add_action( 'template_redirect', [ __CLASS__, 'handle_submission' ] );
		add_filter( 'wp_authenticate_user', [ __CLASS__, 'block_pending_dealer' ], 10, 1 );
	}

	public static function render_form() {
		if ( is_user_logged_in() ) {
			return '<p>' . esc_html__( 'You are already logged in.', 'prolific-dealers' ) . '</p>';
		}

		if ( isset( $_GET['dealer-applied'] ) && '1' === $_GET['dealer-applied'] ) {
			return '<div class="prolific-dealer-success">'
				. '<p>' . esc_html__( 'Thank you for your application! We will review your information and get back to you shortly.', 'prolific-dealers' ) . '</p>'
				. '</div>';
		}

		$errors = [];
		if ( isset( $_GET['dealer-error'] ) ) {
			$error_code = sanitize_text_field( $_GET['dealer-error'] );
			$error_map  = [
				'nonce'          => __( 'Security check failed. Please try again.', 'prolific-dealers' ),
				'required'       => __( 'Please fill in all required fields.', 'prolific-dealers' ),
				'email'          => __( 'Please enter a valid email address.', 'prolific-dealers' ),
				'email_exists'   => __( 'An account with that email address already exists.', 'prolific-dealers' ),
				'terms'          => __( 'You must agree to the terms and conditions.', 'prolific-dealers' ),
				'create_failed'  => __( 'Something went wrong. Please try again.', 'prolific-dealers' ),
			];
			if ( isset( $error_map[ $error_code ] ) ) {
				$errors[] = $error_map[ $error_code ];
			}
		}

		ob_start();
		if ( $errors ) {
			echo '<div class="prolific-dealer-errors">';
			foreach ( $errors as $error ) {
				echo '<p>' . esc_html( $error ) . '</p>';
			}
			echo '</div>';
		}
		?>
		<form method="post" class="prolific-dealer-application">
			<?php wp_nonce_field( 'prolific_dealer_application', 'prolific_dealer_nonce' ); ?>
			<input type="hidden" name="prolific_dealer_action" value="apply" />

			<p>
				<label for="pda_business_name"><?php esc_html_e( 'Business Name', 'prolific-dealers' ); ?> <span class="required">*</span></label>
				<input type="text" id="pda_business_name" name="pda_business_name" required />
			</p>

			<p>
				<label for="pda_street_address"><?php esc_html_e( 'Street Address', 'prolific-dealers' ); ?> <span class="required">*</span></label>
				<input type="text" id="pda_street_address" name="pda_street_address" required />
			</p>

			<p>
				<label for="pda_address_line_2"><?php esc_html_e( 'Address Line 2', 'prolific-dealers' ); ?></label>
				<input type="text" id="pda_address_line_2" name="pda_address_line_2" />
			</p>

			<p>
				<label for="pda_city"><?php esc_html_e( 'City', 'prolific-dealers' ); ?> <span class="required">*</span></label>
				<input type="text" id="pda_city" name="pda_city" required />
			</p>

			<p>
				<label for="pda_state"><?php esc_html_e( 'State / Province / Region', 'prolific-dealers' ); ?> <span class="required">*</span></label>
				<input type="text" id="pda_state" name="pda_state" required />
			</p>

			<p>
				<label for="pda_zip"><?php esc_html_e( 'ZIP / Postal Code', 'prolific-dealers' ); ?> <span class="required">*</span></label>
				<input type="text" id="pda_zip" name="pda_zip" required />
			</p>

			<p>
				<label for="pda_country"><?php esc_html_e( 'Country', 'prolific-dealers' ); ?> <span class="required">*</span></label>
				<select id="pda_country" name="pda_country" required>
					<option value=""><?php esc_html_e( '— Select Country —', 'prolific-dealers' ); ?></option>
					<?php
					$countries = WC()->countries->get_countries();
					foreach ( $countries as $code => $name ) {
						echo '<option value="' . esc_attr( $code ) . '">' . esc_html( $name ) . '</option>';
					}
					?>
				</select>
			</p>

			<p>
				<label for="pda_phone"><?php esc_html_e( 'Phone', 'prolific-dealers' ); ?> <span class="required">*</span></label>
				<input type="tel" id="pda_phone" name="pda_phone" required />
			</p>

			<p>
				<label for="pda_website"><?php esc_html_e( 'Website', 'prolific-dealers' ); ?></label>
				<input type="url" id="pda_website" name="pda_website" />
			</p>

			<p>
				<label for="pda_contact_name"><?php esc_html_e( 'Your Name', 'prolific-dealers' ); ?> <span class="required">*</span></label>
				<input type="text" id="pda_contact_name" name="pda_contact_name" required />
			</p>

			<p>
				<label for="pda_business_type"><?php esc_html_e( 'What type of work does your business do?', 'prolific-dealers' ); ?> <span class="required">*</span></label>
				<textarea id="pda_business_type" name="pda_business_type" rows="4" required></textarea>
			</p>

			<p>
				<label for="pda_email"><?php esc_html_e( 'Email (Username)', 'prolific-dealers' ); ?> <span class="required">*</span></label>
				<input type="email" id="pda_email" name="pda_email" required />
			</p>

			<p>
				<label>
					<input type="checkbox" name="pda_terms" value="1" required />
					<?php esc_html_e( 'I agree to the terms and conditions', 'prolific-dealers' ); ?>
				</label>
			</p>

			<p>
				<button type="submit" class="button"><?php esc_html_e( 'Register', 'prolific-dealers' ); ?></button>
			</p>
		</form>
		<?php
		return ob_get_clean();
	}

	public static function handle_submission() {
		if ( ! isset( $_POST['prolific_dealer_action'] ) || 'apply' !== $_POST['prolific_dealer_action'] ) {
			return;
		}

		$redirect_url = wp_get_referer() ?: home_url();

		if ( ! wp_verify_nonce( $_POST['prolific_dealer_nonce'] ?? '', 'prolific_dealer_application' ) ) {
			wp_safe_redirect( add_query_arg( 'dealer-error', 'nonce', $redirect_url ) );
			exit;
		}

		$required_fields = [
			'pda_business_name',
			'pda_street_address',
			'pda_city',
			'pda_state',
			'pda_zip',
			'pda_country',
			'pda_phone',
			'pda_contact_name',
			'pda_business_type',
			'pda_email',
		];

		foreach ( $required_fields as $field ) {
			if ( empty( $_POST[ $field ] ) ) {
				wp_safe_redirect( add_query_arg( 'dealer-error', 'required', $redirect_url ) );
				exit;
			}
		}

		if ( empty( $_POST['pda_terms'] ) ) {
			wp_safe_redirect( add_query_arg( 'dealer-error', 'terms', $redirect_url ) );
			exit;
		}

		$email = sanitize_email( $_POST['pda_email'] );
		if ( ! is_email( $email ) ) {
			wp_safe_redirect( add_query_arg( 'dealer-error', 'email', $redirect_url ) );
			exit;
		}

		if ( email_exists( $email ) || username_exists( $email ) ) {
			wp_safe_redirect( add_query_arg( 'dealer-error', 'email_exists', $redirect_url ) );
			exit;
		}

		$password = wp_generate_password( 16, true, true );
		$user_id  = wp_insert_user( [
			'user_login' => $email,
			'user_email' => $email,
			'user_pass'  => $password,
			'first_name' => sanitize_text_field( $_POST['pda_contact_name'] ),
			'role'       => 'dealer',
		] );

		if ( is_wp_error( $user_id ) ) {
			wp_safe_redirect( add_query_arg( 'dealer-error', 'create_failed', $redirect_url ) );
			exit;
		}

		update_user_meta( $user_id, '_prolific_dealer_status', 'pending' );
		update_user_meta( $user_id, '_prolific_dealer_business_name', sanitize_text_field( $_POST['pda_business_name'] ) );
		update_user_meta( $user_id, '_prolific_dealer_street_address', sanitize_text_field( $_POST['pda_street_address'] ) );
		update_user_meta( $user_id, '_prolific_dealer_address_line_2', sanitize_text_field( $_POST['pda_address_line_2'] ?? '' ) );
		update_user_meta( $user_id, '_prolific_dealer_city', sanitize_text_field( $_POST['pda_city'] ) );
		update_user_meta( $user_id, '_prolific_dealer_state', sanitize_text_field( $_POST['pda_state'] ) );
		update_user_meta( $user_id, '_prolific_dealer_zip', sanitize_text_field( $_POST['pda_zip'] ) );
		update_user_meta( $user_id, '_prolific_dealer_country', sanitize_text_field( $_POST['pda_country'] ) );
		update_user_meta( $user_id, '_prolific_dealer_phone', sanitize_text_field( $_POST['pda_phone'] ) );
		update_user_meta( $user_id, '_prolific_dealer_website', esc_url_raw( $_POST['pda_website'] ?? '' ) );
		update_user_meta( $user_id, '_prolific_dealer_business_type', sanitize_textarea_field( $_POST['pda_business_type'] ) );

		self::send_admin_notification( $user_id );

		wp_safe_redirect( add_query_arg( 'dealer-applied', '1', $redirect_url ) );
		exit;
	}

	private static function send_admin_notification( $user_id ) {
		$admin_email  = get_option( 'admin_email' );
		$business     = get_user_meta( $user_id, '_prolific_dealer_business_name', true );
		$contact_name = get_user_by( 'ID', $user_id )->first_name;
		$email        = get_user_by( 'ID', $user_id )->user_email;
		$edit_url     = admin_url( 'user-edit.php?user_id=' . $user_id );

		$subject = sprintf( '[%s] New Dealer Application: %s', get_bloginfo( 'name' ), $business );
		$message = sprintf(
			"A new dealer application has been submitted.\n\n"
			. "Business: %s\n"
			. "Contact: %s\n"
			. "Email: %s\n\n"
			. "Review and approve: %s\n",
			$business,
			$contact_name,
			$email,
			$edit_url
		);

		wp_mail( $admin_email, $subject, $message );
	}

	public static function block_pending_dealer( $user ) {
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		if ( ! in_array( 'dealer', (array) $user->roles, true ) ) {
			return $user;
		}

		$status = get_user_meta( $user->ID, '_prolific_dealer_status', true );
		if ( 'pending' === $status ) {
			return new \WP_Error(
				'dealer_pending',
				__( 'Your dealer application is pending approval. You will be notified once approved.', 'prolific-dealers' )
			);
		}

		return $user;
	}
}
