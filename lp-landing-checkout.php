<?php
/**
 * Plugin Name: WP WooCommerce Ajax Landing Page
 * Description: Auto-adds specific product to cart on page visit, handles variable attributes, and updates price via AJAX.
 * Version: 3.0 (Smart Admin & Defaults)
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

        // 2. Frontend Logic
        add_action( 'wp', array( $this, 'handle_initial_cart' ) );
        
        // 3. Shortcode
        add_shortcode( 'lp_checkout_form', array( $this, 'render_product_form' ) );

        // 4. Assets
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_custom_scripts' ) );

        // 5. AJAX Handler
        add_action( 'wp_ajax_lp_update_cart_item', array( $this, 'ajax_update_cart_item' ) );
        add_action( 'wp_ajax_nopriv_lp_update_cart_item', array( $this, 'ajax_update_cart_item' ) );

        // 6. Admin AJAX for Product Search
        add_action( 'wp_ajax_lp_search_products', array( $this, 'ajax_search_products' ) );
        add_action( 'wp_ajax_nopriv_lp_search_products', array( $this, 'ajax_search_products' ) );
        add_action( 'wp_ajax_lp_get_variations', array( $this, 'ajax_get_variations' ) );

        // 7. Force Default Attributes on Frontend
        add_filter( 'woocommerce_product_get_default_attributes', array( $this, 'override_default_attributes' ), 10, 2 );
    }

    // --- ADMIN ---

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
                <?php settings_fields( 'lp_settings_group' ); ?>
                
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
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Search Product</th>
                        <td>
                            <input type="text" id="lp_product_search" class="regular-text" placeholder="Type product name..." style="width: 400px;">
                            <input type="hidden" name="lp_product_id" id="lp_product_id" value="<?php echo esc_attr( get_option('lp_product_id') ); ?>">
                            <div id="lp_selected_product_name" style="margin-top:5px; color: green;"></div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Default Variation</th>
                        <td>
                            <select name="lp_default_variation_id" id="lp_default_variation_id" class="regular-text" disabled>
                                <option value="">Select a Product First</option>
                            </select>
                            <p class="description">Select the specific variation (e.g., Color/Size) to load by default.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Shortcode</th>
                        <td>
                            <input type="text" readonly value="[lp_checkout_form]" style="width: 200px;">
                            <button type="button" id="copy_shortcode" class="button">Copy Shortcode</button>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <script>
                jQuery(document).ready(function($) {
                    // 1. Product Search (Select2 logic simulation)
                    $('#lp_product_search').on('keyup change', function() {
                        var search = $(this).val();
                        if(search.length < 2) return;

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: { action: 'lp_search_products', term: search },
                            success: function(res) {
                                // Simple select replacement logic could go here, 
                                // for now, user selects from a simulated dropdown or just types.
                                // Since Select2 is complex to inline, we will use a simple select if we had one,
                                // but for this text input, let's assume we have a select below it or just rely on ID.
                            }
                        });
                    });

                    // Actually, let's use a standard <select> populated by JS for better UX without Select2 dependency conflicts.
                    // We will dynamically swap the input with a select below.
                    
                    // --- REAL IMPLEMENTATION ---
                    // Fetch all products initially? No, that's heavy.
                    // Let's use a simple AJAX to populate variations.
                    
                    // Load variations if Product ID exists
                    var currentPid = '<?php echo get_option('lp_product_id'); ?>';
                    if(currentPid) {
                        loadVariations(currentPid);
                        // Set search input value to product name (mockup)
                        $('#lp_product_search').val('ID: ' + currentPid + ' (Loaded)');
                    }

                    function loadVariations(pid) {
                        if(!pid) {
                            $('#lp_default_variation_id').empty().append('<option value="">Select a Product First</option>').prop('disabled', true);
                            return;
                        }

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: { action: 'lp_get_variations', product_id: pid },
                            success: function(res) {
                                $('#lp_default_variation_id').empty().prop('disabled', false);
                                var currentVarId = '<?php echo get_option('lp_default_variation_id'); ?>';
                                
                                if(res.length > 0) {
                                    $('#lp_default_variation_id').append('<option value="">-- Select Default --</option>');
                                    $.each(res, function(index, item) {
                                        var selected = (item.id == currentVarId) ? 'selected' : '';
                                        $('#lp_default_variation_id').append('<option value="'+item.id+'" '+selected+'>'+item.name+'</option>');
                                    });
                                } else {
                                    $('#lp_default_variation_id').append('<option value="">Product has no variations</option>');
                                }
                            }
                        });
                    }

                    // Listen for Product ID Change (Manual ID entry)
                    $('#lp_product_id').on('change', function() {
                        loadVariations($(this).val());
                    });

                    // Copy Shortcode
                    $('#copy_shortcode').on('click', function() {
                        var $temp = $("<input>");
                        $("body").append($temp);
                        $temp.val("[lp_checkout_form]").select();
                        document.execCommand("copy");
                        $temp.remove();
                        $(this).text('Copied!').css('color', 'green');
                        setTimeout(function(){ $('#copy_shortcode').text('Copy Shortcode').css('color', ''); }, 2000);
                    });
                });
            </script>
        </div>
        <?php
    }

    // AJAX: Search Products
    public function ajax_search_products() {
        $term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';
        $args = array(
            'post_type' => 'product',
            's' => $term,
            'posts_per_page' => 10
        );
        $query = new WP_Query($args);
        $results = array();
        if($query->have_posts()) {
            while($query->have_posts()) {
                $query->the_post();
                $results[] = array(
                    'id' => get_the_ID(),
                    'text' => get_the_title()
                );
            }
        }
        wp_send_json($results);
    }

    // AJAX: Get Variations
    public function ajax_get_variations() {
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $product = wc_get_product($product_id);
        $variations = array();

        if ( $product && $product->is_type( 'variable' ) ) {
            foreach ( $product->get_available_variations() as $variation ) {
                $var_obj = wc_get_product( $variation['variation_id'] );
                $attributes = wc_get_formatted_variation( $variation_obj, true, false, false ); // Variation data
                
                // Construct a readable name
                $var_name = "Variation #" . $variation['variation_id'];
                if ( ! empty( $variation['attributes'] ) ) {
                    foreach ( $variation['attributes'] as $key => $value ) {
                        $term_name = get_term_by( 'slug', $value, str_replace( 'attribute_', '', $key ) );
                        $var_name .= ($term_name ? $term_name->name : $value) . " / ";
                    }
                    $var_name = rtrim( $var_name, " / " );
                }

                $variations[] = array(
                    'id' => $variation['variation_id'],
                    'name' => $var_name
                );
            }
        }
        wp_send_json($variations);
    }

    // --- FRONTEND LOGIC ---

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
            $this->add_default_product();
        }
    }

    // OVERRIDE DEFAULT ATTRIBUTES FOR FORM
    public function override_default_attributes( $defaults, $product ) {
        // Check if this is our Landing Page
        $page_id = get_option('lp_landing_page_id');
        if ( ! $page_id || ! is_page( $page_id ) ) return $defaults;

        $target_pid = get_option('lp_product_id');
        $default_vid = get_option('lp_default_variation_id');

        // Only apply if we are on the right product
        if ( $product->get_id() != $target_pid ) return $defaults;

        // If a specific variation is set in dashboard
        if ( $default_vid ) {
            $variation = wc_get_product($default_vid);
            if ( $variation ) {
                // Get attributes from the variation
                $new_defaults = array();
                foreach ( $variation->get_variation_attributes() as $key => $value ) {
                    $new_defaults[$key] = $value;
                }
                return $new_defaults;
            }
        }

        return $defaults;
    }

    private function add_default_product() {
        $default_variation_id = get_option('lp_default_variation_id');
        
        // 1. If user set a specific default variation in dashboard
        if ( $default_variation_id ) {
            WC()->cart->add_to_cart( $default_variation_id, 1 );
            return;
        }

        // 2. Fallback: Add first available variation
        $product_id = get_option('lp_product_id');
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

    public function ajax_update_cart_item() {
        check_ajax_referer( 'update-order-review', 'security' );
        $variation_id = isset( $_POST['variation_id'] ) ? intval( $_POST['variation_id'] ) : 0;
        $quantity     = isset( $_POST['quantity'] ) ? intval( $_POST['quantity'] ) : 1;
        
        if ( $quantity < 1 ) $quantity = 1;

        $variation = wc_get_product( $variation_id );
        if ( ! $variation ) wp_send_json_error( array( 'message' => 'Invalid Variation ID' ) );

        WC()->cart->empty_cart();
        $added = WC()->cart->add_to_cart( $variation_id, $quantity );

        if ( $added ) {
            wp_send_json_success( array( 'variation_id' => $variation_id, 'qty' => $quantity ) );
        } else {
            wp_send_json_error( array( 'message' => 'Could not update cart' ) );
        }
    }

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

        wp_enqueue_script( 'lp-final-solution', plugin_dir_url( __FILE__ ) . 'lp-script.js', array('jquery'), '3.0', true );
        
        wp_localize_script( 'lp-final-solution', 'lp_data', array(
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'update-order-review' ),
        ));
    }
}

new LP_Landing_Checkout();