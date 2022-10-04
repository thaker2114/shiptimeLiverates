<?php

/*
Plugin Name: Shiptime Live Rates
Description: This plugin adds the possibility to use live shipping services 
Version: 1.1
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /lang
Text Domain: a2c_ls
*/

/*
Live Shipping 4 Woocommerce is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

Live Shipping 4 Woocommerce is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with API2Cart webhook helper. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SHIPTIME_SHIPPING_SERVICE_VERSION' ) ) {
	define( 'SHIPTIME_SHIPPING_SERVICE_VERSION', '1.0' );
	define( 'SHIPTIME_SHIPPING_SERVICE_POST_TYPE', 'shiptime_shipping_method' );

	require_once 'app' . DIRECTORY_SEPARATOR . 'Shiptime_Shipping_Exception.php';

	/**
	 * @var A2c_shiptime_Shipping_Exception|null $a2c_shiptime_shipping_exception
	 */
	$a2c_shiptime_shipping_exception = null;

	function validate_WooCommerce()
	{
		if ( class_exists( 'WooCommerce' ) ) {
			$version = WooCommerce::instance()->version;
		}

		if ( empty( $version ) || version_compare( $version, '2.7' ) === - 1 ) {
			throw new A2c_shiptime_Shipping_Exception( __( 'Woocommerce 2.7+ is required. ', 'a2c_ls' ) );
		}

		return true;
	}
	
	function a2c_shiptime_shipping_init()
	{
		if ( SHIPTIME_SHIPPING_SERVICE_VERSION !== get_option( 'shiptime_shipping_service_version' ) ) {
			a2c_shiptime_shipping_activate();
		}
		global $a2c_shiptime_shipping_exception;
		try {
			validate_WooCommerce();
			register_post_type(
				'a2c_shiptime_shipping_service',
				array(
					'public'              => false,
					'hierarchical'        => false,
					'has_archive'         => false,
					'exclude_from_search' => false,
					'rewrite'             => false,
					'query_var'           => false,
					'delete_with_user'    => false,
					'_builtin'            => true,
				)
			);
			require_once 'app' . DIRECTORY_SEPARATOR . 'A2c_Shiptime_Shipping_Service.php';
			require_once 'app' . DIRECTORY_SEPARATOR . 'Shiptime_Shipping.php';
			
		} catch ( A2c_shiptime_Shipping_Exception $a2c_shiptime_shipping_exception ) {
		}
	}

	function a2c_shiptime_shipping_error()
	{
		global $a2c_shiptime_shipping_exception;

		if ( $a2c_shiptime_shipping_exception !== null ) {
			echo '
				<div class="error notice">
					<p>API2Cart Live Shipping 4 Woocommerce notice: <b>' . $a2c_shiptime_shipping_exception->getMessage() . '</b></p>
				</div>
			';
		}
	}

	function a2c_shiptime_shipping_activate()
	{
		global $a2c_shiptime_shipping_exception;

		try {
			validate_WooCommerce();
		} catch ( A2c_shiptime_Shipping_Exception $a2c_shiptime_shipping_exception ) {
			die ( $a2c_shiptime_shipping_exception->getMessage() );
		}

		if (is_multisite() && is_network_admin()) {
			$sites = get_sites();

			foreach ($sites as $site) {
				_activatePlugin($site->blog_id, true);
			}
			restore_current_blog();
		} else {
			_activatePlugin();
		}
	}

	function a2c_shiptime_shipping_deactivate()
	{
		if (is_multisite() && is_network_admin()) {
			$sites = get_sites();
			$pluginName = isset($GLOBALS['plugin']) ? $GLOBALS['plugin'] : '';

			foreach ($sites as $site) {
				switch_to_blog($site->blog_id);
				$activePlugins = (array)get_option('active_plugins', array());

				if (($key = array_search($pluginName, $activePlugins)) !== false) {
					unset($activePlugins[$key]);
					update_option('active_plugins', $activePlugins);
				}

				update_option('shiptime_shipping_service_active', false);
				update_option('woocommerce_shiptime_settings', false);
			}

			restore_current_blog();
		} else {

			update_option('shiptime_shipping_service_active', false);
			update_option('woocommerce_shiptime_settings', false);
		}
	}

	function a2c_shiptime_shipping_uninstall()
	{
		/**
		 * @global $wpdb wpdb Database Access Abstraction Object
		 */
		global $wpdb;
		$wpdb->query( 'DELETE FROM `' . $wpdb->prefix . 'posts` WHERE `post_type` = "' . shiptime_SHIPPING_SERVICE_POST_TYPE . '"' );
		delete_option( 'shiptime_shipping_service_version' );
		delete_option( 'shiptime_shipping_service_active' );
		delete_option( 'woocommerce_shiptime_settings' );
	}

	/**
	 * @param int  $siteId      Site Id
	 * @param bool $isMultisite Is Multisite Enabled
	 */
	function _activatePlugin($siteId = 1, $isMultisite = false)
	{
		if ($isMultisite) {
			switch_to_blog($siteId);
		}

		update_option('shiptime_shipping_service_version', SHIPTIME_SHIPPING_SERVICE_VERSION);
		update_option('shiptime_shipping_service_active', true);
		update_option('woocommerce_shiptime_settings', true);

		if (empty(get_option('shiptime_shipping_service_secret'))) {
			update_option('shiptime_shipping_service_secret', wp_generate_password(50, true, true));
		}
	}

	register_activation_hook( __FILE__, 'a2c_shiptime_shipping_activate' );
	register_uninstall_hook( __FILE__, 'a2c_shiptime_shipping_uninstall' );
	register_deactivation_hook( __FILE__, 'a2c_shiptime_shipping_deactivate' );

	add_action( 'plugins_loaded', 'a2c_shiptime_shipping_init', 10, 3 );
	add_action( 'admin_notices', 'a2c_shiptime_shipping_error' );

}
