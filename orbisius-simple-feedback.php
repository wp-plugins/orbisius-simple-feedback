<?php
/*
Plugin Name: Orbisius Simple Feedback
Plugin URI: http://club.orbisius.com/products/wordpress-plugins/orbisius-simple-feedback/
Description: Generates a nice & simple Feedback form which is positioned at the bottom center of your visitor's browser window.
Version: 1.0.2
Author: Svetoslav Marinov (Slavi)
Author URI: http://orbisius.com
*/

/*  Copyright 2012 Svetoslav Marinov (Slavi) <slavi@orbisius.com>

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Set up plugin
add_action( 'init', 'orbisius_simple_feedback_init', 0 );

add_action( 'admin_menu', 'orbisius_simple_feedback_setup_admin' );
add_action( 'wp_head', 'orbisius_simple_feedback_config');
add_action( 'admin_head', 'orbisius_simple_feedback_config');
add_action( 'wp_footer', 'orbisius_simple_feedback_inject_feedback' ); // be the last in the footer

add_action( 'wp_ajax_orbisius_simple_feedback_ajax', 'orbisius_simple_feedback_handle_ajax');
add_action( 'wp_ajax_nopriv_orbisius_simple_feedback_ajax', 'orbisius_simple_feedback_handle_ajax');
//add_action( 'wp_ajax_nopriv_orbisius_simple_feedback_ajax', 'orbisius_simple_feedback_handle_ajax_not_auth');

/**
 * Outputs directly to the browser a json config file which is used by main.js
 * This contains the ajax endpoint & page id so we can include it in the feedback.
 */
function orbisius_simple_feedback_config() {
    $queried_object = get_queried_object();

    $plugin_ajax_url = admin_url('admin-ajax.php'); // not always defined on the public side.
    $id = empty($queried_object->ID) ? 0 : $queried_object->ID;

    echo "\n<script> var orbisius_simple_feedback_config = { plugin_ajax_url: '$plugin_ajax_url', page_id : $id };</script>\n";
}

/**
 * Adds the action link to settings. That's from Plugins. It is a nice thing.
 * @param type $links
 * @param type $file
 * @return type
 */
function orbisius_simple_feedback_add_quick_settings_link($links, $file) {
    if ($file == plugin_basename(__FILE__)) {
        $link = admin_url('options-general.php?page=' . plugin_basename(__FILE__));
        $dashboard_link = "<a href=\"{$link}\">Settings</a>";
        array_unshift($links, $dashboard_link);
    }

    return $links;
}

/**
 * Setups loading of assets (css, js).
 * for live servers we'll use the minified versions e.g. main.min.js otherwise .js or .css (dev)
 * @see http://jscompress.com/ - used for JS compression
 * @see http://refresh-sf.com/yui/ - used for CSS compression
 * @return type
 */
function orbisius_simple_feedback_init() {
    $dev = empty($_SERVER['DEV_ENV']) ? 0 : 1;
    $suffix = $dev ? '' : '.min';

    wp_enqueue_script('jquery');

    wp_register_style( 'simple_feedback', plugins_url("/assets/main{$suffix}.css", __FILE__) );
    wp_enqueue_style( 'simple_feedback' );

    wp_register_script( 'simple_feedback', plugins_url("/assets/main{$suffix}.js", __FILE__), array('jquery', ), '1.0', true );
    wp_enqueue_script( 'simple_feedback');

    $opts = orbisius_simple_feedback_get_options();

    if (!empty($opts['show_in_admin'])) {
        add_action( 'admin_enqueue_scripts', 'orbisius_simple_feedback_init' );
        add_action( 'admin_footer', 'orbisius_simple_feedback_inject_feedback' ); // be the last in the footer
    }

    //Access the global $wp_version variable to see which version of WordPress is installed.
    global $wp_version;

    $color_picker = version_compare($wp_version, '3.5') >= 0
            ? 'wp-color-picker' // new WP
            : 'farbtastic'; // old WP

    wp_enqueue_style($color_picker);
    wp_enqueue_script($color_picker);
}

/**
 * 
 */
