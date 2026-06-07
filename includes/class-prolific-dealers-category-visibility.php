<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Prolific_Dealers_Category_Visibility {

	const META_CUSTOMERS = '_prolific_cat_visible_to_customers';
	const META_DEALERS   = '_prolific_cat_visible_to_dealers';
	const META_TIERS     = '_prolific_cat_dealer_only_tiers';
	const MENU_ID        = 168;

	public static function init() {
		// Category taxonomy fields.
		add_action( 'product_cat_add_form_fields', [ __CLASS__, 'render_add_fields' ] );
		add_action( 'product_cat_edit_form_fields', [ __CLASS__, 'render_edit_fields' ] );
		add_action( 'created_product_cat', [ __CLASS__, 'save_fields' ] );
		add_action( 'edited_product_cat', [ __CLASS__, 'save_fields' ] );

		// Dynamic menu items.
		add_filter( 'wp_get_nav_menu_items', [ __CLASS__, 'append_category_menu_items' ], 20, 3 );

		// Admin JS for tier toggle.
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_category_js' ] );
	}

	/**
	 * Whether a category is visible to customers.
	 */
	public static function is_visible_to_customers( $term_id ) {
		$val = get_term_meta( $term_id, self::META_CUSTOMERS, true );
		return '' === $val || '1' === $val;
	}

	/**
	 * Whether a category is visible to dealers.
	 */
	public static function is_visible_to_dealers( $term_id ) {
		$val = get_term_meta( $term_id, self::META_DEALERS, true );
		return '' === $val || '1' === $val;
	}

	/**
	 * "Add New Category" form fields.
	 */
	public static function render_add_fields() {
		?>
		<div class="form-field">
			<label><?php esc_html_e( 'Visibility', 'prolific-dealers' ); ?></label>
			<label style="display:block;margin:4px 0;">
				<input type="checkbox" name="prolific_cat_visible_to_customers" value="1" checked />
				<?php esc_html_e( 'Customers', 'prolific-dealers' ); ?>
			</label>
			<label style="display:block;margin:4px 0;">
				<input type="checkbox" id="prolific_cat_visible_to_dealers_cb" name="prolific_cat_visible_to_dealers" value="1" checked />
				<?php esc_html_e( 'Dealers', 'prolific-dealers' ); ?>
			</label>
			<div id="prolific_cat_dealer_tiers" style="margin:6px 0 0 24px;">
				<?php Prolific_Dealers_Visibility::render_tier_checkboxes( [], 'prolific_cat_dealer_only_tiers' ); ?>
			</div>
			<p class="description"><?php esc_html_e( 'Who can see this category in the menu?', 'prolific-dealers' ); ?></p>
		</div>
		<?php
	}

	/**
	 * "Edit Category" form fields.
	 */
	public static function render_edit_fields( $term ) {
		$show_customers  = self::is_visible_to_customers( $term->term_id );
		$show_dealers    = self::is_visible_to_dealers( $term->term_id );
		$tier_visibility = get_term_meta( $term->term_id, self::META_TIERS, true ) ?: [];
		?>
		<tr class="form-field">
			<th scope="row"><label><?php esc_html_e( 'Visibility', 'prolific-dealers' ); ?></label></th>
			<td>
				<label style="display:block;margin:4px 0;">
					<input type="checkbox" name="prolific_cat_visible_to_customers" value="1" <?php checked( $show_customers ); ?> />
					<?php esc_html_e( 'Customers', 'prolific-dealers' ); ?>
				</label>
				<label style="display:block;margin:4px 0;">
					<input type="checkbox" id="prolific_cat_visible_to_dealers_cb" name="prolific_cat_visible_to_dealers" value="1" <?php checked( $show_dealers ); ?> />
					<?php esc_html_e( 'Dealers', 'prolific-dealers' ); ?>
				</label>
				<div id="prolific_cat_dealer_tiers" style="margin:6px 0 0 24px;<?php echo ! $show_dealers ? 'display:none;' : ''; ?>">
					<?php Prolific_Dealers_Visibility::render_tier_checkboxes( $tier_visibility, 'prolific_cat_dealer_only_tiers' ); ?>
				</div>
				<p class="description"><?php esc_html_e( 'Who can see this category in the menu?', 'prolific-dealers' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save visibility fields for a product category.
	 */
	public static function save_fields( $term_id ) {
		if ( ! current_user_can( 'manage_product_terms' ) ) {
			return;
		}

		$customers = ! empty( $_POST['prolific_cat_visible_to_customers'] ) ? '1' : '0';
		$dealers   = ! empty( $_POST['prolific_cat_visible_to_dealers'] ) ? '1' : '0';

		update_term_meta( $term_id, self::META_CUSTOMERS, $customers );
		update_term_meta( $term_id, self::META_DEALERS, $dealers );

		if ( '1' === $dealers && ! empty( $_POST['prolific_cat_dealer_only_tiers'] ) ) {
			$tiers = Prolific_Dealers_Visibility::sanitize_tier_input( $_POST['prolific_cat_dealer_only_tiers'] );
			update_term_meta( $term_id, self::META_TIERS, $tiers );
		} else {
			delete_term_meta( $term_id, self::META_TIERS );
		}
	}

	/**
	 * Build a fake menu item object for a product category.
	 */
	private static function make_menu_item( $cat, $menu_order, $parent_db_id = 0 ) {
		$fake_id = PHP_INT_MAX - $cat->term_id;

		$item                   = new stdClass();
		$item->ID               = $fake_id;
		$item->db_id            = $fake_id;
		$item->object_id        = $cat->term_id;
		$item->object           = 'product_cat';
		$item->type             = 'taxonomy';
		$item->type_label       = 'Product Category';
		$item->title            = $cat->name;
		$item->url              = get_term_link( $cat );
		$item->menu_item_parent = $parent_db_id;
		$item->menu_order       = $menu_order;
		$item->target           = '';
		$item->attr_title       = '';
		$item->description      = '';
		$item->classes          = [ 'menu-item', 'menu-item-type-taxonomy', 'menu-item-object-product_cat' ];
		$item->xfn              = '';
		$item->post_type        = 'nav_menu_item';
		$item->post_status      = 'publish';

		return $item;
	}

	/**
	 * Append visible product categories as menu items.
	 *
	 * Dealer-only categories (visible to dealers, NOT customers) go under
	 * a "Dealers Only" parent dropdown — shown only to dealers/admins.
	 *
	 * Customer-visible categories are appended flat at the end, skipping
	 * any already in the menu or already in the dealer dropdown.
	 */
	public static function append_category_menu_items( $items, $menu, $args ) {
		if ( (int) $menu->term_id !== self::MENU_ID ) {
			return $items;
		}

		if ( is_admin() ) {
			return $items;
		}

		$is_admin_user = current_user_can( 'manage_options' );
		$is_dealer     = Prolific_Dealers::is_dealer();

		// Collect term IDs already manually in the menu.
		$existing_cat_ids = [];
		foreach ( $items as $menu_item ) {
			if ( 'taxonomy' === $menu_item->type && 'product_cat' === $menu_item->object ) {
				$existing_cat_ids[] = (int) $menu_item->object_id;
			}
		}

		$categories = get_terms( [
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'exclude'    => $existing_cat_ids,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );

		if ( is_wp_error( $categories ) || empty( $categories ) ) {
			return $items;
		}

		// Sort categories into buckets.
		$dealer_only = []; // visible to dealers but NOT customers
		$customer    = []; // visible to customers (may also be visible to dealers)

		foreach ( $categories as $cat ) {
			$to_customers = self::is_visible_to_customers( $cat->term_id );
			$to_dealers   = self::is_visible_to_dealers( $cat->term_id );

			if ( $is_admin_user ) {
				// Admins see everything — dealer-only goes in the dropdown, rest goes flat.
				if ( $to_dealers && ! $to_customers ) {
					$dealer_only[] = $cat;
				} else {
					$customer[] = $cat;
				}
			} elseif ( $to_dealers && ! $to_customers ) {
				$allowed_tiers = get_term_meta( $cat->term_id, self::META_TIERS, true ) ?: [];
				if ( Prolific_Dealers_Visibility::passes_tier_check( $allowed_tiers ) ) {
					$dealer_only[] = $cat;
				}
			} elseif ( $to_customers ) {
				$customer[] = $cat;
			}
		}

		// Build dynamic items first, then push existing items after.
		$dynamic     = [];
		$menu_order  = 1;

		// Flat customer-visible categories first.
		$dealer_only_ids = [];
		foreach ( $customer as $cat ) {
			$dynamic[] = self::make_menu_item( $cat, $menu_order++ );
		}

		// "Dealers Only" parent + children — only for dealers and admins.
		if ( ! empty( $dealer_only ) && ( $is_dealer || $is_admin_user ) ) {
			$parent_id = PHP_INT_MAX;

			$parent                   = new stdClass();
			$parent->ID               = $parent_id;
			$parent->db_id            = $parent_id;
			$parent->object_id        = 0;
			$parent->object           = 'custom';
			$parent->type             = 'custom';
			$parent->type_label       = 'Custom Link';
			$parent->title            = __( 'Dealers Only', 'prolific-dealers' );
			$parent->url              = '#';
			$parent->menu_item_parent = 0;
			$parent->menu_order       = $menu_order++;
			$parent->target           = '';
			$parent->attr_title       = '';
			$parent->description      = '';
			$parent->classes          = [ 'menu-item', 'menu-item-has-children', 'menu-item-type-custom' ];
			$parent->xfn              = '';
			$parent->post_type        = 'nav_menu_item';
			$parent->post_status      = 'publish';

			$dynamic[] = $parent;

			foreach ( $dealer_only as $cat ) {
				$dynamic[] = self::make_menu_item( $cat, $menu_order++, $parent_id );
				$dealer_only_ids[] = $cat->term_id;
			}
		}

		// Bump existing items so they come after dynamic ones.
		foreach ( $items as $item ) {
			$item->menu_order = $menu_order++;
		}

		return array_merge( $dynamic, $items );
	}

	public static function enqueue_category_js( $hook ) {
		if ( 'edit-tags.php' !== $hook && 'term.php' !== $hook ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'product_cat' !== $screen->taxonomy ) {
			return;
		}

		$js = "jQuery(function($){
			var dealerCb = $('#prolific_cat_visible_to_dealers_cb');
			var tierWrap = $('#prolific_cat_dealer_tiers');

			dealerCb.on('change', function(){
				tierWrap.toggle(dealerCb.is(':checked'));
			});
		});";

		wp_add_inline_script( 'jquery', $js );
	}
}
