<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Prolific_Dealers_Visibility {

	public static function init() {
		add_action( 'add_meta_boxes', [ __CLASS__, 'register_meta_box' ] );
		add_action( 'save_post_product', [ __CLASS__, 'save_meta_box' ] );
		add_action( 'woocommerce_product_query', [ __CLASS__, 'filter_product_query' ] );
		add_filter( 'woocommerce_shortcode_products_query', [ __CLASS__, 'filter_shortcode_query' ] );
		add_filter( 'woocommerce_product_is_visible', [ __CLASS__, 'filter_product_visibility' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_meta_box_js' ] );
	}

	public static function register_meta_box() {
		add_meta_box(
			'prolific_product_visibility',
			'Product Visibility',
			[ __CLASS__, 'render_meta_box' ],
			'product',
			'side',
			'default'
		);
	}

	/**
	 * Check whether a product is visible to customers.
	 */
	public static function is_visible_to_customers( $product_id ) {
		$val = get_post_meta( $product_id, '_prolific_visible_to_customers', true );

		// Not yet set — check legacy meta for backwards compat
		if ( '' === $val ) {
			$legacy_mode = get_post_meta( $product_id, '_prolific_product_visibility', true );
			if ( 'dealers' === $legacy_mode ) {
				return false;
			}

			$legacy_dealer_only = get_post_meta( $product_id, '_prolific_dealer_only', true );
			if ( '1' === $legacy_dealer_only ) {
				return false;
			}

			return true;
		}

		return '1' === $val;
	}

	/**
	 * Check whether a product is visible to dealers.
	 */
	public static function is_visible_to_dealers( $product_id ) {
		$val = get_post_meta( $product_id, '_prolific_visible_to_dealers', true );

		// Not yet set — check legacy meta for backwards compat
		if ( '' === $val ) {
			$legacy_mode = get_post_meta( $product_id, '_prolific_product_visibility', true );
			if ( 'customers' === $legacy_mode ) {
				return false;
			}

			return true;
		}

		return '1' === $val;
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'prolific_product_visibility', 'prolific_product_visibility_nonce' );
		$show_customers  = self::is_visible_to_customers( $post->ID );
		$show_dealers    = self::is_visible_to_dealers( $post->ID );
		$tier_visibility = get_post_meta( $post->ID, '_prolific_dealer_only_tiers', true ) ?: [];
		$overrides       = get_post_meta( $post->ID, '_prolific_dealer_discount_tiers', true ) ?: [];
		?>
		<p class="description"><?php esc_html_e( 'Who can see this product?', 'prolific-dealers' ); ?></p>
		<label style="display:block;margin:4px 0;">
			<input type="checkbox" name="prolific_visible_to_customers" value="1" <?php checked( $show_customers ); ?> />
			<?php esc_html_e( 'Customers', 'prolific-dealers' ); ?>
		</label>
		<label style="display:block;margin:4px 0;">
			<input type="checkbox" id="prolific_visible_to_dealers_cb" name="prolific_visible_to_dealers" value="1" <?php checked( $show_dealers ); ?> />
			<?php esc_html_e( 'Dealers', 'prolific-dealers' ); ?>
		</label>
		<div id="prolific_dealer_tiers" style="margin:6px 0 0 24px;<?php echo ! $show_dealers ? 'display:none;' : ''; ?>">
			<p class="description"><?php esc_html_e( 'Limit to specific tiers:', 'prolific-dealers' ); ?></p>
			<?php for ( $i = 1; $i <= 10; $i++ ) :
				$tier_checked = in_array( $i, array_map( 'intval', $tier_visibility ), true );
				$discount_val = isset( $overrides[ $i ] ) ? $overrides[ $i ] : '';
				?>
				<div style="margin:2px 0;">
					<label style="display:block;">
						<input type="checkbox" class="prolific-tier-cb" name="prolific_dealer_only_tiers[]" value="<?php echo $i; ?>" <?php checked( $tier_checked ); ?> />
						<?php echo esc_html( Prolific_Dealers_Settings::get_tier_label( $i ) ); ?>
					</label>
					<div class="prolific-tier-discount" data-tier="<?php echo $i; ?>" style="margin:2px 0 6px 24px;<?php echo ! $tier_checked ? 'display:none;' : ''; ?>">
						<label style="display:flex;align-items:center;gap:4px;">
							<?php esc_html_e( 'Discount override:', 'prolific-dealers' ); ?>
							<input type="number" name="prolific_dealer_discount_tier[<?php echo $i; ?>]"
								value="<?php echo esc_attr( $discount_val ); ?>" min="0" max="100" step="1" style="width:60px;" />
							<span>%</span>
						</label>
					</div>
				</div>
			<?php endfor; ?>
			<p class="description" style="margin-top:6px;"><?php esc_html_e( 'Leave all unchecked to show to all dealers.', 'prolific-dealers' ); ?></p>
		</div>
		<?php
	}

	public static function enqueue_meta_box_js( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->id ) {
			return;
		}

		$js = "jQuery(function($){
			var dealerCb = $('#prolific_visible_to_dealers_cb');
			var tierWrap = $('#prolific_dealer_tiers');

			dealerCb.on('change', function(){
				tierWrap.toggle(dealerCb.is(':checked'));
			});

			$('.prolific-tier-cb').on('change', function(){
				var tier = $(this).val();
				$('.prolific-tier-discount[data-tier=\"' + tier + '\"]').toggle($(this).is(':checked'));
			});
		});";

		wp_add_inline_script( 'jquery', $js );
	}

	public static function save_meta_box( $post_id ) {
		if ( ! isset( $_POST['prolific_product_visibility_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_POST['prolific_product_visibility_nonce'], 'prolific_product_visibility' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$show_customers = ! empty( $_POST['prolific_visible_to_customers'] ) ? '1' : '0';
		$show_dealers   = ! empty( $_POST['prolific_visible_to_dealers'] ) ? '1' : '0';

		update_post_meta( $post_id, '_prolific_visible_to_customers', $show_customers );
		update_post_meta( $post_id, '_prolific_visible_to_dealers', $show_dealers );

		// Clean up legacy meta
		delete_post_meta( $post_id, '_prolific_dealer_only' );
		delete_post_meta( $post_id, '_prolific_product_visibility' );

		if ( '1' === $show_dealers && ! empty( $_POST['prolific_dealer_only_tiers'] ) ) {
			$tiers = array_map( 'absint', (array) $_POST['prolific_dealer_only_tiers'] );
			$tiers = array_filter( $tiers, function( $t ) { return $t >= 1 && $t <= 10; } );
			update_post_meta( $post_id, '_prolific_dealer_only_tiers', array_values( $tiers ) );
		} else {
			delete_post_meta( $post_id, '_prolific_dealer_only_tiers' );
		}

		// Save per-tier discount overrides
		$discount_tiers = isset( $_POST['prolific_dealer_discount_tier'] ) ? (array) $_POST['prolific_dealer_discount_tier'] : [];
		$clean = [];
		for ( $i = 1; $i <= 10; $i++ ) {
			if ( isset( $discount_tiers[ $i ] ) && '' !== $discount_tiers[ $i ] ) {
				$clean[ $i ] = sanitize_text_field( wp_unslash( $discount_tiers[ $i ] ) );
			}
		}

		if ( empty( $clean ) ) {
			delete_post_meta( $post_id, '_prolific_dealer_discount_tiers' );
		} else {
			update_post_meta( $post_id, '_prolific_dealer_discount_tiers', $clean );
		}

		// Clean up legacy single-value meta if present.
		delete_post_meta( $post_id, '_prolific_dealer_discount' );
	}

	private static function get_exclusion_meta_query() {
		$is_dealer = Prolific_Dealers::is_dealer();

		if ( $is_dealer ) {
			// Dealer: hide products where _prolific_visible_to_dealers = '0'
			// Also handle legacy _prolific_product_visibility = 'customers'
			return [
				'relation' => 'AND',
				[
					'relation' => 'OR',
					[
						'key'     => '_prolific_visible_to_dealers',
						'compare' => 'NOT EXISTS',
					],
					[
						'key'     => '_prolific_visible_to_dealers',
						'value'   => '0',
						'compare' => '!=',
					],
				],
				[
					'relation' => 'OR',
					[
						'key'     => '_prolific_product_visibility',
						'compare' => 'NOT EXISTS',
					],
					[
						'key'     => '_prolific_product_visibility',
						'value'   => 'customers',
						'compare' => '!=',
					],
				],
			];
		}

		// Non-dealer: hide products where _prolific_visible_to_customers = '0'
		// Also handle legacy _prolific_product_visibility = 'dealers' and _prolific_dealer_only = '1'
		return [
			'relation' => 'AND',
			[
				'relation' => 'OR',
				[
					'key'     => '_prolific_visible_to_customers',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_prolific_visible_to_customers',
					'value'   => '0',
					'compare' => '!=',
				],
			],
			[
				'relation' => 'OR',
				[
					'key'     => '_prolific_product_visibility',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_prolific_product_visibility',
					'value'   => 'dealers',
					'compare' => '!=',
				],
			],
			[
				'relation' => 'OR',
				[
					'key'     => '_prolific_dealer_only',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_prolific_dealer_only',
					'value'   => '1',
					'compare' => '!=',
				],
			],
		];
	}

	public static function filter_product_query( $query ) {
		$meta_query = $query->get( 'meta_query' ) ?: [];
		$meta_query[] = self::get_exclusion_meta_query();
		$query->set( 'meta_query', $meta_query );
	}

	public static function filter_shortcode_query( $query_args ) {
		$query_args['meta_query'] = $query_args['meta_query'] ?? [];
		$query_args['meta_query'][] = self::get_exclusion_meta_query();
		return $query_args;
	}

	public static function filter_product_visibility( $visible, $product_id ) {
		$is_dealer = Prolific_Dealers::is_dealer();

		if ( $is_dealer ) {
			if ( ! self::is_visible_to_dealers( $product_id ) ) {
				return false;
			}

			$allowed_tiers = get_post_meta( $product_id, '_prolific_dealer_only_tiers', true ) ?: [];
			if ( empty( $allowed_tiers ) ) {
				return $visible;
			}

			$user_tier = Prolific_Dealers_User::get_dealer_tier();
			if ( ! in_array( $user_tier, array_map( 'intval', $allowed_tiers ), true ) ) {
				return false;
			}

			return $visible;
		}

		if ( ! self::is_visible_to_customers( $product_id ) ) {
			return false;
		}

		return $visible;
	}
}
