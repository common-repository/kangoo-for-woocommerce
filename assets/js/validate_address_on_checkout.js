/**
 * This script validates that the user selects the shippind address using Google Places Auto-completion
 * when the user click the 'Place Order' button
 * @since  1.0.0
 *
 * @param {string} kangooShippingCheckboxId
 *        The id of the checkbox that represents Kangoo Custom shipping method in the checkout page
 */
jQuery( function($) {
    var checkoutForm = $( 'form.checkout' );

    /**
     * Listen to the checkbox 'Ship to a different address?' and alternate the 
     * correspondent TagetInput address.
     */
    setTargetInputAddress();

    /**
     * Validate the address when the user is going to place an order
     *
     * @return {[boolean]}  Whether continue with the submission or not
     *                      true  Continue with submission
     *                      false Prevent the submission
     */
    checkoutForm.on( 'checkout_place_order', function() {

        /**
         * The <td> element with the shipping methods available 
         * @type {DOM element}
         */
        var tdOfShippingMethods = $( 'td[data-title="Shipping"]' );

        /**
         * How many radio buttons are in the section of the shipping methods.
         * @type {Number}
         */
        var howManyRadio = tdOfShippingMethods.find('input[type=radio]').length;

        // If there is more than one option to check check if kangoo is not selected to do nothing
        if(howManyRadio >= 2) {
            /**
             * If Kangoo method shipping is NOT the shipping method chosen continue with the order submission
             * If Kangoo method shipping IS the chosen method, validate the address is correctly set
             */
            if (!$('#'+kangooShippingCheckboxId).is(':checked'))
                return true;
        }

        /**
         * Check if the customer selected the address correctly using Google Places Auto-completion
         *
         * When the customer selects the location store using Google PlacesAuto-completion, the latitude and
         * longitude will be set in the correspondent input elements
         */
        if (address.isValid === false) {
            address.showErrorMessage();
            return false;
        }

        return true;
    });

    /**
     * Alternate the correspond targetInput address based on the checkbox 'Ship to a different address?'
     *
     * @since 1.0.0
     */
    function setTargetInputAddress() {

        $('#ship-to-different-address-checkbox').change(function() {

            /**
             * Every time the user check or uncheck 'Ship to a different address' must re enter
             * the address using google places list.
             * Why? Is needed because the user can set a valid address in the billing section and activate the different
             * shipping address, "Place an Order" and it will take the lat and lng from the billing address (WRONG coordinates)
             * 
             * Is annoying but necessary
             */ 
            address.isValid = false;

            // Set the target input address to the shipping address input.
            // It's located on the right side of the checkout page generally
            if (this.checked)
                address.targetInput = 'shipping_address_1';
            // Set the target input address to the billing address input.
            // Is the destination shipping address on the left side, the common and most frequently used
            else
                address.targetInput = 'billing_address_1';
            
            console.log(address.targetInput);
        });
    }
});