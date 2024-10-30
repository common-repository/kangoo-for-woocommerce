/**
 * Just enable google places auto-complete in an input bases on its ID
 * Create hidden inputs to set the latitude and longitude of the address
 *
 * @since 1.0.0
 *
 * @param {array[String]} optionIDs
 *                        The input ID to set the auto-completion
 *
 * @param {string} afterThisElementID
 *                 Put the hidden fields after the element with this ID
 *
 * @param {array}  latAndLngIDs
 *                 IDs to generate the input hidden fields to set the lat and lng of the store
 *                 Ex:
 *                 {
 *                    lat: "The ID of the field to set the latitude"
 *                    lng: "The ID of the field to set the longitude"
 *                 }
 *
 * @param {string} errorMessageInvalidAddress
 *                 Error message to show when the address is NOT set correctly using Google Places
 *
 */

/**
 * This variable indicates if the address has been set correctly using Google Places auto-completion
 * and has a function to show an error message if the address isn't set correctly
 * @type {Object}
 *      Ex. {
 *          isValid: false,                                 // Represent if the target input address is valid or not
 *          targetInput: 'targetInputAddressID'             // Could be several input to put the destination address on but only one should be taken,
 *                                                          // This var keeps the ID of that input. This specially happen on checkout because you
 *                                                          // can ship your package to another destination
 *          showErrorMessage: function(){                   // A function to notify if the target address is not valid
 *              alert('Sorry, your address is invalid');
 *          }
 *      }
 */
var address = {
    isValid: false,
    targetInput: optionIDs[0] // By default is the 1st input ID (billing address on checkout)
    //showErrorMessage function will be set later to use jQuery and Notify.js library
};

jQuery(document).ready(function($) {
    // Set function that display error message when the address has not been selected using Google Places
    // Doing this far because jquery, to be able to use jquery
    address.showErrorMessage = showErrorMessageOnInvalidAddress;

    // generate the hidden inputs
    generateLatAndLngInputs();

    /**
     * Set the autocompletion on all given inputs on the variable optionIDs
     * If the user select an address set the hidden values, listeing only to the input especified 
     * on targetInput Address
     * 
     * If the user change the address (erase some letter or add some text) without using Google Places
     * Auto-completion in the targetInput address, the address is considered invalid
     *
     * Apply this condition on every desired address input
     */
    optionIDs.map( (optionID) => {

        // Get the element to set the autocomplete with google places
        var input = document.getElementById(optionID);
        var options = {
            types: ['geocode'],
        };

        // Set google places autocompletion on an input
        var autocomplete = new google.maps.places.Autocomplete(input, options);

        // When the user select an address from the google places list
        google.maps.event.addListener(autocomplete, 'place_changed', function(e) {

            var place = autocomplete.getPlace();

            if (!place.geometry)
                return;

            // If this option is the current targetInput address set the lat and lng invalid 
            // listeting only if the current input is the targetInput address
            if( optionID === address.targetInput ) {
                var lat = place.geometry.location.lat();
                var lng = place.geometry.location.lng();

                // When the user select an address, set the values
                setLatAndLng(lat, lng);

                address.isValid = true;

                // update the woocommerce checkout, this solve some bugs
                $( 'body' ).trigger( 'update_checkout' );
            }
        });

        /**
         * Set the address as invalid if the user modified with the keyboard, he must use the
         * google places dropdown
         */
        $('#'+optionID).keydown(function () {
            console.log('keydown');

            // If this option is the current targetInput address set the lat and lng invalid
            if( optionID === address.targetInput ) {
                console.log(address.targetInput);
                address.isValid = false;
                setLatAndLng('', '');
            }
        });
    });
    
    /**
     * Generate the input fields to set the lat and lng coordinates after the element
     * with the ID in afterThisElement variable
     * 
     * @since  1.0.0
     */
    function generateLatAndLngInputs(){
        // Get the element to append the hidden fields
        var afterThisElement = $('#'+afterThisElementID);

        var storeLocationLat = '<input id="'+latAndLngIDs.lat+'" name="'+latAndLngIDs.lat+'" type="hidden" />';
        var storeLocationLng = '<input id="'+latAndLngIDs.lng+'" name="'+latAndLngIDs.lng+'" type="hidden" />';
        afterThisElement.after( storeLocationLat );
        afterThisElement.after( storeLocationLng );

        // In Kangoo Settings page the latitute and longitude may not be undefined
        if( window.hasOwnProperty( "latAndLngValues" ) ) {
            // Set latitude and longitude values passed via parameters in the correspondent input hidden fields
            setLatAndLng(latAndLngValues.lat, latAndLngValues.lng);
    
            // If the coordinates have valid values, set address as valid
            if ((latAndLngValues.lat !== '') && (latAndLngValues.lng !== ''))
                address.isValid = true;
        }
        else
            // Set latitude and longitude values as empty
            setLatAndLng('', '');
    }

    /**
     * Set the latitude and longitude in the hidden inputs
     *
     * @since  1.0.0
     * 
     * @param {number} lat
     *                 latitude
     *
     * @param {number} lng
     *                 longitude
     */
    function setLatAndLng(lat, lng) {
        $('#'+latAndLngIDs.lat).val(lat);
        $('#'+latAndLngIDs.lng).val(lng);
    }

    /**
     * Show an error message when the address has not been set using Google Places Auto-completion
     * and scroll page to show the correspondent address input element
     *
     * The element pointed will be the targetInput address
     * 
     * @since 1.0.0
     */
    function showErrorMessageOnInvalidAddress(){

        // Scroll page to show the input address with the message
        $('html, body').animate({
            scrollTop: $('#'+address.targetInput).offset().top - 100
        }, 250);

        // Add the error message to the input address
        $('#'+address.targetInput).notify( errorMessageInvalidAddress,
                                { position:"top", className: "warn"}
                            );
    }
});
