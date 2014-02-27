/**
 * This ajax handles feedback submission.
 * It serializes the feedback form and adds action. It relies on 'orbisius_simple_feedback_config' json config
 * which should have been injected by the WP plugin.
 * The code below gives feedback if the submission was successful.
 */

window.orbisius_simple_feedback_setup_js_init = 0;

// sometimes JS errors stop the jq .ready method so we'll try
// to init my functions again.
setTimeout(function() { orbisius_simple_feedback_setup_js(); }, 700); // ::stmp

jQuery(document).ready(function($) {
    orbisius_simple_feedback_setup_js();
});

/**
 * Setups the hooks, clicks of buttons etc.
 * I am using a function to call it because sometimes JS gives error.
 * I have to be able to do the hooks no matter what.
 * The div also has a nice mouseover event which is a backup.
 * I am using the flag below so we don't have to setup the callbacks
 * multiple times.
 * @author Svetoslav Marinov (SLAVI)
 * @see http://orbisius.com
 *
 * @returns void
 */
function orbisius_simple_feedback_setup_js() {
    // Did we do the setup already?
    if (window.orbisius_simple_feedback_setup_js_init > 0) {
        return;
    }

    var $ = jQuery;
    
    // let's show the form when the mouse is over the title
    // let's not handle mouseleave because we don't want the form to disappear.
    $('.orbisius_simple_feedback_container .feedback_wrapper').on("mouseenter", function (e) {
        $('.orbisius_simple_feedback_container .feedback').show();
        $('.feedback_wrapper').removeClass('feedback_wrapper_short'); // expand the feedback container
        $('#feedback_text').focus();
        $('.close_button_link').show();
    });

    // this
    $('.orbisius_simple_feedback_container .close_button_link').on("click", function (e) {
        $('.orbisius_simple_feedback_container .feedback').hide();
        $('.feedback_wrapper').addClass('feedback_wrapper_short'); // shrink the feedback container
        $('.close_button_link').hide();
    });

    $('#orbisius_beta_feedback_form').submit(function (e) {
        var $ = jQuery; // Just in case;
        var feedback_text = $('#feedback_text').val();
        feedback_text = $.trim(feedback_text);

        if (feedback_text == '') {
            alert('Enter something.');
            $('#feedback_text').focus().addClass('need_input');

            return false;
        }

        var feedback_email = $('#feedback_email').val();
        feedback_email = $.trim(feedback_email);

        // if the user has entered their email we'll do a basic check
        // e.g. if there's an @ sign or dots
        if (feedback_email != ''
                && (feedback_email.indexOf('@') == -1 || feedback_email.indexOf('.') == -1)) {
            alert('Enter a valid email.');
            $('#feedback_email').focus().addClass('need_input');

            return false;
        }

        $('.need_input').removeClass('need_input');
        $('#orbisius_beta_feedback_form_submit').hide();
        $('#orbisius_beta_feedback_form_submit').after('<span class="loading">Please wait...</span>');

        $('.orbisius_simple_feedback_container .result').text('').removeClass('error success').hide();

        $.ajax({
            type : "post",
            dataType : "json",
            url : orbisius_simple_feedback_config.plugin_ajax_url, // contains all the necessary params
            data : $(this).serialize() + '&action=orbisius_simple_feedback_ajax&page_id=' + orbisius_simple_feedback_config.page_id,
            success: function(json) {
               if (json.status) {
                  $('.orbisius_simple_feedback_container .result').text('Sent.').addClass('success').show();
               } else {
                  //alert("There was an error.");
                  $('.orbisius_simple_feedback_container .result').text('There was an error.').addClass('error').show();
               }

               $('.orbisius_simple_feedback_container .loading').hide();
               $('#orbisius_beta_feedback_form_submit').show();
            }
        });

        return false;
    });
    
    window.orbisius_simple_feedback_setup_js_init = 1;
}
