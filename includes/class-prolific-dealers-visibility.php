<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Prolific_Dealers_Visibility {

	public static function init() {
		add_action( 'add_meta_boxes', [ __CLASS__, 'register_meta_box' ] );
		add_action( 'save_post_product', [ __CLASS__, 'save_meta_box' ] );
		add_action( 'pre_get_posts', [ __CLASS__, 'hide_dealer_only_products' ] );
		add_filter( 'woocommerce_product_is_visible', [ __CLASS__, 'filter_product_visibility' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_meta_box_js' ] );
	}

	public static function register_meta_box() {
		add_meta_box(
			'prolific_dealer_only',
			'Dealer Only',
			[ __CLASS__, 'render_meta_box' ],
			'product',
			'side',
			'default'
		);
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'prolific_dealer_only', 'prolific_dealer_only_nonce' );
		$checked    = get_post_meta( $post->ID, '_prolific_dealer_only', true );
		$tier_visibility = get_post_meta( $post->ID, '_prolific_dealer_only_tiers', true ) ?: [];
		?>
		<label>
			<input type="checkbox" name="prolific_dealer_only" id="prolific_dealer_only" value="1" <?php checked( $checked, '1' ); ?> />
			<?php esc_html_e( 'Restrict this product to dealers only', 'prolific-dealers' ); ?>
		</label>
		<div id="prolific_dealer_only_tiers" style="margin-top:10px;<?php echo '1' !== $checked ? 'display:none;' : ''; ?>">
			<p class="description"><?php esc_html_e( 'Show this product to specific tiers:', 'prolific-dealers' ); ?></p>
			<?php for ( $i = 1; $i <= 10; $i++ ) :
				$tier_checked = in_array( $i, array_map( 'intval', $tier_visibility ), true );
				?>
				<label style="display:block;margin:2px 0;">
					<input type="checkbox" name="prolific_dealer_only_tiers[]" value="<?php echo $i; ?>" <?php checked( $tier_checked ); ?> />
					<?php printf( esc_html__( 'Tier %d', 'prolific-dealers' ), $i ); ?>
				</label>
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

		$js = "
			console.log('Prolific Dealers: JS loaded');
			jQuery(function($){
				console.log('Prolific Dealers: DOM ready');
				var cb = $('#prolific_dealer_only');
				var tiers = $('#prolific_dealer_only_tiers');
				console.log('Prolific Dealers: checkbox found:', cb.length);
				console.log('Prolific Dealers: tiers div found:', tiers.length);
				cb.on('change', function(){
					console.log('Prolific Dealers: checkbox changed, checked:', cb.is(':checked'));
					tiers.toggle(cb.is(':checked'));
				});
			});
		";

		wp_add_inline_script( 'jquery', $js );
	}

	public static function save_meta_box( $post_id ) {
		if ( ! isset( $_POST['prolific_dealer_only_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_POST['prolific_dealer_only_nonce'], 'prolific_dealer_only' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$value = isset( $_POST['prolific_dealer_only'] ) ? '1' : '';
		update_post_meta( $post_id, '_prolific_dealer_only', $value );

		if ( '1' === $value && ! empty( $_POST['prolific_dealer_only_tiers'] ) ) {
			$tiers = array_map( 'absint', (array) $_POST['prolific_dealer_only_tiers'] );
			$tiers = array_filter( $tiers, function( $t ) { return $t >= 1 && $t <= 10; } );
			update_post_meta( $post_id, '_prolific_dealer_only_tiers', array_values( $tiers ) );
		} else {
			delete_post_meta( $post_id, '_prolific_dealer_only_tiers' );
		}
	}

	public static function hide_dealer_only_products( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( Prolific_Dealers::is_dealer() ) {
			return;
		}

		$post_type = $query->get( 'post_type' );
		if ( 'product' !== $post_type && ! $query->is_post_type_archive( 'product' ) && ! $query->is_tax( get_object_taxonomies( 'product' ) ) ) {
			return;
		}

		$meta_query = $query->get( 'meta_query' ) ?: [];
		$meta_query[] = [
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
		];
		$query->set( 'meta_query', $meta_query );
	}

	public static function filter_product_visibility( $visible, $product_id ) {
		$dealer_only = get_post_meta( $product_id, '_prolific_dealer_only', true );

		if ( '1' !== $dealer_only ) {
			return $visible;
		}

		if ( ! Prolific_Dealers::is_dealer() ) {
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
}
