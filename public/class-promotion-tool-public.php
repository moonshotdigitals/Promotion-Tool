<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://with-influence.ae
 * @since      1.0.0
 *
 * @package    Promotion_Tool
 * @subpackage Promotion_Tool/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Promotion_Tool
 * @subpackage Promotion_Tool/public
 * @author     Moonshot <info@moonshot.digital>
 */
class Promotion_Tool_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

		add_action('woocommerce_before_calculate_totals', [$this, 'pt_apply_bogo_rules_to_cart'], 20, 1);

		add_filter('woocommerce_cart_item_quantity', [$this,'pt_lock_bogo_bonus_quantity_input'], 10, 3);

		// add_filter('woocommerce_cart_item_remove_link', [$this,'pt_disable_remove_for_bonus_products'], 10, 2);

		// add_action('woocommerce_after_cart_item_quantity_update', [$this,'pt_force_bonus_product_quantity'], 10, 4);

		add_filter('woocommerce_cart_item_quantity', [$this, 'add_product_id_to_cart_qty_input'], 10, 3);

		add_filter('woocommerce_cart_item_name', [$this,'pt_show_bogo_label_in_cart'], 10, 3);

	}

	public function pt_show_bogo_label_in_cart_old($name, $cart_item, $cart_item_key) {
	    if (!empty($cart_item['pt_bogo_rule_label'])) {
	        $label = esc_html($cart_item['pt_bogo_rule_label']);
	        $name .= '<div class="pt-bogo-label" style="color: #38a169; font-size: 12px;">(Offer: ' . $label . ')</div>';
	    }

	    return $name;
	}

	public function pt_show_bogo_label_in_cart($name, $cart_item, $cart_item_key) {
	    // If no label exists, skip
	    if (empty($cart_item['pt_bogo_rule_label']) || empty($cart_item['pt_bogo_rule_id'])) {
	        return $name;
	    }

	    if (empty($cart_item['pt_bogo_rule_label']) || empty($cart_item['pt_bogo_rule_id']) || empty($cart_item['pt_bogo_applied']) || $cart_item['pt_bogo_applied'] !== true) {
	        return $name; // not a valid BOGO bonus product
	    }

	    $rule_id = $cart_item['pt_bogo_rule_id'];
	    $rules = $this->pt_get_active_bogo_rules();
	    $rule = null;

	    // Find the matching rule by ID
	    foreach ($rules as $r) {
	        if ($r['id'] == $rule_id) {
	            $rule = $r;
	            break;
	        }
	    }

	    // If rule is no longer active or not found, remove the label
	    if (!$rule) {
	        unset(WC()->cart->cart_contents[$cart_item_key]['pt_bogo_rule_label']);
	        unset(WC()->cart->cart_contents[$cart_item_key]['pt_bogo_rule_id']);
	        return $name;
	    }

	    // Check if rule condition is still satisfied
	    $required_qty = intval($rule['qty']);
	    $buy_products = (array) $rule['buy_products'];
	    $buy_count = 0;

	    foreach (WC()->cart->get_cart() as $item) {
	        if (in_array($item['product_id'], $buy_products)) {
	            $buy_count += $item['quantity'];
	        }
	    }

	    // If buy condition is not satisfied, remove the label
	    if ($buy_count < $required_qty) {
	        unset(WC()->cart->cart_contents[$cart_item_key]['pt_bogo_rule_label']);
	        unset(WC()->cart->cart_contents[$cart_item_key]['pt_bogo_rule_id']);
	        return $name;
	    }

	    // ✅ All conditions met, show label
	    $label = esc_html($cart_item['pt_bogo_rule_label']);
	    $name .= '<div class="pt-bogo-label" style="color: #38a169; font-size: 12px;">(Offer: ' . $label . ')</div>';

	    $name .= '<span class="pt-bogo-rule-id" style="opacity:0;">'.esc_attr($cart_item['pt_bogo_rule_id']).'</span>';

	    return $name;
	}

	public function add_product_id_to_cart_qty_input($product_quantity, $cart_item_key, $cart_item) {
        $product_id = $cart_item['product_id'];
        $product_quantity = str_replace(
            '<input',
            '<input data-product_id="' . esc_attr($product_id) . '"',
            $product_quantity
        );
        return $product_quantity;
    }

	public function pt_lock_bogo_bonus_quantity_input_old($quantity_html, $cart_item_key, $cart_item) {
	    if (!empty($cart_item['pt_bogo_rule_id'])) {
	        return '<span class="pt-bogo-locked-qty">' . esc_html($cart_item['quantity']) . '</span>';
	    }
	    return $quantity_html;
	}

	public function pt_lock_bogo_bonus_quantity_input($quantity_html, $cart_item_key, $cart_item) {
	    // If it's not a BOGO reward, don't interfere
	    if (empty($cart_item['pt_bogo_rule_id'])) {
	        return $quantity_html;
	    }

	    if (empty($cart_item['pt_bogo_rule_label']) || empty($cart_item['pt_bogo_rule_id']) || empty($cart_item['pt_bogo_applied']) || $cart_item['pt_bogo_applied'] !== true) {
	        return $quantity_html; // not a valid BOGO bonus product
	    }

	    $rule_id = $cart_item['pt_bogo_rule_id'];
	    $rules = $this->pt_get_active_bogo_rules();
	    $rule = null;

	    // Find the matching rule
	    foreach ($rules as $r) {
	        if ($r['id'] == $rule_id) {
	            $rule = $r;
	            break;
	        }
	    }

	    // If rule no longer exists, unlock quantity and clean up
	    if (!$rule) {
	        unset(WC()->cart->cart_contents[$cart_item_key]['pt_bogo_rule_id']);
	        unset(WC()->cart->cart_contents[$cart_item_key]['pt_bogo_rule_label']);
	        return $quantity_html;
	    }

	    // Check if rule's Buy condition is still met
	    $required_qty = intval($rule['qty']);
	    $buy_products = (array) $rule['buy_products'];
	    $buy_count = 0;

	    foreach (WC()->cart->get_cart() as $item) {
	        if (in_array($item['product_id'], $buy_products)) {
	            $buy_count += $item['quantity'];
	        }
	    }

	    // If Buy condition is not met anymore, unlock and remove BOGO flags
	    if ($buy_count < $required_qty) {
	        unset(WC()->cart->cart_contents[$cart_item_key]['pt_bogo_rule_id']);
	        unset(WC()->cart->cart_contents[$cart_item_key]['pt_bogo_rule_label']);
	        return $quantity_html;
	    }

	    // ✅ Lock the quantity (BOGO rule is still valid)
	    return '<span class="pt-bogo-locked-qty">' . esc_html($cart_item['quantity']) . '</span>';
	}

	public function pt_disable_remove_for_bonus_products($link, $cart_item_key) {
	    $cart = WC()->cart->get_cart();
	    if (!empty($cart[$cart_item_key]['pt_bogo_rule_id'])) {
	        return $link.'<span class="pt-bogo-locked-remove" title="Bonus product can’t be removed">🎁</span>';
	    }
	    return $link;
	}

	public function pt_force_bonus_product_quantity($cart_item_key, $quantity, $old_quantity, $cart) {
	    if (!empty($cart->cart_contents[$cart_item_key]['pt_bogo_rule_id']) && $quantity !== 1) {
	        $cart->cart_contents[$cart_item_key]['quantity'] = 1;
	        wc_add_notice(__('Bonus product quantity reset to 1.'), 'notice');
	    }
	}



	public function pt_get_active_bogo_rules() {
	    $args = [
	        'post_type'      => 'pt_bogo_rule',
	        'posts_per_page' => -1,
	        'post_status'    => 'publish'
	    ];

	    $rules = get_posts($args);

	    $active_rules = [];

	    $now = current_time('Y-m-d');

	    foreach ($rules as $rule) {

	    	$start_date = get_post_meta($rule->ID, '_pt_start_date', true);
    		$end_date   = get_post_meta($rule->ID, '_pt_end_date', true);

    		if ((!$start_date || $start_date <= $now) && (!$end_date || $end_date >= $now)) {

		        $active_rules[] = [
		            'id'             => $rule->ID,
		            'title'        	 => get_the_title($rule->ID),
		            'bogo_type'      => get_post_meta($rule->ID, '_pt_bogo_type', true),
		            'buy_products'   => get_post_meta($rule->ID, '_pt_buy_products', true),
		            'get_products'   => get_post_meta($rule->ID, '_pt_get_products', true),
		            'buy_category'   => get_post_meta($rule->ID, '_pt_buy_category', true),
		            'get_category'   => get_post_meta($rule->ID, '_pt_get_category', true),
		            'discount'       => floatval(get_post_meta($rule->ID, '_pt_discount_amount', true)),
		            'priority'       => intval(get_post_meta($rule->ID, '_pt_priority', true)),
		            'qty'       => intval(get_post_meta($rule->ID, '_pt_x_qty', true))
		        ];
		    }
	    }

	    return $active_rules;
	}

	public function pt_apply_bogo_rules_to_cart($cart) {

	    // if (is_admin() || did_action('woocommerce_before_calculate_totals') >= 2) return;

	    $rules = $this->pt_get_active_bogo_rules();
	    if (empty($rules)) return;

	    $cart_items = $cart->get_cart();

	    foreach ($rules as $rule) {
	        switch ($rule['bogo_type']) {
	            case 'bogo_free':
	                $this->pt_handle_bogo_free($cart, $rule);
	                break;
	            case 'bogo_percent':
	                $this->pt_handle_bogo_percent($cart, $rule);
	                break;
	            case 'category_bogo':
	            	// $this->pt_handle_bogo_free_by_category($cart, $rule);
	                break;
	            // You can add more: tiered_bogo, category_bogo etc.
	        }
	    }
	}

	public function pt_handle_bogo_free_old($cart, $rule) {
	    $buy_ids = $rule['buy_products'];
	    $get_ids = $rule['get_products'];

	    foreach ($cart->get_cart() as $key => $item) {
	        if (in_array($item['product_id'], $buy_ids)) {
	            foreach ($cart->get_cart() as $get_key => $get_item) {
	                if (in_array($get_item['product_id'], $get_ids) && $get_item['product_id'] !== $item['product_id']) {
	                    $cart->cart_contents[$get_key]['data']->set_price(0);
	                }
	            }
	        }
	    }
	}

	public function pt_handle_bogo_free($cart, $rule) {
	    $buy_ids      = (array) $rule['buy_products'];
	    $get_ids      = (array) $rule['get_products'];
	    $required_qty = intval($rule['qty']);

	    $buy_count = 0;
	    $get_items = [];

	    // Count Buy products and collect valid Get items only if applied via BOGO
	    foreach ($cart->get_cart() as $cart_item_key => $item) {
	        $product_id = $item['product_id'];
	        $quantity   = $item['quantity'];

	        if (in_array($product_id, $buy_ids)) {
	            $buy_count += $quantity;
	        }

	        if (
	            in_array($product_id, $get_ids) &&
	            !empty($item['pt_bogo_applied']) &&
	            $item['pt_bogo_applied'] === true
	        ) {
	            $get_items[] = $cart_item_key;
	        }
	    }

	    if ($buy_count >= $required_qty) {
	        foreach ($get_items as $get_key) {
	            $cart->cart_contents[$get_key]['data']->set_price(0);
	            $cart->cart_contents[$get_key]['pt_bogo_rule_id'] = $rule['id'];
	            $cart->cart_contents[$get_key]['pt_bogo_rule_label'] = $rule['title'];
	        }
	    } else {
	        // Restore original price if rule is no longer satisfied
	        foreach ($get_items as $get_key) {
	            $product = $cart->cart_contents[$get_key]['data'];
	            $original_price = $product->get_sale_price() ?: $product->get_regular_price();
	            $product->set_price($original_price);

	            unset($cart->cart_contents[$get_key]['pt_bogo_rule_id']);
	            unset($cart->cart_contents[$get_key]['pt_bogo_rule_label']);
	        }
	    }
	}

	public function pt_handle_bogo_free_old_again($cart, $rule) {
	    $buy_ids = $rule['buy_products'];       // array of product IDs to buy
	    $get_ids = $rule['get_products'];       // array of product IDs to get for free
	    $required_qty = intval($rule['qty']); // required quantity of Buy products

	    $buy_count = 0;
	    $get_items = [];

	    // First pass: count total quantity of Buy products and collect eligible Get items
	    foreach ($cart->get_cart() as $key => $item) {
	        $product_id = $item['product_id'];
	        $quantity = $item['quantity'];

	        if (in_array($product_id, $buy_ids)) {
	            $buy_count += $quantity;
	        }

	        if (in_array($product_id, $get_ids)) {
	            $get_items[] = $key;
	        }
	    }

	    // If the required Buy quantity is met, apply free pricing to Get products
	    if ($buy_count >= $required_qty) {
	        foreach ($get_items as $get_key) {
	            $cart->cart_contents[$get_key]['data']->set_price(0);
	            $cart->cart_contents[$get_key]['pt_bogo_rule_id'] = $rule['id'] ?? 'bogo_free';
	            $cart->cart_contents[$get_key]['pt_bogo_rule_label'] = $rule['title'];
	        }
	    } else {
	        // Not eligible: restore original price if needed
	        foreach ($get_items as $get_key) {
	            $product = $cart->cart_contents[$get_key]['data'];
	            if (method_exists($product, 'get_regular_price')) {
	                // $cart->cart_contents[$get_key]['data']->set_price($product->get_regular_price());
	                $cart->cart_contents[$get_key]['pt_bogo_rule_label'] = $rule['title'];
	            }
	        }
	    }
	}

	public function pt_handle_bogo_percent_old($cart, $rule) {
	    $buy_ids   = $rule['buy_products'];
	    $get_ids   = $rule['get_products'];
	    $discount  = $rule['discount'];

	    foreach ($cart->get_cart() as $key => $item) {
	        // Check if this item qualifies as a 'buy' product
	        if (in_array($item['product_id'], $buy_ids)) {
	            foreach ($cart->get_cart() as $get_key => $get_item) {
	                // Only apply to 'get' items that match and are not the same as the buy item
	                if (in_array($get_item['product_id'], $get_ids)) {
	                    // Get current (already discounted, if any) price
	                    $current_price = $cart->cart_contents[$get_key]['data']->get_price();
	                    $new_price = $current_price - ($current_price * ($discount / 100));

	                    // Apply new discounted price
	                    $cart->cart_contents[$get_key]['data']->set_price($new_price);

	                    // Optionally mark it to prevent quantity changes
	                    $cart->cart_contents[$get_key]['pt_bogo_rule_id'] = $rule['id'];
	                }
	            }
	        }
	    }
	}

	public function pt_handle_bogo_percent($cart, $rule) {
	    $buy_ids   = $rule['buy_products'];
	    $get_ids   = $rule['get_products'];
	    $discount  = floatval($rule['discount']);
	    $required_qty = intval($rule['qty']); // Make sure this exists in your rule definition

	    $buy_count = 0;
	    $eligible_get_items = [];

	    // First, count total Buy product quantity and collect valid Get items
	    foreach ($cart->get_cart() as $cart_item_key => $item) {
	        $product_id = $item['product_id'];
	        $quantity   = $item['quantity'];

	        if (in_array($product_id, $buy_ids)) {
	            $buy_count += $quantity;
	        }

	        if (
	            in_array($product_id, $get_ids) &&
	            !empty($item['pt_bogo_applied']) && $item['pt_bogo_applied'] === true
	        ) {
	            $eligible_get_items[] = $cart_item_key;
	        }
	    }

	    // Apply discount if Buy quantity is satisfied
	    if ($buy_count >= $required_qty) {
	        foreach ($eligible_get_items as $get_key) {
	            $product = $cart->cart_contents[$get_key]['data'];
	            $original_price = $product->get_sale_price() ?: $product->get_regular_price();
	            $new_price = $original_price - ($original_price * ($discount / 100));

	            $product->set_price($new_price);

	            // Mark the item with BOGO rule ID (optional for later use)
	            $cart->cart_contents[$get_key]['pt_bogo_rule_id'] = $rule['id'];
	            $cart->cart_contents[$get_key]['pt_bogo_rule_label'] = $rule['title'];
	        }
	    } else {
	        // Condition not satisfied anymore, restore original price
	        foreach ($eligible_get_items as $get_key) {
	            $product = $cart->cart_contents[$get_key]['data'];

	            if (method_exists($product, 'get_regular_price')) {
	                $original_price = $product->get_sale_price() ?: $product->get_regular_price();
	                $product->set_price($original_price);
	            }

	            unset($cart->cart_contents[$get_key]['pt_bogo_rule_id']);
	            unset($cart->cart_contents[$get_key]['pt_bogo_rule_label']);
	        }
	    }
	}

	public function pt_handle_bogo_free_by_category($cart, $rule) {
	    $buy_categories = [];
	    $get_categories = [];

	    // Safely unserialize and normalize category arrays
	    if (!empty($rule['buy_category']) && is_array($rule['buy_category'])) {
	        foreach ($rule['buy_category'] as $raw) {
	            $val = maybe_unserialize($raw);
	            if (is_array($val)) {
	                $buy_categories = array_merge($buy_categories, $val);
	            } elseif (!is_null($val)) {
	                $buy_categories[] = $val;
	            }
	        }
	    }

	    if (!empty($rule['get_category']) && is_array($rule['get_category'])) {
	        foreach ($rule['get_category'] as $raw) {
	            $val = maybe_unserialize($raw);
	            if (is_array($val)) {
	                $get_categories = array_merge($get_categories, $val);
	            } elseif (!is_null($val)) {
	                $get_categories[] = $val;
	            }
	        }
	    }

	    $has_buy_product = false;
	    $eligible_get_items = [];

	    // Step 1: Check for buy category product presence and collect get-category products
	    foreach ($cart->get_cart() as $key => $item) {
	        $product_id = $item['product_id'];
	        $product = wc_get_product($product_id);
	        $terms = get_the_terms($product_id, 'product_cat');
	        $product_cats = $terms ? wp_list_pluck($terms, 'term_id') : [];

	        // Check for any product from buy category
	        if (array_intersect($buy_categories, $product_cats)) {
	            $has_buy_product = true;
	        }

	        // Collect eligible get-category products currently in cart
	        if (array_intersect($get_categories, $product_cats)) {
	            $eligible_get_items[$key] = floatval($product->get_price());
	        }
	    }

	    // Step 2: If matched, apply free price to the cheapest get-category product in cart
	    if ($has_buy_product && !empty($eligible_get_items)) {
	        $cheapest_key = array_keys($eligible_get_items, min($eligible_get_items))[0];
	        $cart->cart_contents[$cheapest_key]['data']->set_price(0);

	        // Optional: flag it with the rule ID for later reference
	        $cart->cart_contents[$cheapest_key]['pt_bogo_rule_id'] = $rule['id'];
	    }
	}

	public function pt_handle_bogo_free_by_category_old($cart, $rule) {
	    $buy_categories = $rule['buy_category']; // array of slugs or IDs
	    $get_categories = $rule['get_category']; // array of slugs or IDs

	    $has_buy_product = false;
	    $eligible_get_items = [];

	    // Step 1: Check for buy category product presence and collect eligible get category items
	    foreach ($cart->get_cart() as $key => $item) {
	        $product_id = $item['product_id'];
	        $product = wc_get_product($product_id);
	        $terms = get_the_terms($product_id, 'product_cat');
	        $product_cats = $terms ? wp_list_pluck($terms, 'slug') : [];

	        // Check if product is from buy category
	        if (array_intersect($buy_categories, $product_cats)) {
	            $has_buy_product = true;
	        }

	        // Collect items from get category for possible discount
	        if (array_intersect($get_categories, $product_cats)) {
	            $eligible_get_items[$key] = $product->get_price();
	        }
	    }

	    // Step 2: If rule matched, apply free price to cheapest get-category product
	    if ($has_buy_product && !empty($eligible_get_items)) {
	        // Get the cart item key of the cheapest product
	        $cheapest_key = array_keys($eligible_get_items, min($eligible_get_items))[0];
	        $cart->cart_contents[$cheapest_key]['data']->set_price(0);
	    }
	}


	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Promotion_Tool_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Promotion_Tool_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/promotion-tool-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Promotion_Tool_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Promotion_Tool_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/promotion-tool-public.js', array( 'jquery' ), $this->version, false );
		wp_enqueue_script('pt-bogo-script', plugin_dir_url(__FILE__) . '/js/pt-bogo.js', ['jquery'], $this->version, false );
		wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], null, true);

		wp_localize_script('pt-bogo-script', 'pt_bogo_data', [
	        'ajax_url' => admin_url('admin-ajax.php'),
	        'nonce' => wp_create_nonce('pt_bogo_nonce')
	    ]);

	}

}