function orbisius_simple_feedback_handle_ajax() {
   $status_rec = array(
       'status' => 0,
   );

   // do some checks before deleting this attachment.
   if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
           && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
           ) {
       $status_rec['status'] = orbisius_simple_feedback_send_feedback();
   }

   $result = json_encode($status_rec);
   echo $result;

   exit();
}

/**
 * Not used for now
 */
function orbisius_simple_feedback_handle_ajax_not_auth() {
   $status_rec = array(
       'status' => 0,
       'message' => "You must be logged in in order to perform this operation.",
   );

   $result = json_encode($status_rec);
   echo $result;

   exit();
}

/**
 * Outputs the feedback form + container. if the user is logged in we'll take their email
 * requires: wp_footer
 */
function orbisius_simple_feedback_inject_feedback() {
    $opts = orbisius_simple_feedback_get_options();
    $data = orbisius_simple_feedback_get_plugin_data();

    // The user doesn't want to show the form.
    if (0&&empty($opts['status'])) {
        echo "\n<!-- {$data['name']} | {$data['url']} : is disabled. Skipping rendering. -->\n";
        return ;
    }

    $url = preg_replace('#\?.*#si', '', $data['url']);

    $site = site_url();
    $site = urlencode($site);
    $url .= "?utm_source=$site&utm_medium=frontend&utm_campaign=product";
    
    $xyz = "<a href='$url' target='_blank' title='opens in a new tab/page.'>{$data['name']}</a>";

    $powered_by_line = "<div class='powered_by'>Powered by $xyz</div>";

    // in case if somebody wants to get rid if the feedback link
    $powered_by_line = apply_filters('orbisius_simple_feedback_filter_powered_by', $powered_by_line);

    $email = '';
    $current_user_obj = wp_get_current_user();

    // if the user is logged in we'll take their email
    if (!empty($current_user_obj->user_email)) {
       $email = $current_user_obj->user_email;
       $email = esc_attr($email);
    }

    $call_to_action = empty($opts['call_to_action']) ? 'Feedback' : $opts['call_to_action'];
    $call_to_action_alignment = empty($opts['call_to_action_alignment']) ? '' : $opts['call_to_action_alignment'];

    if ($opts['feedback_box'] == 'input_text') {
        $text_box = '<input type="text" id="feedback_text" name="feedback_text" value="" class="feedback_text" placeholder="Enter your feedback here..." autocomplete="off" />';
    } else {
        $text_box = '<textarea id="feedback_text" name="feedback_text" class="feedback_text" placeholder="Enter your feedback here..." rows="2" cols="10"></textarea>';
    }

	$form_buff = <<<FORM_EOF
<div class="orbisius_simple_feedback_container">
    <div class="feedback_wrapper feedback_wrapper_short feedback_wrapper_$call_to_action_alignment" onmouseover="try { orbisius_simple_feedback_setup_js(); } catch (e) {} ">
        <div class="feedback_title">
			<strong>$call_to_action</strong> <span class="result hide"></span>
            <a href="javascript:void(0);" class='close_button_link hide'><span __class='close_button'>X</span></a>
        </div>
		<div class="feedback hide">
			<form id="orbisius_beta_feedback_form" class="orbisius_beta_feedback_form">
				$text_box
				<input type="text" id="feedback_email" name="feedback_email" value="$email" class="feedback_email" placeholder="Your email" />
				<input type="submit" id="orbisius_beta_feedback_form_submit" name="send" value="send" />
			</form>
			$powered_by_line
		</div>
    </div>
</div> <!-- /orbisius_simple_feedback_container -->

FORM_EOF;

    echo $form_buff;
}

/**
 * Lookps through different $_SERVER fields to get all of the IPs.
 * @return string
 */
function orbisius_simple_feedback_get_ip_list() {
    $ips = array();
    
    $vars = array(
        'REMOTE_ADDR',
        'HTTP_X_FORWARDED',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'HTTP_FORWARDED_FOR',
    );

    foreach ($vars as $key) {
        if (!empty($_SERVER[$key])) {
            $ips[] = "$key: " . $_SERVER[$key];
        }
    }

    $ips = array_map('trim', $ips);
    $ips = array_map('strip_tags', $ips);
    $ips = array_map('htmlentities', $ips);

    return join(', ', $ips);
}

