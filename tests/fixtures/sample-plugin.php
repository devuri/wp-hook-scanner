<?php

/*
 * This file is part of the WP Hook Scanner package.
 *
 * (c) Uriel Wilson
 *
 * The full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// Actions
add_action('init', 'my_init_function');
add_action('wp_loaded', 'my_loaded_function');
add_action('admin_init', 'my_admin_init');

// Firing custom actions
do_action('my_plugin_loaded');
do_action('my_custom_event', $arg1, $arg2);

// Filters
add_filter('the_content', 'my_content_filter');
add_filter('the_title', 'my_title_filter', 10, 2);

// Applying filters
$value = apply_filters('my_plugin_value', $default);
$modified = apply_filters('my_custom_filter', $data, $context);
