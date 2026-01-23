<?php
/**
 * Plugin Name: WP WooCommerce Ajax Landing Page
 * Description: Auto-adds specific product to cart on page visit, handles variable attributes, and updates price via AJAX.
 * Version: 2.0 (With Admin Dashboard)
 * Author: Tarik Billa
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LP_Landing_Checkout {

    public function __construct() {
        // 1. Admin Settings
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // 2. Cart Logic
        add_action( 'wp', array( $this, 'handle_initial_cart' ) );

        // 3. Render Form Shortcode
        add_shortcode( 'lp_checkout_form', array( $this, 'render_product_form' ) );

        // 4. Load JS and CSS
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_custom_scripts' ) );

        // 5. CUSTOM AJAX HANDLER
        add_action( 'wp_ajax_lp_update_cart_item', array( $this, 'ajax_update_cart_item' ) );
        add_action( 'wp_ajax_nopriv_lp_update_cart_item', array( $this, 'ajax_update_cart_item' ) );
    }

    // --- ADMIN DASHBOARD ---

    public function add_admin_menu() {
        add_menu_page(
            'LP Checkout Settings',
            'LP Checkout',
            'manage_options',
            'lp_checkout_settings',
            array( $this, 'render_settings_page' ),
            'dashicons-cart',
            90
        );
    }

    public function register_settings() {
        register_setting( 'lp_settings_group', 'lp_landing_page_id' );
        register_setting( 'lp_settings_group', 'lp_product_id' );
        register_setting( 'lp_settings_group', 'lp_default_variation_id' );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Landing Page Checkout Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'lp_settings_group' );
                do_settings_sections( 'lp_settings_group' );
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Select Landing Page</th>
                        <td>
                            <?php 
                            $args = array(
                                'name' => 'lp_landing_page_id',
                                'selected' => get_option('lp_landing_page_id'),
                                'show_option_none' => 'Select Page...'
                            );
                            wp_dropdown_pages($args);
                            ?>
                            <p class="description">Select the page where the checkout form appears.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Product ID</th>
                        <td>
                            <input type="number" name="lp_product_id" value="<?php echo esc_attr( get_option('lp_product_id') ); ?>" class="regular-text">
                            <p class="description">Enter the ID of the main Variable Product.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Default Variation ID</th>
                        <td>
                            <input type="number" name="lp_default_variation_id" value="<?php echo esc_attr( get_option('lp_default_variation_id') ); ?>" class="regular-text">
                            <p class="description">Enter the Variation ID of the default combination (e.g., Size: Small, Color: Black). <br><strong>Tip:</strong> Go to Products > Variations, hover over the row, and check the link ID.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }


    // --- CART LOGIC ---

    public function handle_initial_cart() {
        $page_id = get_option('lp_landing_page_id');
        if ( ! $page_id || ! is_page( $page_id ) ) return;
        if ( ! class_exists( 'WooCommerce' ) ) return;

        $needs_reset = true;

        if ( ! WC()->cart->is_empty() ) {
            $cart_items = WC()->cart->get_cart();
            if ( count( $cart_items ) === 1 ) {
                foreach ( $cart_items as $cart_item ) {
                    $product_id = get_option('lp_product_id');
                    if ( $cart_item['product_id'] == $product_id ) {
                        $needs_reset = false; 
                    }
                }
            }
        }

        if ( $needs_reset ) {
            WC()->cart->empty_cart();
            $this->add_first_variation();
        }
    }

    private function add_first_variation() {
        $default_variation_id = get_option('lp_default_variation_id');
        $product_id = get_option('lp_product_id');
        
        // If user set a default variation ID, add that
        if ( $default_variation_id ) {
            WC()->cart->add_to_cart( $default_variation_id, 1 );
        } else {
            // Fallback: Add first available variation
            $product = wc_get_product( $product_id );
            if ( $product && $product->is_type( 'variable' ) ) {
                $variations = $product->get_available_variations();
                if ( ! empty( $variations ) ) {
                    $variation_id = $variations[0]['variation_id']; 
                    WC()->cart->add_to_cart( $variation_id, 1 );
                }
            } else {
                WC()->cart->add_to_cart( $product_id, 1 );
            }
        }
    }

    // --- AJAX HANDLER ---

    public function ajax_update_cart_item() {
        check_ajax_referer( 'update-order-review', 'security' );

        $variation_id = isset( $_POST['variation_id'] ) ? intval( $_POST['variation_id'] ) : 0;
        $quantity     = isset( $_POST['quantity'] ) ? intval( $_POST['quantity'] ) : 1;
        
        if ( $quantity < 1 ) $quantity = 1;

        $variation = wc_get_product( $variation_id );
        
        if ( ! $variation ) {
            wp_send_json_error( array( 'message' => 'Invalid Variation ID' ) );
        }

        WC()->cart->empty_cart();
        $added = WC()->cart->add_to_cart( $variation_id, $quantity );

        if ( $added ) {
            wp_send_json_success( array( 'variation_id' => $variation_id, 'qty' => $quantity ) );
        } else {
            wp_send_json_error( array( 'message' => 'Could not update cart' ) );
        }
    }

    // --- FRONTEND RENDER ---

    public function render_product_form( $atts ) {
        $product_id = get_option('lp_product_id');
        $product = wc_get_product( $product_id );
        if ( ! $product ) return '<p>Product not found. Please check plugin settings.</p>';

        ob_start();

        wp_enqueue_script( 'wc-add-to-cart-variation' );
        wp_enqueue_style( 'wc-add-to-cart-variation' );

        echo '<style>
            .lp-product-wrapper .single_add_to_cart_button { display: none !important; }
            .lp-product-wrapper .quantity { display: none !important; }
            .lp-updating { opacity: 0.5; pointer-events: none; }
        </style>';

        // Mock Query
        global $wp_query, $post;
        $temp_query = clone $wp_query;
        $post = get_post( $product_id );
        setup_postdata( $post );
        $wp_query->is_singular = true;
        $wp_query->is_single   = true;
        $wp_query->post        = $post;
        $wp_query->posts       = array( $post );
        $wp_query->post_count  = 1;
        $wp_query->found_posts = 1;
        $wp_query->current_post = 0;
        $wp_query->is_product = true; 

        ?>
        <div class="lp-product-wrapper">
            <div class="product lp-single-product-context">
                <?php woocommerce_template_single_add_to_cart(); ?>
            </div>
        </div>
        <?php

        $wp_query = $temp_query;
        wp_reset_postdata();

        return ob_get_clean();
    }

    public function enqueue_custom_scripts() {
        $page_id = get_option('lp_landing_page_id');
        if ( ! $page_id || ! is_page( $page_id ) ) return;

        wp_enqueue_script( 'lp-final-solution', plugin_dir_url( __FILE__ ) . 'lp-script.js', array('jquery'), '2.0', true );
        
        wp_localize_script( 'lp-final-solution', 'lp_data', array(
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'update-order-review' ),
        ));
    }
}

new LP_Landing_Checkout();