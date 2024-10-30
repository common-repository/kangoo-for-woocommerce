/**
 * @since  1.0.0
 *
 * @param {string} security_nonce
 *        Code of the Nonce. Security Stuff
 *
 * @param {string} emailInputId
 *        The id of the input that represent the email on the Kangoo Custom shipping method settings page
 *
 * @param {string} passwordInputId
 *        The id of the input that represent the password on the Kangoo Custom shipping method settings page
 *
 * @param {string} enabledInputId
 *        The id of the input that represent if the kangoo shipping method is enabled on the Kangoo Custom shipping method settings page
 *
 * @param {string} addressInputId
 *        The id of the input that represent the address of the store on the Kangoo Custom shipping method settings page
 *
 * @param {string} spinnerUrl
 *        The url of the spinner.
 *        When the user clicks, we put a spinner to represent that the page is working.
 */
jQuery(function($)
{

    // The call trigered
    var saveButton = $('[name="save"]');

    insertSpinnerElement();

    /**
     * Typical event function on jquery to catch the click event on the save button
     * @since 1.0.0
     */
    saveButton.click(function(event)
    {
        event.preventDefault();

        /**
         * Check if the store manager selected the store's address correctly using Google Places Auto-completion
         *
         * When the store manager selects the location store using Google PlacesAuto-completion, the latitude and
         * longitude will be set in the correspondent input elements
         *
         * @param  {[boolean]} address
         *                     true - Store address selected using Google Places Auto-completion
         *                     false - Store address empty or filled manually (without using Google Places Auto-completion)
         */
        if (address.isValid === false) {
            address.showErrorMessage();
            return;
        }

        // Set email and password input fields as readonly
        setEmailPasswordReadOnly( true );

        hideSpinner( false );
        //---------------- ajax call ----------------
        $.ajax(
        {
            url: ajaxurl,
            type: 'POST',
            dataType: 'json',
            data:
            {
                //the WP actions that is going to receive this
                action: 'credentials_validation',
                //security stuff
                security_nonce: security_nonce,
                // Custom data
                email: $('#'+emailInputId).val(),
                password: $('#'+passwordInputId).val(),

            },
            // timeout: 30000,
            success: function( response )
            {
                if(response.success){
                    console.log( 'succ: ', response );

                    // Continue the 'Save changes' submit button
                    saveButton.unbind('click');
                    saveButton.trigger('click');
                }
                else{
                    alert( response.data );
                    // Set email and password fields' readonly property back to false
                    setEmailPasswordReadOnly( false );

                    hideSpinner( true );
                }
            },
            // Conection Error
            error: function( error )
            {
                // Set email and password fields' readonly property back to false
                setEmailPasswordReadOnly( false );
                hideSpinner( true );
                console.log( 'Conection error: ', error );
            }
        });
        //---------------- ajax call ----------------
    });

    /**
     * Set Email and Password ReadOnly property true or false
     * @since 1.0.0
     *
     * @param {Boolean} isReadOnly
     *                  true  - readonly on
     *                  false - readonly off
     */
    function setEmailPasswordReadOnly( isReadOnly ){
        $('#'+emailInputId).attr('readonly', isReadOnly);
        $('#'+passwordInputId).attr('readonly', isReadOnly);

        // Disabled the enabled button
        $('#'+enabledInputId).attr('readonly', isReadOnly);
        $('#'+enabledInputId).attr('onclick', 'return '+isReadOnly+';');

        // Disabled the enabled button
        $('#'+addressInputId).attr('readonly', isReadOnly);
    }

    /**
     * Insert image spinner element to show it during the validation process
     * @since 1.0.0
     */
    function insertSpinnerElement(){
        var img = '<img id="spinner" style="width: 20px; margin-left: 10px;" src="'+spinnerUrl+'"/>';
        saveButton.after(img);
        hideSpinner( true );
    }

    /**
     * Hide and show the Spinner element during the validation process
     * @since 1.0.0
     *
     * @param  {boolean} hide
     *                   true  - Hide Spinner
     *                   false - Show Spinner
     */
    function hideSpinner( hide ) {
        if (hide)
            $('#spinner').hide();
        else
            $('#spinner').show();
    }
});