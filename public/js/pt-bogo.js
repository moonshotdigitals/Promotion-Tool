jQuery(document).ready(function($) {

    const shownBogoForProduct = new Set();

    /**
     * Render and show the BOGO Reward Modal
     */
    function showBogoRewardModal(data) {

        let productList = '<div class="poup-with-affiliate-unlocked-reward"><ul class="reward-product-list">';

        data.products.forEach(prod => {
          productList += `
            <li class="reward-product-item">
              <img src="${prod.image}" alt="${prod.title}" class="product-img" />
              <div class="product-content">
                <div class="product-title">${prod.title}</div>
                <button class="get-free-button pt-add-bogo-item" 
                        data-id="${prod.id}" 
                        data-rule="${data.rule_id}">
                  ${prod.label}
                </button>
              </div>
            </li>`;
        });

        productList += '</ul></div>';

        if(data.type=="bogo_percent"){
            $('.poup-with-affiliate-unlocked-reward.popup2 .popup-subtext').html("Get a Discounted product!")
        }else{
            $('.poup-with-affiliate-unlocked-reward.popup2 .popup-subtext').html("Get Another Product Free!")
        }
        $('.poup-with-affiliate-unlocked-reward.popup2').fadeIn()
        $('.poup-with-affiliate-unlocked-reward .productlists').html(productList)

        // Swal.fire({
        //     title: '🎁 You’ve unlocked a reward!',
        //     html: productList,
        //     showConfirmButton: false,
        //     customClass: {
        //         popup: 'bogo-reward-modal'
        //     },
        //     didRender: () => {
        //         $('.pt-add-bogo-item').on('click', function(e) {
        //             e.preventDefault();
        //             const pid = $(this).data('id');

        //             $.post(wc_add_to_cart_params.ajax_url, {
        //                 action: 'woocommerce_ajax_add_to_cart',
        //                 product_id: pid,
        //                 quantity: 1
        //             }, function(response) {
        //                 Swal.fire('✅ Gift Added!', '', 'success');
        //                 $(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash, $(this)]);
        //             });
        //         });
        //     }
        // });

    }

    /**
     * Show "Not Qualified Yet" Modal
     */
    function showBogoHintModal(data) {
        if(data.type=="bogo_free"){
            $('.with-affiliate-custom-popup.popup1 img').attr('src', $('#buy_get_free').val())
            $('.with-affiliate-custom-popup.popup1 h2.popup-title').html("Want A Free Product<br>As A Reward?")
        }else{
            $('.with-affiliate-custom-popup.popup1 img').attr('src', $('#buy_get_discounted').val())
            $('.with-affiliate-custom-popup.popup1 h2.popup-title').html("Want A Discounted Product<br>As A Reward?")
        }
        $('.with-affiliate-custom-popup.popup1').css('display', 'block')
        $('.with-affiliate-custom-popup.popup1 .popup-subtitle').html(data.message)
        $('.with-affiliate-custom-popup.popup1 .popup-subtitle-top').html(data.title)
    }

    /**
     * AJAX check BOGO status by product ID
     */
    function checkBogoOffer(productId) {
        if (shownBogoForProduct.has(productId)) return;

        $.post(pt_bogo_data.ajax_url, {
            action: 'pt_check_bogo_offer',
            product_id: productId,
            nonce: pt_bogo_data.nonce
        }, function(response) {
            if (!response.success) return;

            const data = response.data;
            if (data.status === 'qualified') {
                showBogoRewardModal(data);
                shownBogoForProduct.add(productId);
            } else if (data.status === 'not_yet') {
                showBogoHintModal(data);
            }
        });
    }

    /**
     * Extract product ID from cart item
     */
    function extractProductId($cartItem) {
        let productId = $cartItem.find('input[name^="cart"]').data('product_id');
        if (!productId) {
            productId = $cartItem.find('input.qty').attr('data-product_id');
        }
        return productId;
    }

    /**
     * Trigger BOGO check on single product or archive (add to cart)
     */
    $('body').on('added_to_cart', function(event, fragments, cart_hash, $button) {
        const productId = $button.data('product_id');
        if (productId) checkBogoOffer(productId);
    });

    // Listen for cart or checkout update events
    $(document.body).on('updated_cart_totals updated_checkout', function() {
        // setTimeout(function() {
             $('.cart_item').each(function() {
                let productId = $(this).find('[data-product_id]').data('product_id');

                // Fallback: try from input if not found in button
                if (!productId) {
                    const inputName = $(this).find('input.qty').attr('name'); // e.g. cart[abc123][qty]
                    const matches = inputName.match(/\[([^\]]+)]/); // get cart key

                    if (matches && matches[1]) {
                        const cartKey = matches[1];
                        const itemData = wc_cart_params?.cart || {};
                        productId = itemData[cartKey]?.product_id;
                    }
                }

                if (productId) {
                    checkBogoOffer(productId);
                }
            });
         // }, 1000);
    });

    /**
     * Trigger BOGO check on cart or checkout page updates
     */
    // $(document.body).on('updated_cart_totals updated_checkout', function() {
    //     $('.cart_item').each(function() {
    //         const productId = extractProductId($(this));
    //         if (productId) checkBogoOffer(productId);
    //     });
    // });
    $(document).on('click','.single_add_to_cart_button',function(){
        productId = $(this).val();
        setTimeout(function() {
            if (productId) checkBogoOffer(productId);
        }, 2000);
    })

});

