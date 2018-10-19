<?php
/**
 * Created by PhpStorm.
 * User: Andrew
 * Date: 01.06.2018
 * Time: 10:35
 */

namespace NetworkPosts\Components;

class NetsPostsMultisite {

	public static function multisite_init() {
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			if ( isset( $_GET['action'] ) && isset( $_GET['plugin'] ) ) {
				$plugin_name = urldecode( $_GET['plugin'] );

				if ( $plugin_name == NETSPOSTS_MAIN_PLUGIN_FILE && $_GET['action'] == 'deactivate' ) {
					self::multisite_deactivate();
				}
			} elseif ( isset( $_GET['activate'] ) ) {
				self::multisite_activate();
			}
		}
	}

	private static function multisite_activate() {

		if ( function_exists( 'activate_plugin' ) ) {
			$blogs = get_sites(['fields' => 'ids']);

			foreach ( $blogs as $blog_id ) {
				self::activate( $blog_id );
			}
		}
	}

	private static function multisite_deactivate() {
		if ( function_exists( 'deactivate_plugins' ) ) {
			$blogs = get_sites(['fields' => 'ids']);

			foreach ( $blogs as $blog_id ) {
				self::deactivate( $blog_id );
			}
		}
	}

	private static function activate( $blog_id ) {
		switch_to_blog( $blog_id );

		if ( ! is_plugin_active_for_network( NETSPOSTS_MAIN_PLUGIN_FILE ) ) {
			$result = activate_plugin( NETSPOSTS_MAIN_PLUGIN_FILE );

			if ( $result ) {
				error_log( $result->get_error_message() );
			}
		}

		restore_current_blog();
	}

	private static function deactivate( $blog_id ) {
		switch_to_blog( $blog_id );

		deactivate_plugins( array( NETSPOSTS_MAIN_PLUGIN_FILE ), true );

		restore_current_blog();
	}

	public static function activate_new_blog_plugin( $blog_id ) {
		if ( ! is_plugin_active_for_network( NETSPOSTS_MAIN_PLUGIN_FILE ) ) {
			self::activate( $blog_id );
		}
	}
}