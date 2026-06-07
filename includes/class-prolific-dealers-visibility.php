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
		add_action( 'wp', [ __CLASS__, 'block_hidden_single_product' ] );

		// Product list table column.
		add_filter( 'manage_edit-product_columns', [ __CLASS__, 'add_tier_column' ] );
		add_action( 'manage_product_posts_custom_column', [ __CLASS__, 'render_tier_column' ], 10, 2 );
		add_action( 'admin_head', [ __CLASS__, 'tier_column_css' ] );
	}

	/**
	 * Render tier checkboxes. Used by product meta box and category forms.
	 *
	 * @param array         $selected_tiers Currently selected tier IDs.
	 * @param string        $field_name     Input name attribute (without []).
	 * @param callable|null $after_cb       Optional callback( $tier_num, $is_checked ) called after each checkbox.
	 */
	public static function render_tier_checkboxes( $selected_tiers, $field_name = 'prolific_dealer_only_tiers', $after_cb = null ) {
		?>
		<p class="description"><?php esc_html_e( 'Limit to specific tiers:', 'prolific-dealers' ); ?></p>
		<?php for ( $i = 1; $i <= 10; $i++ ) :
			$tier_checked = in_array( $i, array_map( 'intval', (array) $selected_tiers ), true );
			?>
			<div style="margin:2px 0;">
				<label style="display:block;">
					<input type="checkbox" class="prolific-tier-cb" name="<?php echo esc_attr( $field_name ); ?>[]" value="<?php echo $i; ?>" <?php checked( $tier_checked ); ?> />
					<?php echo esc_html( Prolific_Dealers_Settings::get_tier_label( $i ) ); ?>
				</label>
				<?php if ( $after_cb ) { $after_cb( $i, $tier_checked ); } ?>
			</div>
		<?php endfor; ?>
		<p class="description" style="margin-top:6px;"><?php esc_html_e( 'Leave all unchecked to show to all dealers.', 'prolific-dealers' ); ?></p>
		<?php
	}

	/**
	 * Sanitize tier input from $_POST.
	 *
	 * @param array $raw Raw tier values from form submission.
	 * @return array Validated tier IDs (1–10).
	 */
	public static function sanitize_tier_input( $raw ) {
		$tiers = array_map( 'absint', (array) $raw );
		$tiers = array_filter( $tiers, function( $t ) { return $t >= 1 && $t <= 10; } );
		return array_values( $tiers );
	}

	/**
	 * Check whether the current dealer's tier is in an allowed list.
	 *
	 * @param array $allowed_tiers Tier IDs that are allowed. Empty = all tiers allowed.
	 * @return bool
	 */
	public static function passes_tier_check( $allowed_tiers ) {
		if ( empty( $allowed_tiers ) ) {
			return true;
		}
		$user_tier = Prolific_Dealers_User::get_dealer_tier();
		return in_array( $user_tier, array_map( 'intval', $allowed_tiers ), true );
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
			<?php
			self::render_tier_checkboxes( $tier_visibility, 'prolific_dealer_only_tiers', function( $i, $tier_checked ) use ( $overrides ) {
				$discount_val = isset( $overrides[ $i ] ) ? $overrides[ $i ] : '';
				?>
				<div class="prolific-tier-discount" data-tier="<?php echo $i; ?>" style="margin:2px 0 6px 24px;<?php echo ! $tier_checked ? 'display:none;' : ''; ?>">
					<label style="display:flex;align-items:center;gap:4px;">
						<?php esc_html_e( 'Discount override:', 'prolific-dealers' ); ?>
						<input type="number" name="prolific_dealer_discount_tier[<?php echo $i; ?>]"
							value="<?php echo esc_attr( $discount_val ); ?>" min="0" max="100" step="1" style="width:60px;" />
						<span>%</span>
					</label>
				</div>
				<?php
			} );
			?>
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

	public static function tier_column_css() {
		$screen = get_current_screen();
		if ( ! $screen || 'edit-product' !== $screen->id ) {
			return;
		}
		echo '<style>.column-dealer_tiers{width:120px;}</style>';
	}

	public static function add_tier_column( $columns ) {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'product_tag' === $key ) {
				$new['dealer_tiers'] = __( 'Dealer Tiers', 'prolific-dealers' );
			}
		}
		// Fallback if product_tag column doesn't exist.
		if ( ! isset( $new['dealer_tiers'] ) ) {
			$new['dealer_tiers'] = __( 'Dealer Tiers', 'prolific-dealers' );
		}
		return $new;
	}

	public static function render_tier_column( $column, $post_id ) {
		if ( 'dealer_tiers' !== $column ) {
			return;
		}

		$tiers = get_post_meta( $post_id, '_prolific_dealer_only_tiers', true ) ?: [];
		if ( empty( $tiers ) ) {
			echo '—';
			return;
		}

		$labels = array_map( [ 'Prolific_Dealers_Settings', 'get_tier_label' ], $tiers );
		echo esc_html( implode( ', ', $labels ) );
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
			$tiers = self::sanitize_tier_input( $_POST['prolific_dealer_only_tiers'] );
			update_post_meta( $post_id, '_prolific_dealer_only_tiers', $tiers );
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

		// Purge any server-side cache for this product so visibility changes take effect immediately.
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache( get_permalink( $post_id ) );
		}
		clean_post_cache( $post_id );
	}

	/**
	 * Get product IDs that should be hidden from the current user.
	 * Uses a single cheap query to find explicitly hidden products,
	 * then feeds them to post__not_in (avoids expensive LEFT JOINs).
	 */
	private static function get_hidden_product_ids() {
		global $wpdb;

		$is_dealer = Prolific_Dealers::is_dealer();

		if ( $is_dealer ) {
			// Hide products where visibility is explicitly '0' or legacy says 'customers'.
			return $wpdb->get_col(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta}
				 WHERE (meta_key = '_prolific_visible_to_dealers' AND meta_value = '0')
				    OR (meta_key = '_prolific_product_visibility' AND meta_value = 'customers')"
			);
		}

		// Non-dealer: hide products explicitly marked dealer-only.
		return $wpdb->get_col(
			"SELECT DISTINCT post_id FROM {$wpdb->postmeta}
			 WHERE (meta_key = '_prolific_visible_to_customers' AND meta_value = '0')
			    OR (meta_key = '_prolific_product_visibility' AND meta_value = 'dealers')
			    OR (meta_key = '_prolific_dealer_only' AND meta_value = '1')"
		);
	}

	public static function filter_product_query( $query ) {
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		$hidden = self::get_hidden_product_ids();
		if ( ! empty( $hidden ) ) {
			$existing = $query->get( 'post__not_in' ) ?: [];
			$query->set( 'post__not_in', array_merge( $existing, array_map( 'intval', $hidden ) ) );
		}
	}

	public static function filter_shortcode_query( $query_args ) {
		if ( current_user_can( 'manage_options' ) ) {
			return $query_args;
		}

		$hidden = self::get_hidden_product_ids();
		if ( ! empty( $hidden ) ) {
			$query_args['post__not_in'] = array_merge(
				$query_args['post__not_in'] ?? [],
				array_map( 'intval', $hidden )
			);
		}
		return $query_args;
	}

	public static function block_hidden_single_product() {
		if ( ! is_singular( 'product' ) ) {
			return;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		$product_id = get_queried_object_id();
		$is_dealer  = Prolific_Dealers::is_dealer();

		if ( $is_dealer ) {
			if ( ! self::is_visible_to_dealers( $product_id ) ) {
				wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
				exit;
			}
			$allowed_tiers = get_post_meta( $product_id, '_prolific_dealer_only_tiers', true ) ?: [];
			if ( ! self::passes_tier_check( $allowed_tiers ) ) {
				wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
				exit;
			}
			return;
		}

		if ( ! self::is_visible_to_customers( $product_id ) ) {
			wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
			exit;
		}
	}

	public static function filter_product_visibility( $visible, $product_id ) {
		if ( current_user_can( 'manage_options' ) ) {
			return $visible;
		}

		$is_dealer = Prolific_Dealers::is_dealer();

		if ( $is_dealer ) {
			if ( ! self::is_visible_to_dealers( $product_id ) ) {
				return false;
			}

			$allowed_tiers = get_post_meta( $product_id, '_prolific_dealer_only_tiers', true ) ?: [];
			if ( ! self::passes_tier_check( $allowed_tiers ) ) {
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