/**
 * Sends an email to the admin
 */
function orbisius_simple_feedback_send_feedback() {
    $headers = array();
    $params = $_REQUEST;
    $page_id = empty($params['page_id']) ? 0 : intval($params['page_id']);

    if ($page_id) {
        $page_link = get_permalink($page_id);
    } else {
        $page_link = get_site_url();
    }

    $to = get_option('admin_email');
    $from_name = 'Simple Feedback';
    $from_email = 'feedback@' . $_SERVER['HTTP_HOST'];
    $headers[] = "From: $from_name <$from_email>";
    $headers[] = "Reply-To: " . esc_attr($params['feedback_email']);
    $subject = "[{$_SERVER['HTTP_HOST']}] New Feedback";
    //$headers[] = 'Cc: John Q Codex <jqc@wordpress.org>';

    $message = "Somebody just left you some feedback\n";
    $message .= empty($params['feedback_email']) ? '' : "\nEmail: " . esc_attr($params['feedback_email']);
    $message .= "\nPage: " . esc_attr($page_link);
    $message .= empty($page_id) ? '' : "\nPage ID: " . esc_attr($page_id);
    $message .= "\nBrowser: " . (empty($_SERVER['HTTP_USER_AGENT']) ? 'n/a' : $_SERVER['HTTP_USER_AGENT']);
    $message .= "\n" . orbisius_simple_feedback_get_ip_list();
    $message .= "\nDate: " . date('r');

    $current_user_obj = wp_get_current_user();

    if (!empty($current_user_obj->ID)) {
       $message .= "\nUser ID: " . $current_user_obj->ID;
       $message .= "\nUsername: " . $current_user_obj->user_login;
       
       if ($params['feedback_email'] != $current_user_obj->user_email) {
          $message .= "\nEmail: " . $current_user_obj->user_email;
       }

       $message .= "\nName: " . $current_user_obj->display_name;
       $message .= "\nJoined on: " . $current_user_obj->user_registered;
    }

    $message .= "\nFeedback:\n" . esc_attr($params['feedback_text']) . "\n";

    // in case an extension wants to add more stuff
    $to = apply_filters('orbisius_simple_feedback_ext_filter_pre_mail_to', $to);
    $message = apply_filters('orbisius_simple_feedback_ext_filter_pre_mail_message', $message);
    $subject = apply_filters('orbisius_simple_feedback_ext_filter_pre_mail_subject', $subject);
    $headers = apply_filters('orbisius_simple_feedback_ext_filter_pre_mail_header', $headers);

    $status = wp_mail( $to, $subject, $message, $headers );

    // executed after the email
    do_action('orbisius_simple_feedback_ext_action_post_mail', $status, $to, $subject, $message, $headers);

    return $status;
}

/**
 * Set up administration
 *
 * @package Orbisius Simple Feedback
 * @since 0.1
 */
function orbisius_simple_feedback_setup_admin() {
	add_options_page( 'Orbisius Simple Feedback', 'Orbisius Simple Feedback', 'manage_options', __FILE__, 'orbisius_simple_feedback_options_page' );

    add_filter('plugin_action_links', 'orbisius_simple_feedback_add_quick_settings_link', 10, 2);
}

add_action('admin_init', 'orbisius_simple_feedback_register_settings');

/**
 * Sets the setting variables
 */
function orbisius_simple_feedback_register_settings() { // whitelist options
    register_setting('orbisius_simple_feedback_settings', 'orbisius_simple_feedback_options', 'orbisius_simple_feedback_validate_settings');
}

/**
 * This is called by WP after the user hits the submit button.
 * The variables are trimmed first and then passed to the who ever wantsto filter them.
 * @param array the entered data from the settings page.
 * @return array the modified input array
 */
