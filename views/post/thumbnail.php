<?php/** * Created by PhpStorm. * User: Admin * Date: 04.04.2017 * Time: 8:13 */use NetworkPosts\Components\NetsPostsHtmlHelper;if ( ! defined( 'POST_VIEWS_PATH' ) ) {	die();}$html .= htmlspecialchars_decode( $shortcode_mgr->get( 'wrap_image_start' ) );$size        = $shortcode_mgr->get( 'size' );if( $size === 'parent theme' ){	$size = 'post-thumbnail';}$image_class = $shortcode_mgr->get( 'image_class' );$column      = $shortcode_mgr->get( 'column' );if ( $the_post['post_type'] != 'estore' ) {	$thumbnail = netsposts_get_thumbnail_by_blog( $the_post['blog_id'], $the_post['ID'], $size, $image_class, $column, $use_single_images_folder );} else {	$thumbnail = netsposts_create_estore_product_thumbnail( $the_post['thumbnail_url'], $the_post['post_title'], $size, $image_class, $column );}$html .= NetsPostsHtmlHelper::create_link($the_post['guid'], $thumbnail);$html .= htmlspecialchars_decode( $shortcode_mgr->get( 'wrap_image_end' ) );