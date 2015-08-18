<?php
/**
 * Plugin Name: Backbone Wordpress API
 * Plugin URI: 
 * Description: Backbone Wordpress API, provides an api to connect backbone requests with the wordpress
 * Version: 0.5.1
 * Author: Sef
 * Author URI: 
 * License: 
 *
 */


/* check first if some one else registered the class before */
if ( ! class_exists('BB_WP_API') ) {
	define('BB_WP_API_FILE', (__FILE__));
	define('BB_WP_API_PATH', dirname((__FILE__)));
	
	require_once ( BB_WP_API_PATH .  '/class-api.php');	
}