<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://with-influence.ae
 * @since      1.0.0
 *
 * @package    Promotion_Tool
 * @subpackage Promotion_Tool/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Promotion_Tool
 * @subpackage Promotion_Tool/admin
 * @author     Moonshot <info@moonshot.digital>
 */
class Promotion_Tool_Admin {

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
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		
		add_action('add_meta_boxes', [$this,'pt_add_bogo_meta_boxes']);
		add_action('save_post', [$this,'pt_save_bogo_rule']);

	}

	public function pt_add_bogo_meta_boxes() {
	    add_meta_box('pt_bogo_rule_settings', 'BOGO Rule Settings', [$this,'pt_render_bogo_rule_meta_box'], 'pt_bogo_rule', 'normal', 'default');
	}

	public function pt_render_bogo_rule_meta_box($post) {
	    $bogo_type       = get_post_meta($post->ID, '_pt_bogo_type', true);
	    $buy_products    = get_post_meta($post->ID, '_pt_buy_products', true) ?: [];
	    $buy_category_product    = get_post_meta($post->ID, '_pt_buy_category', true) ?: [];
	    $get_products    = get_post_meta($post->ID, '_pt_get_products', true) ?: [];
	    $get_category_product    = get_post_meta($post->ID, '_pt_get_category', true) ?: [];
	    $discount_amount = get_post_meta($post->ID, '_pt_discount_amount', true);
	    $start_date      = get_post_meta($post->ID, '_pt_start_date', true);
	    $end_date        = get_post_meta($post->ID, '_pt_end_date', true);
	    $priority        = get_post_meta($post->ID, '_pt_priority', true);
	    $x_qty        = get_post_meta($post->ID, '_pt_x_qty', true);

	    // echo "<pre>";
		//     print_r(get_post_meta($post->ID));
	    // echo "</pre>";

	    $buy_products_display = $bogo_type=="category_bogo"?"none":"block";
	    $get_products_display = $bogo_type=="category_bogo"?"none":"block";

	    $buy_category_product_display = ($bogo_type == "bogo_free" || $bogo_type == "bogo_percent") ? "none" : "block";
		$get_category_product_display = ($bogo_type == "bogo_free" || $bogo_type == "bogo_percent") ? "none" : "block";


	    $discount_amount_display = $bogo_type=="bogo_percent"?"block":"none";

	    // exit();
	    ?>

	    <style>
	        .pt-field-group label { display: block; margin-top: 12px; font-weight: 600; }
	    </style>

	    <?php wp_nonce_field('save_pt_bogo_rule', 'pt_bogo_nonce'); ?>

	    <div class="pt-field-group">
	        <label for="pt_bogo_type">BOGO Type</label>
	        <select required name="pt_bogo_type" id="pt_bogo_type" class="widefat mt-top">
	        	<option value="">Select Rule Type</option>
	            <option value="bogo_free" <?php selected($bogo_type, 'bogo_free'); ?>>Buy X Get Y Free</option>
	            <option value="bogo_percent" <?php selected($bogo_type, 'bogo_percent'); ?>>Buy X Get Y% Off</option>
	            <!-- <option value="tiered_bogo" <?php selected($bogo_type, 'tiered_bogo'); ?>>Tiered BOGO</option> -->
	            <!-- <option value="category_bogo" <?php selected($bogo_type, 'category_bogo'); ?>>Category BOGO</option> -->
	        </select>

	        <?php $this->pt_render_product_selector('pt_buy_products[]', $buy_products,'', 'pt_buy_products','Buy Products(x)',$buy_products_display); ?>

	        <?php $this->pt_render_category_selector('pt_buy_category[]', $buy_category_product,'', 'pt_buy_category','Buy Category Product',$buy_category_product_display); ?>

	        <label for="pt_end_date">Qty(x)</label>
	        <input type="number" required name="pt_x_qty" id="pt_x_qty" class="mt-top" value="<?php echo esc_attr($x_qty); ?>">

	        <?php $this->pt_render_product_selector('pt_get_products[]', $get_products,'multiple', 'pt_get_products','Get Products(y)',$get_products_display); ?>

	        <?php $this->pt_render_category_selector('pt_get_category[]', $get_category_product,'', 'pt_get_category','Get Category Product',$get_category_product_display); ?>

	        <div id="bogo_percent" style="display: <?php echo $discount_amount_display; ?>;">
		        <label for="pt_discount_amount">Discount(y) (%)</label>
		        <input type="number" name="pt_discount_amount" class="mt-top" id="pt_discount_amount" value="<?php echo esc_attr($discount_amount); ?>" class="small-text" step="0.01">
	    	</div>

	        <label for="pt_start_date">Start Date</label>
	        <input  type="date" name="pt_start_date" class="mt-top" value="<?php echo esc_attr($start_date); ?>">

	        <label for="pt_end_date">End Date</label>
	        <input  type="date" name="pt_end_date" class="mt-top" value="<?php echo esc_attr($end_date); ?>">

	        <label for="pt_priority">Rule Priority (Lower = Higher Priority)</label>
	        <input  type="number" name="pt_priority" class="mt-top" value="<?php echo esc_attr($priority ?: 10); ?>" class="small-text">
	    </div>

	    <?php
	}

	public function pt_render_product_selector($name, $selected_ids = [], $type="", $id="",$lbl,$display) {
	    $args = [
	        'post_type'      => 'product',
	        'post_status'    => 'publish',
	        'posts_per_page' => -1,
	    ];
	    $products = get_posts($args);

	    echo '<div style="display: '.$display.'" id="' . esc_attr($id) . '"><label class="mt-bot" for="'.esc_attr($name).'">'.$lbl.'</label><select  class="custom-select2" name="' . esc_attr($name) . '" ' . esc_attr($type) . ' style="width:100%;height:auto;">';
	    echo "<option value=''>Select</option>";
	    foreach ($products as $product) {
	        $selected = in_array($product->ID, $selected_ids) ? 'selected' : '';
	        echo "<option value='{$product->ID}' {$selected}>{$product->post_title}</option>";
	    }
	    echo '</select></div>';
	}

	public function pt_render_category_selector($name, $selected_ids = [], $type = "", $id="",$lbl,$display) {
	    $args = [
	        'taxonomy'   => 'product_cat',
	        'hide_empty' => false,
	    ];

	    $categories = get_terms($args);

	    echo '<div style="display: '.$display.'" id="' . esc_attr($id) . '"><label class="mt-bot" for="'.$id.'">'.$lbl.'</label><select  class="custom-select2" name="' . esc_attr($name) . '" ' . esc_attr($type) . ' style="width:100%;height:auto;">';	
	    echo "<option value=''>Select Category</option>";

	    foreach ($categories as $category) {
	        $selected = in_array($category->term_id, $selected_ids) ? 'selected' : '';
	        echo "<option value='{$category->term_id}' {$selected}>{$category->name}</option>";
	    }

	    echo '</select></div>';
	}


	public function pt_save_bogo_rule($post_id) {

		// Autosave check
	    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

	    // Proper post type check (replace with your CPT)
	    if (get_post_type($post_id) !== 'pt_bogo_rule') return;

	    // Permissions check
	    if (!current_user_can('edit_post', $post_id)) return;

        if (!isset($_POST['pt_bogo_nonce']) || !wp_verify_nonce($_POST['pt_bogo_nonce'], 'save_pt_bogo_rule')) return;

	    if(is_admin()){

			    update_post_meta($post_id, '_pt_bogo_type', sanitize_text_field($_POST['pt_bogo_type'] ?? ''));
			    update_post_meta($post_id, '_pt_start_date', sanitize_text_field($_POST['pt_start_date'] ?? ''));
			    update_post_meta($post_id, '_pt_end_date', sanitize_text_field($_POST['pt_end_date'] ?? ''));
			    update_post_meta($post_id, '_pt_priority', intval($_POST['pt_priority'] ?? 10));
			    update_post_meta($post_id, '_pt_x_qty', intval($_POST['pt_x_qty'] ?? 1));

				delete_post_meta($post_id, '_pt_buy_products');
				delete_post_meta($post_id, '_pt_get_products');
				delete_post_meta($post_id, '_pt_discount_amount');
				delete_post_meta($post_id, '_pt_get_category');
				delete_post_meta($post_id, '_pt_buy_category');

	    	if($_POST['pt_bogo_type']=="bogo_free" || $_POST['pt_bogo_type']=="bogo_percent"){

			    update_post_meta($post_id, '_pt_buy_products', array_map('intval', $_POST['pt_buy_products'] ?? []));
			    update_post_meta($post_id, '_pt_get_products', array_map('intval', $_POST['pt_get_products'] ?? []));

			    if($_POST['pt_bogo_type']=="bogo_percent"){
			    	update_post_meta($post_id, '_pt_discount_amount', floatval($_POST['pt_discount_amount'] ?? 0));
			    }

			}
			elseif($_POST['pt_bogo_type']=="category_bogo"){

				update_post_meta($post_id, '_pt_get_category', array_map('intval', $_POST['pt_get_category'] ?? []));
			    update_post_meta($post_id, '_pt_buy_category', array_map('intval', $_POST['pt_buy_category'] ?? []));
			
			}

		}
	}

	/**
	 * Register the stylesheets for the admin area.
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

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/promotion-tool-admin.css', array(), $this->version, 'all' );
		wp_enqueue_style( 'select-2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
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

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/promotion-tool-admin.js', array( 'jquery' ), $this->version, false );
		wp_enqueue_script( 'select-2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array( 'jquery' ), $this->version, false );

	}

}