jQuery(document).ready(function($) {
    $('body').on('click', '.pt-add-bogo-item', function() {
        let productId = $(this).data('id');
        let ruleId = $(this).data('rule');
        $.post(pt_bogo_data.ajax_url, {
            action: 'pt_add_bogo_product',
            product_id: productId,
            rule_id: ruleId,
            nonce: pt_bogo_data.nonce
        }, function() {
            location.reload();
        });
    });

    // Close popup
    $('body').on('click', '.pt-popup-close', function() {
        $('.pt-popup').remove();
    });
});

jQuery(document).ready(function($) {
    // Listen for WooCommerce cart update completion
    $(document.body).on('updated_cart_totals', function() {
        const productsChecked = new Set();

        // Loop through all cart items
        $('.cart_item').each(function() {
            const $row = $(this);
            const $qtyInput = $row.find('input.qty');
            const quantity = parseInt($qtyInput.val(), 10) || 0;
            const productId = parseInt($qtyInput.data('product_id'), 10); // Custom attr you may need to add in PHP

            if (!productId || quantity <= 0 || productsChecked.has(productId)) return;

            productsChecked.add(productId);

            $.post(pt_bogo_data.ajax_url, {
                action: 'pt_check_bogo_offer',
                product_id: productId,
                nonce: pt_bogo_data.nonce
            }, function(response) {
                if (!response.success || !response.data) return;

                const data = response.data;

                if (data.status === 'qualified') {

                    let productList = '<div class="poup-with-affiliate-unlocked-reward"><ul class="reward-product-list">';

                    data.products.forEach(prod => {
                      productList += `
                        <li class="reward-product-item">
                          <img src="${prod.image}" alt="${prod.title}" class="product-img" />
                          <div class="product-content">
                            <div class="product-title">${prod.title}</div>
                            <button class="get-free-button pt-add-bogo-item" 
                                    data-id="${prod.id}" 
                                    data-rule="${data.rule_id}">
                              ${prod.label}
                            </button>
                          </div>
                        </li>`;
                    });

                    productList += '</ul></div>';

                    $('.poup-with-affiliate-unlocked-reward.popup2').fadeIn()
                    $('.poup-with-affiliate-unlocked-reward .productlists').html(productList)

                    // Swal.fire({
                    //     title: '🎁 Reward Unlocked!',
                    //     html: productList,
                    //     showConfirmButton: false,
                    //     customClass: {
                    //         popup: 'bogo-reward-modal'
                    //     },
                    //     didRender: () => {
                    //         $('.pt-add-bogo-item').on('click', function(e) {
                    //             e.preventDefault();
                    //             const pid = $(this).data('product_id');

                    //             $.post(wc_add_to_cart_params.ajax_url, {
                    //                 action: 'woocommerce_ajax_add_to_cart',
                    //                 product_id: pid,
                    //                 quantity: 1
                    //             }, function(response) {
                    //                 Swal.fire('✅ Gift Added!', '', 'success');
                    //                 $(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash, $(this)]);
                    //             });
                    //         });
                    //     }
                    // });
                } else if (data.status === 'not_yet') {
                    // if(data.now_removed){
                    //     location.reload()
                    // }
                    // Swal.fire({
                    //     title: 'Want a reward?',
                    //     text: data.message,
                    //     icon: 'info',
                    //     confirmButtonText: 'OK'
                    // });
                }
            });
        });
    });
});


// jQuery(document).ready(function($) {
//     setTimeout(function() {
//         if ($('body').hasClass('woocommerce-checkout')) {
//             $('span.pt-bogo-rule-id').each(function() {
//                 console.log($(this).html())  
//                 if($(this).html()!=""){ 
//                     $(this).parent().parent().parent().find('.quantity').css('display','none')
//                 }
//             })
//         }
//     }, 5000);
// });

jQuery(document).ready(function($) {
    $('.with-affiliate-custom-popup .popup-button').on('click', function () {
      $('.with-affiliate-custom-popup').fadeOut();
    });

    $('.poup-with-affiliate-unlocked-reward.popup2 .popup-close span').on('click', function () {
      $('.poup-with-affiliate-unlocked-reward.popup2').fadeOut();
    });
})