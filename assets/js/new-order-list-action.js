/**
 * script to enqueue the custom status on the order list
 * @since  1.0.0
 *
 * @param {string} KANGOO_ICON_SRC
 *        	       The URL of the image to represent the custom status order.
 */
jQuery(function($)
{
  var button = $('.button.tips.kangoo_ready_to_pick_up');
  var img = '<img id="kangoo_icon" src="'+KANGOO_ICON_SRC+'"/>';

  // Add Kangoo icon to the Kangoo notification button in orders list action column
  button.append(img);
});