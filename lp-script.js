jQuery(document).ready(function($) {
    
    var isUpdating = false;

    $('.lp-product-wrapper form').attr('action', ''); 
    $('.lp-product-wrapper form').on('submit', function(e){
        e.preventDefault();
    });

    function updateMasterCart(variationId, quantity) {
        if (isUpdating) return;
        isUpdating = true;

        console.log('Sending Update: VarID=' + variationId + ', Qty=' + quantity);

        $('.lp-product-wrapper').addClass('lp-updating');
        $('body').trigger('updated_checkout_divs');

        $.ajax({
            type: 'POST',
            url: lp_data.ajax_url,
            data: {
                action: 'lp_update_cart_item',
                security: lp_data.nonce,
                variation_id: variationId,
                quantity: quantity
            },
            success: function(response) {
                if (response.success) {
                    $(document.body).trigger('wc_fragment_refresh');
                    $('body').trigger('update_checkout');
                } else {
                    alert('Update Failed: ' + (response.data.message || 'Unknown Error'));
                }
            },
            complete: function() {
                setTimeout(function(){
                    $('.lp-product-wrapper').removeClass('lp-updating');
                    isUpdating = false;
                }, 500);
            }
        });
    }

    $(document).on('found_variation', '.lp-product-wrapper form.variations_form', function(event, variation) {
        var variationId = variation.variation_id;
        var checkoutQty = $('.woocommerce-checkout-review-order-table input.qty').val() || 1;
        updateMasterCart(variationId, checkoutQty);
    });

    $(document).on('change', '.woocommerce-checkout-review-order-table input.qty', function() {
        var newQty = $(this).val();
        var form = $('.lp-product-wrapper form.variations_form');
        var currentVariationId = 0;
        
        if (form.length > 0) {
            currentVariationId = $('input[name="variation_id"]', form).val();
        }
        
        if (currentVariationId && currentVariationId !== "0") {
            updateMasterCart(currentVariationId, newQty);
        } else {
            console.warn("Cannot update qty: No variation selected");
        }
    });

});