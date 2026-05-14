<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Prolific_Dealers_User {

	public static function init() {
		add_action( 'show_user_profile', [ __CLASS__, 'render_tier_field' ] );
		add_action( 'edit_user_profile', [ __CLASS__, 'render_tier_field' ] );
		add_action( 'personal_options_update', [ __CLASS__, 'save_tier_field' ] );
		add_action( 'edit_user_profile_update', [ __CLASS__, 'save_tier_field' ] );
	}

	public static function render_tier_field( $user ) {
		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}

		if ( ! in_array( 'dealer', (array) $user->roles, true ) ) {
			return;
		}

		$tier = get_user_meta( $user->ID, '_prolific_dealer_tier', true );
		?>
		<h3><?php esc_html_e( 'Dealer Settings', 'prolific-dealers' ); ?></h3>
		<table class="form-table">
			<tr>
				<th><label for="prolific_dealer_tier"><?php esc_html_e( 'Dealer Tier', 'prolific-dealers' ); ?></label></th>
				<td>
					<select name="prolific_dealer_tier" id="prolific_dealer_tier">
						<option value=""><?php esc_html_e( '— Select Tier —', 'prolific-dealers' ); ?></option>
						<?php for ( $i = 1; $i <= 10; $i++ ) : ?>
							<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $tier, $i ); ?>>
								<?php
								printf(
									esc_html__( 'Tier %1$d (%2$s%%)', 'prolific-dealers' ),
									$i,
									Prolific_Dealers_Settings::get_tier_discount( $i )
								);
								?>
							</option>
						<?php endfor; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Select the dealer pricing tier for this user.', 'prolific-dealers' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="prolific_dealer_discount_override"><?php esc_html_e( 'Discount Override (%)', 'prolific-dealers' ); ?></label></th>
				<td>
					<input type="number" id="prolific_dealer_discount_override" name="prolific_dealer_discount_override"
						value="<?php echo esc_attr( get_user_meta( $user->ID, '_prolific_dealer_discount_override', true ) ); ?>"
						min="0" max="100" step="1" style="width:80px;" />
					<p class="description"><?php esc_html_e( 'Overrides tier and product-level discounts. Leave empty to use defaults.', 'prolific-dealers' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="prolific_dealer_status"><?php esc_html_e( 'Dealer Status', 'prolific-dealers' ); ?></label></th>
				<td>
					<?php $status = get_user_meta( $user->ID, '_prolific_dealer_status', true ); ?>
					<select name="prolific_dealer_status" id="prolific_dealer_status">
						<option value="approved" <?php selected( $status, 'approved' ); ?>><?php esc_html_e( 'Approved', 'prolific-dealers' ); ?></option>
						<option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'prolific-dealers' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Pending dealers cannot log in.', 'prolific-dealers' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	public static function save_tier_field( $user_id ) {
		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}

		if ( ! isset( $_POST['prolific_dealer_tier'] ) ) {
			return;
		}

		$tier = sanitize_text_field( $_POST['prolific_dealer_tier'] );
		update_user_meta( $user_id, '_prolific_dealer_tier', $tier );

		$override = isset( $_POST['prolific_dealer_discount_override'] ) ? sanitize_text_field( $_POST['prolific_dealer_discount_override'] ) : '';
		if ( '' === $override ) {
			delete_user_meta( $user_id, '_prolific_dealer_discount_override' );
		} else {
			update_user_meta( $user_id, '_prolific_dealer_discount_override', $override );
		}

		if ( isset( $_POST['prolific_dealer_status'] ) ) {
			$status = sanitize_text_field( $_POST['prolific_dealer_status'] );
			update_user_meta( $user_id, '_prolific_dealer_status', $status );
		}
	}

	public static function get_dealer_tier( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		return absint( get_user_meta( $user_id, '_prolific_dealer_tier', true ) );
	}

	public static function get_discount_override( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}
		$override = get_user_meta( $user_id, '_prolific_dealer_discount_override', true );
		if ( '' === $override ) {
			return null;
		}
		return (float) $override;
	}
}
