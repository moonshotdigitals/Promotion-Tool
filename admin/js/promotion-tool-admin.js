(function( $ ) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */

})( jQuery );

jQuery(document).ready(function($) {
    $('.custom-select2').select2();
});

jQuery(document).ready(function($) {
    $(document).on('change','#pt_bogo_type',function(){
    	var bogo_type = $(this).val()
    	console.log(bogo_type)
    	if(bogo_type=="bogo_free" || bogo_type=="bogo_percent"){
    		$('#pt_buy_products').css('display', 'block')
    		$('#pt_get_products').css('display', 'block')
    		$('#pt_buy_category').css('display', 'none')
    		$('#pt_get_category').css('display', 'none')

    		$('#pt_buy_products select').attr('required','true')
    		$('#pt_get_products select').attr('required','true')
    		$('#pt_discount_amount').attr('required','true')

    		$('#pt_buy_category select').removeAttr('required')
    		$('#pt_get_category select').removeAttr('required')

    		if(bogo_type=="bogo_percent"){
    			$('#bogo_percent').css('display','block')
    		}else{
    			$('#pt_discount_amount').removeAttr('required')
    			$('#bogo_percent').css('display','none')
    		}
    	}
    	else if(bogo_type=="category_bogo"){
    		$('#pt_buy_category').css('display', 'block')
    		$('#pt_get_category').css('display', 'block')
    		$('#pt_buy_products').css('display', 'none')
    		$('#pt_get_products').css('display', 'none')
    		$('#bogo_percent').css('display','none')

    		$('#pt_buy_products select').removeAttr('required')
    		$('#pt_get_products select').removeAttr('required')
    		$('#pt_discount_amount').removeAttr('required')

    		$('#pt_buy_category select').attr('required','true')
    		$('#pt_get_category select').attr('required','true')

    	}else{

    	}
    })
});