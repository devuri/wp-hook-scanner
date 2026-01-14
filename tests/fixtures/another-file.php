<?php

/*
 * This file is part of the WP Hook Scanner package.
 *
 * (c) Uriel Wilson
 *
 * The full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// Duplicate of init hook from sample-plugin.php
add_action('init', 'another_init_function');

// Unique hooks
add_action('wp_enqueue_scripts', 'enqueue_my_scripts');
add_filter('body_class', 'add_custom_body_class');

// More applied filters
$result = apply_filters('my_plugin_value', 'different_default');