function orbisius_simple_feedback_validate_settings($input) { // whitelist options
    $input = array_map('trim', $input);

    // let extensions do their thing
    $input_filtered = apply_filters('orbisius_simple_feedback_ext_filter_settings', $input);

    // did the extension break stuff?
    $input = is_array($input_filtered) ? $input_filtered : $input;

    return $input;
}

/**
 * Retrieves the plugin options. It inserts some defaults.
 * The saving is handled by the settings page. Basically, we submit to WP and it takes
 * care of the saving.
 * 
 * @return array
 */
function orbisius_simple_feedback_get_options() {
    $defaults = array(
        'status' => 1,
        'show_in_admin' => 0,
        'call_to_action' => 'Feedback',
        'feedback_box' => 'textarea',
        'call_to_action_alignment' => 'bottom_center',
    );
    
    $opts = get_option('orbisius_simple_feedback_options');
    
    $opts = (array) $opts;
    $opts = array_merge($defaults, $opts);

    return $opts;
}

/**
 * Options page
 *
 * @package Orbisius Simple Feedback
 * @since 1.0
 */
function orbisius_simple_feedback_options_page() {
    $opts = orbisius_simple_feedback_get_options();
	?>

    <div class="wrap orbisius_simple_feedback_admin_wrapper">
        <h2>Orbisius Simple Feedback</h2>

        <div id="poststuff">
            <div id="post-body" class="metabox-holder columns-2">
                <!-- main content -->
                <div id="post-body-content">
                    <div class="meta-box-sortables ui-sortable">
                        <div class="postbox">
                            <div class="inside">
                                <form method="post" action="options.php">
                                    <?php settings_fields('orbisius_simple_feedback_settings'); ?>
                                    <table class="form-table">
                                        <?php if (0) : // field not needed at the moment ?>
                                        <tr valign="top">
                                            <th scope="row">Status</th>
                                            <td>
                                                <label for="radio1">
                                                    <input type="radio" id="radio1" name="orbisius_simple_feedback_options[status]"
                                                        value="1" <?php echo empty($opts['status']) ? '' : 'checked="checked"'; ?> /> Show Feedback box
                                                </label>
                                                <br/>
                                                <label for="radio2">
                                                    <input type="radio" id="radio2" name="orbisius_simple_feedback_options[status]"
                                                        value="0" <?php echo!empty($opts['status']) ? '' : 'checked="checked"'; ?> /> Hide Feedback box
                                                </label>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        <tr>
                                            <th scope="row">Call to Action</th>
                                            <td>
                                                <label for="orbisius_simple_feedback_options_call_to_action">
                                                    <input type="text" id="orbisius_simple_feedback_options_call_to_action" class="widefat"
                                                           name="orbisius_simple_feedback_options[call_to_action]"
                                                        value="<?php echo esc_attr($opts['call_to_action']); ?>" />
                                                </label>
                                                <p>Example: Feedback or Share your feedback.</p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Call to Action Alignment</th>
                                            <td>
                                                <label for="orbisius_simple_feedback_options_call_to_action_alignment">
                                                    <?php

                                                    $call_to_action_alignment_opts = array(
                                                        'bottom_left' => 'Bottom Left',
                                                        'bottom_center' => 'Bottom Center',
                                                        'bottom_right' => 'Bottom Right',
                                                    );

                                                    echo orbisius_simple_feedback_util::html_boxes('orbisius_simple_feedback_options[call_to_action_alignment]',
                                                            empty($opts['call_to_action_alignment']) ? '' : $opts['call_to_action_alignment'], $call_to_action_alignment_opts); ?>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row">Feedback Text Box Type</th>
                                            <td>
                                                <label for="orbisius_simple_feedback_options_feedback_box">
                                                    <?php

                                                    $feedback_box_opts = array(
                                                        'textarea' => 'Textarea',
                                                        'input_text' => 'Input Text Box',
                                                    );

                                                    echo orbisius_simple_feedback_util::html_boxes('orbisius_simple_feedback_options[feedback_box]',
                                                            empty($opts['feedback_box']) ? '' : $opts['feedback_box'], $feedback_box_opts); ?>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr valign="top">
                                            <th scope="row">Show in Admin Area</th>
                                            <td>
                                                <label for="radio_show_in_admin_enabled">
                                                    <input type="radio" id="radio_show_in_admin_enabled" name="orbisius_simple_feedback_options[show_in_admin]"
                                                        value="1" <?php echo empty($opts['show_in_admin']) ? '' : 'checked="checked"'; ?> /> Enabled
                                                </label>
                                                <br/>
                                                <label for="radio_show_in_admin_disabled">
                                                    <input type="radio" id="radio_show_in_admin_disabled" name="orbisius_simple_feedback_options[show_in_admin]"
                                                        value="0" <?php echo!empty($opts['show_in_admin']) ? '' : 'checked="checked"'; ?> /> Disabled
                                                </label>
                                            </td>
                                        </tr>

                                        <?php if (has_action('orbisius_simple_feedback_ext_action_render_settings')) : ?>
                                            <tr valign="top">
                                                <th scope="row"><strong>Extensions (see list)</strong></th>
                                                <td colspan="1">
                                                </td>
                                            </tr>
                                            <?php do_action('orbisius_simple_feedback_ext_action_render_settings', $opts, $settings_key); ?>
                                        <?php else : ?>
                                            <tr valign="top">
                                                <!--<th scope="row">Extension Name</th>-->
                                                <td colspan="2">
                                                    No extensions found.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </table>

                                    <p class="submit">
                                        <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
                                    </p>
                                </form>
                            </div> <!-- .inside -->

                        </div> <!-- .postbox -->

                        <!-- Extensions -->
                        <div class="postbox">
                            <h3><span>Extensions</span> 
                                | <a href='http://club.orbisius.com/products/wordpress-plugins/orbisius-simple-feedback/extensions/?utm_source=<?php
                                    echo str_replace('.php', '', basename(__FILE__));?>&utm_medium=plugin-settings-extensions&utm_campaign=product'
                                     target="_blank">Get Extensions</a></h3>
                            <div class="inside">
                                <p>Extensions allow you to add an extra functionality to this plugin.</p>
                                <div>
                                    <?php
                                       if (!has_action('orbisius_simple_feedback_ext_action_extension_list')) {
                                           echo "No extensions have been installed.";
                                       } else {
                                           echo "The following extensions have been found.<br/><ul>";
                                           do_action('orbisius_simple_feedback_ext_action_extension_list');
                                           echo "</ul>";
                                       }
                                       ?>
                                </div>
                                <br/>
                                <div>
    <?php
    $plugin_slug = basename(__FILE__);
    $plugin_slug = str_replace('.php', '', $plugin_slug);
    ?>
    <iframe style="width:100%;min-height:300px;height: auto;" width="640" height="480"
            src="http://club.orbisius.com/wpu/content/wp/<?php echo $plugin_slug;?>/" frameborder="0" allowfullscreen></iframe>

                                </div>

                            </div> <!-- .inside -->
                        </div> <!-- .postbox -->
                        <!-- /Extensions -->
                    </div> <!-- .meta-box-sortables .ui-sortable -->

                </div> <!-- post-body-content -->

                <!-- sidebar -->
                <div id="postbox-container-1" class="postbox-container">
                    <div class="meta-box-sortables">
                        <!-- Hire Us -->
                        <div class="postbox">
                            <h3><span>Hire Us</span></h3>
                            <div class="inside">
                                Hire us to create a plugin/web/mobile app
                                <br/><a href="http://orbisius.com/page/free-quote/?utm_source=<?php echo str_replace('.php', '', basename(__FILE__));?>&utm_medium=plugin-settings&utm_campaign=product"
                                   title="If you want a custom web/mobile app/plugin developed contact us. This opens in a new window/tab"
                                    class="button-primary" target="_blank">Get a Free Quote</a>
                            </div> <!-- .inside -->
                        </div> <!-- .postbox -->
                        <!-- /Hire Us -->

                        <!-- Newsletter-->
                        <div class="postbox">
                            <h3><span>Newsletter</span></h3>
                            <div class="inside">
                                <!-- Begin MailChimp Signup Form -->
                                <div id="mc_embed_signup">
                                    <?php
                                        $current_user = wp_get_current_user();
                                        $email = empty($current_user->user_email) ? '' : $current_user->user_email;
                                    ?>

                                    <form action="http://WebWeb.us2.list-manage.com/subscribe/post?u=005070a78d0e52a7b567e96df&amp;id=1b83cd2093" method="post"
                                          id="mc-embedded-subscribe-form" name="mc-embedded-subscribe-form" class="validate" target="_blank">
                                        <input type="hidden" value="settings" name="SRC2" />
                                        <input type="hidden" value="<?php echo str_replace('.php', '', basename(__FILE__));?>" name="SRC" />

                                        <span>Get notified about cool plugins we release</span>
                                        <!--<div class="indicates-required"><span class="app_asterisk">*</span> indicates required
                                        </div>-->
                                        <div class="mc-field-group">
                                            <label for="mce-EMAIL">Email</label>
                                            <input type="email" value="<?php echo esc_attr($email); ?>" name="EMAIL" class="required email" id="mce-EMAIL">
                                        </div>
                                        <div id="mce-responses" class="clear">
                                            <div class="response" id="mce-error-response" style="display:none"></div>
                                            <div class="response" id="mce-success-response" style="display:none"></div>
                                        </div>	<div class="clear"><input type="submit" value="Subscribe" name="subscribe" id="mc-embedded-subscribe" class="button-primary"></div>
                                    </form>
                                </div>
                                <!--End mc_embed_signup-->
                            </div> <!-- .inside -->
                        </div> <!-- .postbox -->
                        <!-- /Newsletter-->

                        <!-- Support options -->
                        <div class="postbox">
                            <h3>
                                <?php
                                        $plugin_data = get_plugin_data(__FILE__);
                                        $product_name = trim($plugin_data['PluginName']);
                                        $product_page = trim($plugin_data['PluginURI']);
                                        $product_descr = trim($plugin_data['Description']);
                                        $product_descr_short = substr($product_descr, 0, 50) . '...';

                                        $base_name_slug = basename(__FILE__);
                                        $base_name_slug = str_replace('.php', '', $base_name_slug);
                                        $product_page .= (strpos($product_page, '?') === false) ? '?' : '&';
                                        $product_page .= "utm_source=$base_name_slug&utm_medium=plugin-settings&utm_campaign=product";

                                        $product_page_tweet_link = $product_page;
                                        $product_page_tweet_link = str_replace('plugin-settings', 'tweet', $product_page_tweet_link);
                                    ?>
                                <!-- Twitter: code -->
                                <script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="http://platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>
                                <!-- /Twitter: code -->

                                <!-- Twitter: Orbisius_Follow:js -->
                                    <a href="https://twitter.com/orbisius" class="twitter-follow-button"
                                       data-align="right" data-show-count="false">Follow @orbisius</a>
                                <!-- /Twitter: Orbisius_Follow:js -->

                                &nbsp;

                                <!-- Twitter: Tweet:js -->
                                <a href="https://twitter.com/share" class="twitter-share-button"
                                   data-lang="en" data-text="Checkout <?php echo $product_name;?> #WordPress #plugin.<?php echo $product_descr_short; ?>"
                                   data-count="none" data-via="orbisius" data-related="orbisius"
                                   data-url="<?php echo $product_page_tweet_link;?>">Tweet</a>
                                <!-- /Twitter: Tweet:js -->

                                <br/>
                                <span>
                                    <a href="<?php echo $product_page; ?>" target="_blank" title="[new window]">Product Page</a>
                                    |
                                    <a href="http://club.orbisius.com/forums/forum/community-support-forum/wordpress-plugins/<?php echo $base_name_slug;?>/?utm_source=<?php echo $base_name_slug;?>&utm_medium=plugin-settings&utm_campaign=product"
                                    target="_blank" title="[new window]">Support Forums</a>

                                     <!-- |
                                     <a href="http://docs.google.com/viewer?url=https%3A%2F%2Fdl.dropboxusercontent.com%2Fs%2Fwz83vm9841lz3o9%2FOrbisius_LikeGate_Documentation.pdf" target="_blank">Documentation</a>-->
                                </span>
                            </h3>
                        </div> <!-- .postbox -->
                        <!-- /Support options -->

                        <div class="postbox">
                            <h3><span>Tell Your Friends</span></h3>
                            <div class="inside">
                                <?php
                                    $plugin_data = get_plugin_data(__FILE__);

                                    $app_link = urlencode($plugin_data['PluginURI']);
                                    $app_title = urlencode($plugin_data['Name']);
                                    $app_descr = urlencode($plugin_data['Description']);
                                ?>
                                <p>
                                    <!-- AddThis Button BEGIN -->
                                    <div class="addthis_toolbox addthis_default_style addthis_32x32_style">
                                        <a class="addthis_button_facebook" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                        <a class="addthis_button_twitter" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                        <a class="addthis_button_google_plusone" g:plusone:count="false" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                        <a class="addthis_button_linkedin" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                        <a class="addthis_button_email" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                        <!--<a class="addthis_button_myspace" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                        <a class="addthis_button_google" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                        <a class="addthis_button_digg" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                        <a class="addthis_button_delicious" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                        <a class="addthis_button_stumbleupon" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                        <a class="addthis_button_tumblr" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>
                                        <a class="addthis_button_favorites" addthis:url="<?php echo $app_link?>" addthis:title="<?php echo $app_title?>" addthis:description="<?php echo $app_descr?>"></a>-->
                                        <a class="addthis_button_compact"></a>
                                    </div>
                                    <!-- The JS code is in the footer -->

                                    <script type="text/javascript">
                                    var addthis_config = {"data_track_clickback":true};
                                    var addthis_share = {
                                      templates: { twitter: 'Check out {{title}} at {{lurl}} (from @orbisius)' }
                                    }
                                    </script>
                                    <!-- AddThis Button START part2 -->
                                    <script type="text/javascript" src="http://s7.addthis.com/js/250/addthis_widget.js#pubid=lordspace"></script>
                                    <!-- AddThis Button END part2 -->
                                </p>
                            </div> <!-- .inside -->
                        </div> <!-- .postbox -->

                        <div class="postbox"> <!-- quick-contact -->
                            <?php
                            $current_user = wp_get_current_user();
                            $email = empty($current_user->user_email) ? '' : $current_user->user_email;
                            $quick_form_action = is_ssl()
                                    ? 'https://ssl.orbisius.com/apps/quick-contact/'
                                    : 'http://apps.orbisius.com/quick-contact/';

                            if (!empty($_SERVER['DEV_ENV'])) {
                                $quick_form_action = 'http://localhost/projects/quick-contact/';
                            }
                            ?>
                            <h3><span>Quick Question or Suggestion</span></h3>
                            <div class="inside">
                                <div>
                                    <form method="post" action="<?php echo $quick_form_action; ?>" target="_blank">
                                        <?php
                                            global $wp_version;
                                            $plugin_data = get_plugin_data(__FILE__);

                                            $hidden_data = array(
                                                'site_url' => site_url(),
                                                'wp_ver' => $wp_version,
                                                'first_name' => $current_user->first_name,
                                                'last_name' => $current_user->last_name,
                                                'product_name' => $plugin_data['Name'],
                                                'product_ver' => $plugin_data['Version'],
                                                'woocommerce_ver' => defined('WOOCOMMERCE_VERSION') ? WOOCOMMERCE_VERSION : 'n/a',
                                            );
                                            $hid_data = http_build_query($hidden_data);
                                            echo "<input type='hidden' name='data[sys_info]' value='$hid_data' />\n";
                                        ?>
                                        <textarea class="widefat" id='orbisius_simple_feedback_msg' name='data[msg]' required="required"></textarea>
                                        <br/>Your Email: <input type="text" class=""
                                               name='data[sender_email]' placeholder="Email" required="required"
                                               value="<?php echo esc_attr($email); ?>"
                                               />
                                        <br/><input type="submit" class="button-primary" value="<?php _e('Send Feedback') ?>"
                                                    onclick="try { if (jQuery('#orbisius_simple_feedback_msg').val().trim() == '') { alert('Enter your message.'); jQuery('#orbisius_simple_feedback_msg').focus(); return false; } } catch(e) {};" />
                                        <br/>
                                        What data will be sent
                                        <a href='javascript:void(0);'
                                            onclick='jQuery(".orbisius_simple_feedback_data_to_be_sent").toggle();'>(show/hide)</a>
                                        <div class="hide-if-js hide orbisius_simple_feedback_data_to_be_sent">
                                            <textarea class="widefat" rows="4" readonly="readonly" disabled="disabled"><?php
                                            foreach ($hidden_data as $key => $val) {
                                                if (is_array($val)) {
                                                    $val = var_export($val, 1);
                                                }

                                                echo "$key: $val\n";
                                            }
                                            ?></textarea>
                                        </div>
                                    </form>
                                </div>
                            </div> <!-- .inside -->
                        </div> <!-- .postbox --> <!-- /quick-contact -->

                    </div> <!-- .meta-box-sortables -->

                </div> <!-- #postbox-container-1 .postbox-container -->

            </div> <!-- #post-body .metabox-holder .columns-2 -->

            <br class="clear">
        </div> <!-- #poststuff -->
	</div>

	<?php
}

