<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://with-influence.ae
 * @since      1.0.0
 *
 * @package    Promotion_Tool
 * @subpackage Promotion_Tool/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Promotion_Tool
 * @subpackage Promotion_Tool/includes
 * @author     Moonshot <info@moonshot.digital>
 */
class Promotion_Tool {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Promotion_Tool_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'PROMOTION_TOOL_VERSION' ) ) {
			$this->version = PROMOTION_TOOL_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'promotion-tool';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

		add_action('init', [$this,'pt_register_bogo_post_type']);

		add_action('wp_ajax_pt_check_bogo_offer', [$this,'pt_ajax_check_bogo_offer']);
		add_action('wp_ajax_nopriv_pt_check_bogo_offer', [$this,'pt_ajax_check_bogo_offer']);

		add_action('wp_ajax_pt_add_bogo_product', [$this,'pt_ajax_add_bogo_product']);
		add_action('wp_ajax_nopriv_pt_add_bogo_product', [$this,'pt_ajax_add_bogo_product']);

	}

	public function pt_ajax_add_bogo_product() {
	    check_ajax_referer('pt_bogo_nonce', 'nonce');

	    $product_id = intval($_POST['product_id']);
	    $rule_id = intval($_POST['rule_id']);

	    WC()->cart->add_to_cart($product_id, 1, 0, [], [
		    'pt_bogo_applied' => true,
		    'pt_bogo_rule_id' => $rule_id,
		]);

	    // WC()->cart->add_to_cart($product_id, 1, 0, [], ['pt_bogo_rule_id' => $rule_id]);
	    wp_die();
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

	public function pt_ajax_check_bogo_offer() {
	    check_ajax_referer('pt_bogo_nonce', 'nonce');

	    $product_id = intval($_POST['product_id']);

	    // Get all active BOGO rules, ordered by priority ASC (lowest number = highest priority)
	    $rules = $this->pt_get_active_bogo_rules();

	    usort($rules, function($a, $b) {
	        return intval($a['priority'] ?? 9999) - intval($b['priority'] ?? 9999);
	    });

	    // Count total quantity of Buy product in cart
	    // $product_qty = 0;
	    // foreach (WC()->cart->get_cart() as $cart_item) {
	    //     if ((int)$cart_item['product_id'] === $product_id) {
	    //         $product_qty += $cart_item['quantity'];
	    //     }
	    // }

	    // wp_send_json_success([
	    //         'all_rules'       => $rules
	    //     ]);

	    $alreadyDone = false;

	    foreach ($rules as $rule) {
	        if (!in_array($product_id, (array) $rule['buy_products'])) {
	            continue;
	        }

	        foreach (WC()->cart->get_cart() as $cart_item) {
	        	if (!empty($cart_item['pt_bogo_applied']) || $cart_item['pt_bogo_applied'] ) {
	        		$alreadyDone = true;
	        	}
	        }

	        if($alreadyDone){
	        	continue;
	        }

	        $required_qty     = intval($rule['qty']);
	        $get_product_ids  = (array) $rule['get_products'];
	        $buy_product_ids  = (array) $rule['buy_products'];
	        $bogo_type        = $rule['bogo_type'];
	        $rule_title       = $rule['title'] ?? 'BOGO Offer';

	        $already_in_cart = false;
	        $manually_added  = false;

	        $product_qty = 0;

		    foreach (WC()->cart->get_cart() as $cart_item) {
		        if (in_array($cart_item['product_id'], $buy_product_ids)) {
		        	$product_qty += $cart_item['quantity'];
		        }
		    }

	        foreach (WC()->cart->get_cart() as $cart_item) {
	            if (in_array($cart_item['product_id'], $get_product_ids)) {
	            	if(!in_array($cart_item['product_id'], $buy_product_ids)) {
		                $already_in_cart = true;

		                if (empty($cart_item['pt_bogo_applied']) || $cart_item['pt_bogo_applied'] !== true) {
		                    $manually_added = true;
		                }
	            	}
	            }
	        }

	        // ✅ Rule condition met
	        if ($product_qty >= $required_qty) {
	            if ($manually_added) {
	                wp_send_json_success([
	                    'rule_id' => $rule['id'],
	                    'status'  => 'manual_block',
	                    'message' => 'You added the reward manually. Please use the provided button to claim the offer.',
	                ]);
	            }

	            if ($already_in_cart) {
	                wp_send_json_success([
	                    'rule_id' => $rule['id'],
	                    'status'  => 'already_added',
	                    'message' => 'Reward product already added via the offer.',
	                ]);
	            }

	            $products = [];
	            foreach ($get_product_ids as $pid) {
	                $products[] = [
	                    'id'    => $pid,
	                    'title' => get_the_title($pid),
	                    'image' => get_the_post_thumbnail_url($pid, 'thumbnail'),
	                    'label' => $bogo_type === 'bogo_free'
	                        ? 'Get Free'
	                        : 'Get ' . intval($rule['discount']) . '% Off',
	                ];
	            }

	            wp_send_json_success([
	                'rule_id'   => $rule['id'],
	                'status'    => 'qualified',
	                'title'     => $rule_title,
	                'type' => $rule['bogo_type'],
	                'products'  => $products
	            ]);
	        }

	        // ❌ Not yet satisfied
	        $remaining    = $required_qty - $product_qty;
	        $removed_any  = false;

	        // foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
	        //     if (in_array($cart_item['product_id'], $get_product_ids)) {
	        //         WC()->cart->remove_cart_item($cart_item_key);
	        //         $removed_any = true;
	        //     }
	        // }

	        wp_send_json_success([
	            'rule_id'       => $rule['id'],
	            'status'        => 'not_yet',
	            'title'         => $rule_title,
	            'required_qty'  => $required_qty,
	            'current_qty'   => $product_qty,
	            'remaining_qty' => $remaining,
	            'now_removed'   => $removed_any,
	            'type' => $rule['bogo_type'],
	            'message'       => sprintf(
	                'Add %d more to unlock: %s',
	                $remaining,
	                $bogo_type === 'bogo_free'
	                    ? 'Get 1 free!'
	                    : 'Get ' . intval($rule['discount']) . '% off!'
	            ),
	        ]);

	        return; // Only apply the first matched rule based on priority
	    }

	    // 🚫 No matching rule
	    wp_send_json_success(['status' => 'no_rule']);
	}

	public function pt_register_bogo_post_type() {
	    register_post_type('pt_bogo_rule', [
	        'labels' => [
	            'name' => 'BOGO Rules',
	            'singular_name' => 'BOGO Rule',
	            'add_new' => 'Add New Rule',
	            'add_new_item' => 'Add New BOGO Rule',
	            'edit_item' => 'Edit BOGO Rule',
	            'new_item' => 'New BOGO Rule',
	            'view_item' => 'View BOGO Rule',
	        ],
	        'public' => false,
	        'show_ui' => true,
	        'menu_icon' => 'dashicons-randomize',
	        'supports' => ['title'],
	        'menu_position' => 90,
	    ]);
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Promotion_Tool_Loader. Orchestrates the hooks of the plugin.
	 * - Promotion_Tool_i18n. Defines internationalization functionality.
	 * - Promotion_Tool_Admin. Defines all hooks for the admin area.
	 * - Promotion_Tool_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-promotion-tool-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-promotion-tool-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-promotion-tool-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-promotion-tool-public.php';

		$this->loader = new Promotion_Tool_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Promotion_Tool_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Promotion_Tool_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Promotion_Tool_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Promotion_Tool_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Promotion_Tool_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
