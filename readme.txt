=== Orbisius Simple Feedback ===

Contributors: lordspace
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=7APYDVPBCSY9A
Tags: wp,orbisius,feedback,chat,contact,contact form,contact, contact button, contact chat, contact form, contact plugin, contact us, contact form 7
Requires at least: 2.6
Tested up to: 3.8.1
Stable tag: 1.0.1
License: GPLv2 or later

Generates a nice & simple Feedback form which is positioned at the bottom center of your visitor's browser window.

== Description ==

= Support =
> Support is handled on our site: <a href="http://club.orbisius.com/" target="_blank" title="[new window]">http://club.orbisius.com/</a>
> Please do NOT use the WordPress forums or other places to seek support.

Generates a nice & simple Feedback form which is positioned at the bottom center of your visitor's browser window.
The data is sent via ajax to your admin email. The current page is also sent as well.
This plugin is useful when you have a private beta or want to collect some feedback from your users.

= Features / Benefits =
* Have a quick and easy way for your customers and clients to reach you.
* Get quick feedback for your site.
* Optionally Premium Extension: log the feedback. The plugin extension keeps track of failed and not failed email deliveries (based on wp_mail status)
* For logged in users their info is included.
* The Reply-to field is filled with the sender's email address ... Just hit reply.
* Small box which only expands when the user puts their mouse over it.
* Configure the call to action

== Demo ==
TODO

Bugs? Suggestions? If you want a faster response contact us through our website's contact form [ orbisius.com ] and not through the support tab of this plugin or WordPress forums.
We don't get notified when such requests get posted in the forums.

> Support is handled on our site: <a href="http://club.orbisius.com/" target="_blank" title="[new window]">http://club.orbisius.com/</a>
> Please do NOT use the WordPress forums or other places to seek support.

= Author =

Svetoslav Marinov (Slavi) | <a href="http://orbisius.com" title="Custom Web Programming, Web Design, e-commerce, e-store, Wordpress Plugin Development, Facebook and Mobile App Development in Niagara Falls, St. Catharines, Ontario, Canada" target="_blank">Custom Web and Mobile Programming by Orbisius.com</a>

== Upgrade Notice ==
n/a

== Screenshots ==
1. Plugins
2. Settings Page
3. Live demo (collapsed)
4. Live demo (expanded)

== Installation ==

1. Unzip the package, and upload `orbisius-simple-feedback` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= How to use this plugin? =
Just install the plugin and activate it. The feedback text appear in the public area

= How to remove the powered by? =

If you don't want to give us credit :( add this line to your functions.php

add_filter('orbisius_simple_feedback_filter_powered_by', '__return_false', 10);

== Changelog ==

= 1.0.2 =
* Including the IP address(es) of the person and their browser in the message
* Added alignment options for the feedback call to action: bottom left, bottom center, bottom right.
* Added an option to choose of having a textbox or a textarea for the feedback message
* Tested with WP 3.8.1

= 1.0.1 =
* Added a setting so the feedback box can be optionally shown in the admin area as well.

= 1.0.0 =
* Initial release
