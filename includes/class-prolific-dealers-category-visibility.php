<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Prolific_Dealers_Category_Visibility {

	const META_CUSTOMERS = '_prolific_cat_visible_to_customers';
	const META_DEALERS   = '_prolific_cat_visible_to_dealers';
	const MENU_ID        = 2026;

	public static function init() {
		// Category taxonomy fields.
		add_action( 'product_cat_add_form_fields', [ __CLASS__, 'render_add_fields' ] );
		add_action( 'product_cat_edit_form_fields', [ __CLASS__, 'render_edit_fields' ] );
		add_action( 'created_product_cat', [ __CLASS__, 'save_fields' ] );
		add_action( 'edited_product_cat', [ __CLASS__, 'save_fields' ] );

		// Dynamic menu items.
		add_filter( 'wp_get_nav_menu_items', [ __CLASS__, 'append_category_menu_items' ], 20, 3 );
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
				<input type="checkbox" name="prolific_cat_visible_to_dealers" value="1" checked />
				<?php esc_html_e( 'Dealers', 'prolific-dealers' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Who can see this category in the menu?', 'prolific-dealers' ); ?></p>
		</div>
		<?php
	}

	/**
	 * "Edit Category" form fields.
	 */
	public static function render_edit_fields( $term ) {
		$show_customers = self::is_visible_to_customers( $term->term_id );
		$show_dealers   = self::is_visible_to_dealers( $term->term_id );
		?>
		<tr class="form-field">
			<th scope="row"><label><?php esc_html_e( 'Visibility', 'prolific-dealers' ); ?></label></th>
			<td>
				<label style="display:block;margin:4px 0;">
					<input type="checkbox" name="prolific_cat_visible_to_customers" value="1" <?php checked( $show_customers ); ?> />
					<?php esc_html_e( 'Customers', 'prolific-dealers' ); ?>
				</label>
				<label style="display:block;margin:4px 0;">
					<input type="checkbox" name="prolific_cat_visible_to_dealers" value="1" <?php checked( $show_dealers ); ?> />
					<?php esc_html_e( 'Dealers', 'prolific-dealers' ); ?>
				</label>
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
	}

	/**
	 * Append visible product categories as menu items to menu 2026.
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

		// Collect term IDs already in the menu to avoid duplicates.
		$existing_cat_ids = [];
		foreach ( $items as $menu_item ) {
			if ( 'taxonomy' === $menu_item->type && 'product_cat' === $menu_item->object ) {
				$existing_cat_ids[] = (int) $menu_item->object_id;
			}
		}

		$categories = get_terms( [
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'exclude'    => array_merge( $existing_cat_ids, [ get_option( 'default_product_cat', 0 ) ] ),
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );

		if ( is_wp_error( $categories ) || empty( $categories ) ) {
			return $items;
		}

		$menu_order = count( $items ) + 1;

		foreach ( $categories as $cat ) {
			if ( ! $is_admin_user ) {
				if ( $is_dealer && ! self::is_visible_to_dealers( $cat->term_id ) ) {
					continue;
				}
				if ( ! $is_dealer && ! self::is_visible_to_customers( $cat->term_id ) ) {
					continue;
				}
			}

			$item                   = new stdClass();
			$fake_id                = PHP_INT_MAX - $cat->term_id;
			$item->ID               = $fake_id;
			$item->db_id            = $fake_id;
			$item->object_id        = $cat->term_id;
			$item->object           = 'product_cat';
			$item->type             = 'taxonomy';
			$item->type_label       = 'Product Category';
			$item->title            = $cat->name;
			$item->url              = get_term_link( $cat );
			$item->menu_item_parent = 0;
			$item->menu_order       = $menu_order++;
			$item->target           = '';
			$item->attr_title       = '';
			$item->description      = '';
			$item->classes          = [ 'menu-item', 'menu-item-type-taxonomy', 'menu-item-object-product_cat' ];
			$item->xfn              = '';
			$item->post_type        = 'nav_menu_item';
			$item->post_status      = 'publish';

			$items[] = $item;
		}

		return $items;
	}
}
