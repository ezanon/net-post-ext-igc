<?php
############  SETUP  ####################

/*
set_error_handler( function($errno, $errstr){
	print($errstr);
} );

set_exception_handler(function( $exception ){
	print($exception->message);
});
*/

require_once 'components/NetsPostsMultisite.php';
require_once 'components/NetsPostsSettings.php';
require_once 'components/NetsPostsTemplateRenderer.php';

use \NetworkPosts\Components\NetsPostsMultisite;
use \NetworkPosts\Components\NetsPostsSettings;

define( 'DEFAULT_THUMBNAIL_WIDTH', 300 );
define( 'BASE_JS_PATH', plugins_url( '/network-posts-extended/js' ) );
define( 'POST_VIEWS_PATH', plugin_dir_path( __FILE__ ) . 'views/post' );
define( 'NETSPOSTS_VIEW_PATH', plugin_dir_path( __FILE__ ) . 'views' );

//add_action( 'init', 'net_shared_posts_init' );
if( !defined( 'NETSPOSTS_TEST' ) ) {
	add_action('admin_init', 'netsposts_init_thumbnails_manager');
	add_action( 'admin_init', array( NetsPostsMultisite::class, 'multisite_init' ) );
	add_action( 'admin_init', array( NetsPostsSettings::class, 'register_settings' ) );
	add_action( 'wpmu_new_blog', array( NetsPostsMultisite::class, 'activate_new_blog_plugin' ) );

	\NetworkPosts\Components\NetsPostsTemplateRenderer::init( NETSPOSTS_VIEW_PATH );
	add_action( "plugins_loaded", "net_shared_posts_init" );
	add_shortcode( 'netsposts', 'netsposts_shortcode' );
	add_action( 'admin_menu', array( NetsPostsSettings::class, 'add_toolpage' ) );
	add_action( 'admin_enqueue_scripts', 'netsposts_init_settings_page' );
	add_action( 'network_admin_menu', 'netsposts_add_network_settings' );
	add_action( 'update_option', 'netsposts_save_for_blog' );
}
//This variable is needed for WP_EStore thumbnails

$img_sizes = [];

function netsposts_url( $relative_url ){
	return plugins_url( $relative_url, __FILE__ );
}

function netsposts_path( $file ){
	return plugin_dir_path( __FILE__ ) . '/' . $file;
}