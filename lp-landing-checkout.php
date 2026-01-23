<?php
/**
 * Plugin Name: WP WooCommerce Ajax Landing Page
 * Description: Auto-adds specific product to cart on page visit, handles variable attributes, and updates price via AJAX.
 * Version: 1.0
 * Author: Tarik Billa
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LP_Landing_Checkout {

    private $landing_page_id = 4942; 
    private $product_id      = 4928;

    public function __construct() {
        // 1. Initial Cart Load (Strict Reset)
        add_action( 'wp', array( $this, 'handle_initial_cart' ) );

        // 2. Render Form Shortcode
        add_shortcode( 'lp_checkout_form', array( $this, 'render_product_form' ) );

        // 3. Load JS and CSS
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_custom_scripts' ) );

        // 4. CUSTOM AJAX HANDLER: Bypass standard WC logic to force update
        add_action( 'wp_ajax_lp_update_cart_item', array( $this, 'ajax_update_cart_item' ) );
        add_action( 'wp_ajax_nopriv_lp_update_cart_item', array( $this, 'ajax_update_cart_item' ) );
    }

    /**
     * LOGIC: Force reset cart if it contains anything other than our target product
     */
    public function handle_initial_cart() {
        if ( ! is_page( $this->landing_page_id ) ) return;
        if ( ! class_exists( 'WooCommerce' ) ) return;

        $needs_reset = true;

        // Check if cart is NOT empty
        if ( ! WC()->cart->is_empty() ) {
            $cart_items = WC()->cart->get_cart();
            
            // If cart has exactly 1 item, check if it is our product
            if ( count( $cart_items ) === 1 ) {
                foreach ( $cart_items as $cart_item ) {
                    if ( $cart_item['product_id'] == $this->product_id ) {
                        $needs_reset = false; // It's already there, don't touch it
                    }
                }
            }
        }

        // If needs reset (Empty, Has other products, or has multiple products)
        if ( $needs_reset ) {
            WC()->cart->empty_cart();
            $this->add_first_variation( $this->product_id );
        }
    }

    private function add_first_variation( $product_id ) {
        $product = wc_get_product( $product_id );
        if ( ! $product ) return;

        if ( $product->is_type( 'variable' ) ) {
            $variations = $product->get_available_variations();
            if ( ! empty( $variations ) ) {
                // Add the first available variation (Default)
                $variation_id = $variations[0]['variation_id']; 
                WC()->cart->add_to_cart( $variation_id, 1 );
            }
        } else {
            WC()->cart->add_to_cart( $product_id, 1 );
        }
    }

    /**
     * CUSTOM AJAX: Force Update Cart
     */
    public function ajax_update_cart_item() {
        check_ajax_referer( 'update-order-review', 'security' );

        $variation_id = isset( $_POST['variation_id'] ) ? intval( $_POST['variation_id'] ) : 0;
        $quantity     = isset( $_POST['quantity'] ) ? intval( $_POST['quantity'] ) : 1;
        
        if ( $quantity < 1 ) $quantity = 1;

        // Validate Variation ID
        $variation = wc_get_product( $variation_id );
        
        if ( ! $variation ) {
            wp_send_json_error( array( 'message' => 'Invalid Variation ID' ) );
        }

        // BRUTE FORCE UPDATE
        // 1. Clear everything
        WC()->cart->empty_cart();
        
        // 2. Add the specific variation with specific quantity
        $added = WC()->cart->add_to_cart( $variation_id, $quantity );

        if ( $added ) {
            wp_send_json_success( array( 'variation_id' => $variation_id, 'qty' => $quantity ) );
        } else {
            wp_send_json_error( array( 'message' => 'Could not update cart' ) );
        }
    }

    public function render_product_form( $atts ) {
        $product = wc_get_product( $this->product_id );
        if ( ! $product ) return '<p>Product not found.</p>';

        ob_start();

        wp_enqueue_script( 'wc-add-to-cart-variation' );
        wp_enqueue_style( 'wc-add-to-cart-variation' );

        // CSS: Hide Button, Hide Top Quantity
        echo '<style>
            .lp-product-wrapper .single_add_to_cart_button { display: none !important; }
            .lp-product-wrapper .quantity { display: none !important; }
            .lp-updating { opacity: 0.5; pointer-events: none; }
        </style>';

        // Mock Query
        global $wp_query, $post;
        $temp_query = clone $wp_query;
        $post = get_post( $this->product_id );
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
        if ( is_page( $this->landing_page_id ) ) {
            wp_enqueue_script( 'lp-final-solution', plugin_dir_url( __FILE__ ) . 'lp-script.js', array('jquery'), '1.0', true );
            
            wp_localize_script( 'lp-final-solution', 'lp_data', array(
                'ajax_url'   => admin_url( 'admin-ajax.php' ),
                'nonce'      => wp_create_nonce( 'update-order-review' ),
            ));
        }
    }
}

new LP_Landing_Checkout();