function orbisius_simple_feedback_get_plugin_data() {
 // pull only these vars
    $default_headers = array(
		'Name' => 'Plugin Name',
		'PluginURI' => 'Plugin URI',
	);

    $plugin_data = get_file_data(__FILE__, $default_headers, 'plugin');

    $url = $plugin_data['PluginURI'];
    $name = $plugin_data['Name'];

    $data['name'] = $name;
    $data['url'] = $url;
    
    return $data;
}

/**
* adds some HTML comments in the page so people would know that this plugin powers their site.
*/
function orbisius_simple_feedback_add_plugin_credits() {
    // pull only these vars
    $default_headers = array(
		'Name' => 'Plugin Name',
		'PluginURI' => 'Plugin URI',
	);

    $plugin_data = get_file_data(__FILE__, $default_headers, 'plugin');

    $url = $plugin_data['PluginURI'];
    $name = $plugin_data['Name'];

    printf(PHP_EOL . PHP_EOL . '<!-- ' . "Powered by $name | URL: $url " . '-->' . PHP_EOL . PHP_EOL);
}

class orbisius_simple_feedback_util {
    /**
     * Generates radio or checkboxes. If $sel is empty the first element from the $options array
     * will be used as default. This supports checkboxes if [ is used in the name -> array values so it switches to checkboxes instead.
     * Multiple values will be selected.
     * $buff .= 'License: ' . orbisius_simple_feedback_util::html_boxes('license', empty($_REQUEST['license']) ? '' : $_REQUEST['license'], $license_types);
     *
     * @param type $name
     * @param type $sel
     * @param type $options
     * @param type $attr
     * @return string
     */
    public static function html_boxes($name = '', $sel = null, $options = array(), $attr = '') {
        $id = strtolower($name);
        $id = preg_replace('#[^\w-]#si', '_', $id);
        $id = trim($id, '_ ');
        $id = esc_attr($id);
        $name = esc_attr($name);
        $html = "\n<div id='$id' $attr>\n";

        // if we have [ that means that the user is supposed to select multiple values
        // therefore we'll use checkboxes. Smart, eh?
        $type = (strpos($name, '[]') === false) ? 'radio' : 'checkbox';
        $sep = "<br/>\n";

        if (0&&!is_null($sel) && empty($sel)) {
            $first_key = key($options); // First Element's Key
            //$first_value = reset($options); // First Element's Value

            $sel = $first_key;
        }

        $sel_mod = (array) $sel;

        foreach ($options as $key => $label) {
            $checked = count(array_intersect($sel_mod, array($key, $label))) ? ' checked="checked"' : '';
            $html .= "\t<label> <input type='$type' name='$name' value='$key' $checked> $label</label>" . $sep;
        }

        $html .= '</div>';
        $html .= "\n";

        return $html;
    }
}