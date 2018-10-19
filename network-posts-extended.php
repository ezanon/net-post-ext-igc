<?php

/*
Plugin Name: Network Posts Ext
Plugin URI: https://wordpress.org/plugins/network-posts-extended/
Description: Network Posts Extended plugin enables you to share posts over WP Multi Site network.  You can display on any blog in your network the posts selected by taxonomy from any blogs including main.
Version: 0.6.3
Author: John Cardell, Superfrontender
Author URI: https://www.johncardell.com
Text Domain: network-posts-extended
*/

if ( realpath( __FILE__ ) == realpath( $_SERVER['SCRIPT_FILENAME'] ) ) {
	exit( 'Please don\'t access this file directly.' );
}
define( 'NETSPOSTS_MAIN_PLUGIN_FILE', __FILE__ );

require_once 'components/NetsPostsDBQuery.php';
require_once 'components/NetsPostsShortcodeManager.php';
require_once 'components/NetsPostsSettings.php';
require_once 'components/resizer/NetsPostsResizerSettingsScreen.php';
require_once 'components/resizer/NetsPostsThumbnailBlogSettings.php';
require_once 'components/resizer/NetsPostsImageResizerFacade.php';

use NetworkPosts\Components\NetsPostsDBQuery;
use NetworkPosts\Components\NetsPostsShortcodeManager;
use NetworkPosts\Components\Resizer;

require_once 'network-posts-init.php';

$plugin = plugin_basename( __FILE__ );

add_filter( "plugin_action_links_$plugin", array(
	\NetworkPosts\Components\NetsPostsSettings::class,
	'plugin_settings_link'
) );

function netsposts_add_network_settings() {
	if ( is_super_admin() ) {
		Resizer\NetsPostsResizerSettingsScreen::add_settings_page();
	}
}

function net_shared_posts_init() {
	register_uninstall_hook( __FILE__, 'net_shared_posts_uninstall' );
	if( get_option( 'load_plugin_styles', 1 ) ) {
		add_action( 'wp_enqueue_scripts', 'netposts_add_stylesheet' );
	}
	load_plugin_textdomain( 'netsposts', false, basename( dirname( __FILE__ ) ) . '/language' );
}

function netsposts_init_thumbnails_manager() {
	global $wpdb;
	$is_resizing_allowed = Resizer\NetsPostsThumbnailBlogSettings::is_allowed_for_blog( get_current_blog_id() );
	$is_global_resizing  = Resizer\NetsPostsThumbnailBlogSettings::is_global( get_current_blog_id() );
	Resizer\NetsPostsImageResizerFacade::getInstance( $is_resizing_allowed, $is_global_resizing );
}

function netposts_add_stylesheet() {
	wp_register_style( 'netsposts_css', plugins_url( '/css/net_posts_extended.css', __FILE__ ) );

	wp_enqueue_style( 'netsposts_css' );
}

function netsposts_init_settings_page() {
	if ( isset( $_GET['page'] ) && $_GET['page'] == 'netsposts_page' ) {
		wp_register_style( 'netsposts_admin_css', plugins_url( '/css/settings.css', __FILE__ ) );
		wp_enqueue_style( 'netsposts_admin_css' );
		Resizer\NetsPostsImageResizerFacade::getInstance()->register_scripts();
	}
}

function net_shared_posts_uninstall() {
	remove_shortcode( 'netsposts' );
}

function netsposts_save_for_blog() {
	if ( @$_REQUEST['option_page'] === 'netsposts_page' ) {
		$blog_id = get_current_blog_id();
		if ( isset( $_POST['blog_resizer_options'] ) ) {
			if ( isset( $_POST['allowed'] ) ) {
				Resizer\NetsPostsThumbnailBlogSettings::allow_for_blog( $blog_id );
			} else {
				Resizer\NetsPostsThumbnailBlogSettings::restrict_for_blog( $blog_id );
			}
			if ( isset( $_POST['global'] ) ) {
				Resizer\NetsPostsThumbnailBlogSettings::make_global( $blog_id );
			} else {
				Resizer\NetsPostsThumbnailBlogSettings::delete_from_global( $blog_id );
			}
		}
	}
}

function netsposts_shortcode( $atts ) {
	global $wpdb;
	$db_manager = NetsPostsDBQuery::new_instance( $wpdb );

	$use_single_images_folder = get_option( 'use_single_images_folder', false );

	$shortcode_mgr = NetsPostsShortcodeManager::newInstance( $atts );

	if ( ! empty( $_GET ) ) {
		$shortcode_mgr->add_attributes( $_GET );
	}

########  OUTPUT STAFF  ####################

	$titles_only = $shortcode_mgr->get_boolean( 'titles_only' );

	$thumbnail = $shortcode_mgr->get_boolean( 'thumbnail' );

	$paginate = $shortcode_mgr->get_boolean( 'paginate' );

	$auto_excerpt = $shortcode_mgr->get_boolean( 'auto_excerpt' );

	$show_author = $shortcode_mgr->get_boolean( 'show_author' );

	$full_text = $shortcode_mgr->get_boolean( 'full_text' );

	$prev_next = $shortcode_mgr->get_boolean( 'prev_next' );

	$random = $shortcode_mgr->get_boolean( 'random' );

	/* my updates are finished here */

	$price_woocommerce = false;

	$price_estore = false;

	$key_name = 'exclude_link_title_posts';

	if ( $shortcode_mgr->has_value( 'exclude_link_title_posts' ) ) {
		$exclude_title_links = $shortcode_mgr->split_array( $key_name, ',' );
	}

	global $img_sizes;

	global $wpdb;

	$key_name = 'include_price';

	if ( $shortcode_mgr->has_value( $key_name ) ) {
		$is_match = $shortcode_mgr->is_match( $key_name, '/(\|)+/' );

		if ( $is_match ) {
			$exs = $shortcode_mgr->split_array( $key_name, '|' );
		} else {
			$exs = [ $shortcode_mgr->get( $key_name ) ];
		}

		foreach ( $exs as $ex ) {
			if ( $ex == 'woocommerce' ) {
				$price_woocommerce = true;
			} elseif ( $ex == 'estore' ) {
				$price_estore = true;
			}
		}
	}

	$woocommerce_installed = $db_manager->is_woocommerce_installed();

	$estore_installed = $db_manager->is_estore_installed();

	global $table_prefix;

	define( "WOOCOMMERCE", "woocommerce" );

	define( "WPESTORE", "estore" );

	$key_name = 'limit';

	$limit = '';
	if ( $shortcode_mgr->has_value( $key_name ) ) {
		$value = $shortcode_mgr->get( $key_name );

		$limit = " LIMIT 0, $value";
	}

	## Include blogs

	$include = '';
	$exclude = '';

	if ( $shortcode_mgr->has_value( 'include_blog' ) ) {
		$include_arr = $shortcode_mgr->split_array( 'include_blog', ',' );

		$include = " AND (";

		foreach ( $include_arr as $included_blog ) {
			if ( is_numeric( $included_blog ) ) {
				$include .= " blog_id = $included_blog  OR";
			}
		}

		$include = substr( $include, 0, strlen( $include ) - 2 );

		$include .= ")";
	} else {
		if ( $shortcode_mgr->has_value( 'exclude_blog' ) ) {
			$exclude_arr = $shortcode_mgr->split_array( 'exclude_blog', ',' );
			$exclude     = " AND (";
			foreach ( $exclude_arr as $exclude_blog ) {
				if ( is_numeric( $exclude_blog ) ) {
					$exclude .= "blog_id != $exclude_blog  OR";
				}
			}
			$exclude = substr( $exclude, 0, strlen( $exclude ) - 2 );

			$exclude .= ")";
		}
	}

	$BlogsTable = $wpdb->base_prefix . 'blogs';

	/* below is my updates */

	$page = get_query_var( 'paged' );

	if ( ! $page ) {
		$page = get_query_var( 'page' );
	}

	if ( ! $page ) {
		$page = 1;
	}

	$is_paginate = $page > 1 && $paginate;

	$blogs = $db_manager->get_blogs( $random, $is_paginate, $include, $exclude );

	## Getting posts

	$postdata = array();

	$prices = array();

	if ( $blogs ) {
		$img_sizes = netsposts_get_image_sizes( $blogs );

		if ( $shortcode_mgr->has_value( 'show_before_date' ) ) {
			$join_meta_table = true;
			$filter_values   = $shortcode_mgr->split_array( 'show_before_date', '::' );
			$filter_column   = $filter_values[0];
			if ( strtolower( $filter_column ) !== 'post_date' ) {
				$meta_keys[] = $filter_column;
			}
		}

		foreach ( $blogs as $blog_id ) {
			//must be called here
			$db_manager->init( $blog_id );

			if ( $shortcode_mgr->has_value( 'days' ) ) {
				$days = $shortcode_mgr->get( 'days' );

				$old = $db_manager->create_by_date_query_path( 'post_date', $days );

				$old = "AND $old";
			} else {
				$old = "";
			}

			$ids = '';

			$estore_ids = '';
            $include_posts_id = null;

            if( $shortcode_mgr->has_value('include_post') ) {
				$include_posts_id = $shortcode_mgr->split_array( 'include_post', ',' );
			}
			if ( $shortcode_mgr->has_value( 'taxonomy_type' ) ) {
				$db_manager->set_taxonomy_type( $shortcode_mgr->split_array( 'taxonomy_type', ',' ) );
			}

			if ( $shortcode_mgr->has_value( 'taxonomy' ) ) {
				$taxonomy = $shortcode_mgr->split_array( 'taxonomy', ',' );

				$ids = $db_manager->get_formatted_category_post_ids( $taxonomy, 'include', $include_posts_id );

				if ( strlen( $ids ) > 0 ) {
					$ids = ' AND ' . $ids;
				}
				if ( $estore_installed ) {
					$estore_ids = $db_manager->get_formatted_estore_category_post_ids( $taxonomy, 'include', $include_posts_id );
				}
			}

			if ( $shortcode_mgr->has_value( 'exclude_taxonomy' ) ) {
				$exclude_taxonomy = $shortcode_mgr->split_array( 'exclude_taxonomy', ',' );

				$post_ids = $db_manager->get_formatted_category_post_ids( $exclude_taxonomy, 'exclude', $include_posts_id );

				if ( ! empty( $post_ids ) ) {
					$ids .= " AND {$post_ids}";
				}
				if ( $estore_installed ) {
					$estore_post_ids = $db_manager->get_formatted_estore_category_post_ids( $exclude_taxonomy, 'exclude', $include_posts_id );

					if ( ! empty( $estore_post_ids ) ) {
						$estore_ids .= ' AND (';

						$estore_ids .= $estore_post_ids . ')';

						unset( $estore_post_ids );
					}
				}
			}

			/* below is my updates */

			$order_by = "";

			$aorder = array();

			$aorder1 = array();

			$meta_sort = false;
			$meta_keys = [];

			if ( $shortcode_mgr->has_value( 'order_post_by' ) ) {
				$tab_order_by1 = $shortcode_mgr->split_array( 'order_post_by', ' ' );

				$ordad = ( $tab_order_by1[1] ) ? $tab_order_by1[1] : "ASC";

				$aorder = array_merge( $aorder, array( $tab_order_by1[0] => $ordad ) );

				if ( $tab_order_by1[0] == "date_order" ) {
					$ordad0 = "post_date";
				} elseif ( $tab_order_by1[0] == "alphabetical_order" ) {
					$ordad0 = "post_title";
				} elseif ( $tab_order_by1[0] == "id" ) {
					$ordad0 = "ID";
				} else {
					$ordad0          = $tab_order_by1[0];
					$join_meta_table = true;
					$meta_keys[]     = $tab_order_by1[0];
				}

				$order_by .= $db_manager->create_orderby_query_path( $ordad0 . ' ' . $ordad );

				if ( strtoupper( $tab_order_by1[1] ) == "DESC" ) {
					$ordad1 = SORT_DESC;
				} else {
					$ordad1 = SORT_ASC;
				}

				$aorder1 = array_merge( $aorder1, array( $ordad0 => $ordad1 ) );
			}

			if ( $shortcode_mgr->has_value( 'post_type' ) ) {
				$post_type_array = $shortcode_mgr->split_array( 'post_type', ',' );

				$post_type_search = "";

				if ( count( $post_type_array ) > 0 ) {
				    $post_type_search = format_inclusion($db_manager->get_table_name('posts_table'), 'post_type', $post_type_array, false);
				    /*
					$post_type_search = "(";

					foreach ( $post_type_array as $type ) {
						$post_type_search .= "(post_type = '" . trim( $type ) . "') OR ";
					}

					$search_len = strlen( $post_type_search );

					if ( $search_len > 4 ) {
						$post_type_search = mb_substr( $post_type_search, 0, $search_len - 4 ) . ")";
					}
				    */
				} else {
					$post_type_search .= "(post_type = '" . trim( $shortcode_mgr->get( 'post_type' ) ) . "')";
				}
			} else {
				$post_type_search = "(post_type  = 'post' OR post_type = 'product')";
			}

			if ( $shortcode_mgr->has_value( 'show_after_date' ) ) {
				$filter_values = $shortcode_mgr->split_array( 'show_after_date', '::' );
				$filter_column = $filter_values[0];
				if ( $filter_column !== 'post_date' ) {
					$meta_keys[] = $filter_column;
				}
			}
			if ( $shortcode_mgr->has_value( 'show_before_date' ) ) {
				$filter_values = $shortcode_mgr->split_array( 'show_before_date', '::' );
				$filter_column = $filter_values[0];
				if ( $filter_column !== 'post_date' ) {
					$meta_keys[] = $filter_column;
				}
			}
			if ( $shortcode_mgr->has_value( 'show_for_date' ) ) {
				$filter_column = $shortcode_mgr->get( 'show_for_today' );
				if ( $filter_column !== 'post_date' ) {
					$meta_keys[] = $filter_column;
				}
			}
            $args = array(
	            'is_random' => $random,
                'paginate' => $is_paginate,
                'post_type_search' => $post_type_search,
                'ids' => $ids,
                'old' => $old,
                'limit' => $limit,
                'meta_keys' => $meta_keys,
                'include_meta' => $shortcode_mgr->has_value( 'include_post_meta' ) ?
                                    $shortcode_mgr->split_array( 'include_post_meta', ',' ) :
                                    false
            );
			$the_post        = $db_manager->get_posts( $args );
			$show_categories = $shortcode_mgr->get_boolean( 'show_categories' );

			$count = count( $the_post );

			switch_to_blog( $blog_id );
			for ( $i = 0; $i < $count; $i ++ ) {
				if ( isset( $the_post[ $i ] ) ) {
					$item = $the_post[ $i ];
					/*
					 * Check whether $post contains meta value and set
					 * post field with name 'meta_key' and value 'meta_value'
					 */
					if ( isset( $item['meta_key'] ) && isset( $item['meta_value'] ) ) {
						$the_post[ $i ][ $item['meta_key'] ] = $item['meta_value'];
						unset( $the_post[ $i ]['meta_key'] );
						unset( $the_post[ $i ]['meta_value'] );
					}
					/*
					 * Search for $post duplications, set values to current $post and remove copies from array.
					 */
					for ( $j = $i + 1; $j < $count; $j ++ ) {
						if ( isset( $the_post[ $j ] ) ) {
							$next = $the_post[ $j ];
							if ( $next['ID'] === $item['ID'] ) {
								if ( isset( $next['meta_key'] ) && isset( $next['meta_value'] ) ) {
									$the_post[ $i ][ $next['meta_key'] ] = $next['meta_value'];
									unset( $the_post[ $j ] );
								}
							}
						}
					}
					$the_post[ $i ]['blog_id'] = $blog_id;
					if ( $show_categories ) {
						$the_post[ $i ]['categories'] = $db_manager->get_post_categories( $item['ID'] );
					}
					$the_post[ $i ]['guid'] = get_permalink( $item['ID'] );
				}
			}
			restore_current_blog();

			$postdata = array_merge_recursive( $postdata, $the_post );

			if ( $estore_installed ) {
				if ( ! empty( $estore_ids ) ) {
					$estore_ids = ' WHERE ' . $estore_ids;
				}

				$estore_posts = $db_manager->get_estore_products( $random, $is_paginate, $estore_ids, $limit );

				foreach ( $estore_posts as &$item ) {
					$item['blog_id'] = $blog_id;
				}

				$postdata = array_merge_recursive( $postdata, $estore_posts );
			}

			$ids = '';
		}
	}

	$show_before_date = null;
	$show_after_date  = null;
	$show_for_today   = null;

	if ( $shortcode_mgr->has_value( 'show_after_date' ) ) {
		$filter_values   = $shortcode_mgr->split_array( 'show_after_date', '::' );
		$filter_column   = $filter_values[0];
		$show_after_date = [ $filter_column, netsposts_strtodate( $filter_values[1] ) ];
	}
	if ( $shortcode_mgr->has_value( 'show_before_date' ) ) {
		$filter_values    = $shortcode_mgr->split_array( 'show_before_date', '::' );
		$filter_column    = $filter_values[0];
		$show_before_date = [ $filter_column, netsposts_strtodate( $filter_values[1] ) ];
	}
	if ( $shortcode_mgr->has_value( 'show_for_today' ) ) {
		$filter_column  = $shortcode_mgr->get( 'show_for_today' );
		$show_for_today = $filter_column;
	}
	if ( $show_before_date || $show_after_date
	     || $show_for_today ) {
		$filtered_items = netsposts_filter_by_date( $postdata, $show_after_date, $show_before_date, $show_for_today );
	}
	if ( isset( $filtered_items ) ) {
		$postdata = $filtered_items;
	}
	/* below is my updates */

	if ( ! $random ) {
		if ( isset( $order_by ) && $order_by == "" ) {
			usort( $postdata, "custom_sort" );
		} elseif ( isset( $aorder1 ) ) {
			$postdata = array_msort( $postdata, $aorder1 );
		}
	}

	/* my updates are finished here */

	/* below is my updates */
	if ( $shortcode_mgr->has_value( 'exclude_post' ) ) {
		$exclude_post2 = $shortcode_mgr->split_array( 'exclude_post', ',' );
	} else {
		$exclude_post2 = [];
	}

	/* exclude latest n elements from categories */
	if ( $shortcode_mgr->has_value( 'number_latest_x_posts_excluded' ) &&
	     $shortcode_mgr->has_value( 'exclude_latest_x_posts_from_category' )
	) {
		$skipped_categories = $shortcode_mgr->split_array( 'exclude_latest_x_posts_from_category', ',' );

		if ( count( $skipped_categories ) > 0 ) {
			$excluded_posts = $db_manager->get_skipped_ids( $skipped_categories, (int) $shortcode_mgr->get( 'number_latest_x_posts_excluded' ) );

			$exclude_post2 = array_merge( $exclude_post2, $excluded_posts );
		}
	}

	foreach ( $exclude_post2 as $row ) {
		if ( is_array( $row ) && isset( $row['ID'] ) ) {
			removeElementWithValue( $postdata, "ID", $row['ID'] );
		} else {
			removeElementWithValue( $postdata, "ID", $row );
		}
	}

	/* my updates are finished here */

	if ( $shortcode_mgr->has_value( 'include_post' ) ) {
		$include_post2 = $shortcode_mgr->split_array( 'include_post', ',' );
		$newPostdata   = [];

		foreach ( $postdata as $postX ) {
			if ( in_array( $postX['ID'], $include_post2 ) ) {
				$newPostdata[] = $postX;
			}
		}

		$postdata = $newPostdata;
	}

	$list   = $shortcode_mgr->get( 'list' );
	$column = $shortcode_mgr->get( 'column' );

	if ( is_array( $postdata ) ) {
		$skip = 0;

		if ( $shortcode_mgr->has_value( 'number_latest_x_posts_excluded' ) &&
		     ! $shortcode_mgr->has_value( 'exclude_latest_x_posts_from_category' )
		) {
			$skip = (int) $shortcode_mgr->get( 'number_latest_x_posts_excluded' );
		}

		if ( $paginate ) {
			if ( $column > 1 ) {
				$column_list = ceil( $list / $column );

				$list = $column_list * $column;

				if ( ! $list ) {
					$list = $column;

					$column_list = 1;
				}
			}

			$total_records = count( $postdata ) - $skip;

			$total_pages = ceil( $total_records / $list );
			$postdata    = array_slice( $postdata, ( $page - 1 ) * $list + $skip, $list );
		} /* below is my updates */

		else {
			$postdata = array_slice( $postdata, $skip, $list );
		}

		/* my updates are finished here */
		if ( $column > 1 ) {
			$count = count( $postdata );

			if ( ! $paginate ) {
				$column_list = ceil( $count / $column );
			}

			for ( $i = 0; $i < $column; ++ $i ) {
				if ( $count < ( $column_list * $column ) ) {
					$column_list = ceil( $count / $column );
				}

				$colomn_data[ $i ] = array_slice( $postdata, ( $i ) * $column_list, $column_list );
			}
		} else {
			$colomn_data[0] = $postdata;
		}
	}

	## OUTPUT

	if ( $shortcode_mgr->has_value( 'page_title_style' ) ) {
		$page_title_style = $shortcode_mgr->get( 'page_title_style' );

		?>
        <style type="text/css">
            h2.pagetitle {

            <?php echo  $page_title_style; ?> <?php //echo get_option('net-style'); ?>

            }
        </style>
		<?php

	}

	$html = '<div class="netsposts-menu">';

	if ( $shortcode_mgr->has_value( 'menu_name' ) ) {
		$menu = array(
			'menu'            => $shortcode_mgr->get( 'menu_name' ),
			'menu_class'      => $shortcode_mgr->get( 'menu_class' ),
			'container_class' => $shortcode_mgr->get( 'container_class' )
		);

		wp_nav_menu( $menu );
	}

	if ( $shortcode_mgr->has_value( 'link_open_new_window' ) ) {
		$link_open_new_window = strtolower( $shortcode_mgr->get( 'link_open_new_window' ) ) === 'true' ? true
			: $shortcode_mgr->split_array( 'link_open_new_window', ',' );
	} else {
		$link_open_new_window = false;
	}

	$html .= '</div>';

	if ( $postdata ) {
		$html.= '<div id="post-wrapper" class="post-wrapper clearfix">'; //erickson
                //$html .= '<div class="netsposts-block-wrapper">';

		if ( $shortcode_mgr->has_value( 'post_height' ) ) {
			$post_height    = $shortcode_mgr->get( "post_height" );
			$height_content = "height: " . $post_height . "px;";
		} else {
			$height_content = "";
		}

		if ( $shortcode_mgr->has_value( 'title' ) ) {
			$html .= '<span class="netsposts-title">' . $shortcode_mgr->get( 'title' ) . '</span><br />';
		}

		$use_layout        = $shortcode_mgr->get( 'use_layout' );
		$use_inline_layout = isset( $use_layout ) && strtolower( $use_layout ) == "inline";

		foreach ( $colomn_data as $data ) {
			if ( $column > 1 ) {
			    $cw = $shortcode_mgr->has_value( 'column_width' ) ?
                        'width: ' . $shortcode_mgr->get('column_width') : '';
				$html .= '<div class ="netsposts-column" style="' . $cw . '">';
			}

			foreach ( $data as $key => $the_post ) {
				$open_link_in_new_tab = $link_open_new_window === true ||
				                        is_array( $link_open_new_window ) &&
				                        in_array( $the_post['ID'], $link_open_new_window ) ? ' target="_blank"' : '';

				$blog_details = get_blog_details( $the_post['blog_id'] );

				$blog_name = $blog_details->blogname;

				$blog_url = $blog_details->siteurl;

				if ( $shortcode_mgr->has_value( 'wrap_start' ) ) {
					$html .= html_entity_decode( $shortcode_mgr->get( 'wrap_start' ) );
				}

				$html .= '<article class="post type-post">'; //erickson 
                                //$html .= '<div class="netsposts-content" style="' . $height_content . '">';

				if ( $use_inline_layout ) {
					include( POST_VIEWS_PATH . '/layout/layout_inline.php' );
				} else {
					include( POST_VIEWS_PATH . '/layout/layout_default.php' );
				}

				$html.= "</article>\n"; //erickson
                                //$html .= '</div>';//end of netsposts-content

				if ( $shortcode_mgr->has_value( 'wrap_end' ) ) {
					$html .= html_entity_decode( $shortcode_mgr->get( 'wrap_end' ) );
				}
			}
			$html .= '<div class="end-netsposts-content"></div>';

			if ( $column > 1 ) {
				$html .= '</div>';
			} //end of netsposts-column
		}

		$html .= '<div class="clear"></div>';

		if ( ( $paginate ) and ( $total_pages > 1 ) ) {
			$html .= '<div class="netsposts-paginate">';

			$big = 999999999;

			$url_format = is_single() ? '?page=%#%' : '?paged=%#%';

			$pagination = paginate_links( array(

				'base' => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),

				'format' => $url_format,

				'current' => $page,

				'total' => $total_pages,

				'show_all' => !$shortcode_mgr->get_boolean( 'prev_next' ),

				'prev_next' => $shortcode_mgr->get_boolean( 'prev_next' ),

				'prev_text' => __( $shortcode_mgr->get( 'prev' ) ),

				'next_text' => __( $shortcode_mgr->get( 'next' ) ),

				'end_size' => $shortcode_mgr->get( 'end_size' ),

				'mid_size' => $shortcode_mgr->get( 'mid_size' )

			) );
			if ( is_single() ) {
				$url        = get_permalink();
				$pagination = netsposts_modify_pagination( $url, $pagination, $page );
			}
			$html .= $pagination;

			$html .= '</div>';
		}

		$html .= '</div>'; //end of netsposts-block-wrapper

	}

	return $html;
}

##########################################################

function netsposts_get_thumbnail_by_blog( $blog_id, $post_id, $size, $image_class, $column, $use_single_images_folder ) {
	$current_blog_id = get_current_blog_id();

	$current_blog = get_blog_details( array( 'blog_id' => $current_blog_id, 'path', 'domain' ), false );

	switch_to_blog( $blog_id );

	$thumb_id = has_post_thumbnail( $post_id );

	if ( ! $thumb_id ) {
		restore_current_blog();

		return false;
	}

	$blogdetails = get_blog_details( $blog_id );

	$sizes = netsposts_get_image_sizes( array( $blog_id ) );

	if ( array_key_exists( $size, $sizes ) ) {
		$image_size = $sizes[ $size ];
	} else {
		$image_size = $sizes['thumbnail'];
	}

	if ( $column > 1 ) {
		$image_class = $image_class . " more-column";
	}

	$attrs = array( 'class' => $image_class );

	$use_compressed_images = get_option( 'use_compressed_images', true );

	if ( $use_compressed_images ) {
		$img = get_the_post_thumbnail( $post_id, array(
			$image_size['width'],
			$image_size['height']
		), $attrs );
	} else {
		$img = get_the_post_thumbnail( $post_id, array(
			$image_size['width'],
			$image_size['height']
		), $attrs );
		$img = preg_replace( '/(\bsizes\=.*?\")[\s\/]/', "", $img );
		$img = preg_replace( '/(\bsrcset\=.*?\\")[\s\/]/', "", $img );
	}

	if ( ! $use_single_images_folder ) {
		$thumbcode = str_replace( $current_blog->domain . $current_blog->path, $blogdetails->domain . $blogdetails->path, $img );
	} else {
		$thumbcode = $img;
	}

	restore_current_blog();

	return $thumbcode;
}

function netsposts_get_image_sizes( $blog_ids ) {
	global $_wp_additional_image_sizes;

	$sizes = array();

	foreach ( $blog_ids as $id ) {
		switch_to_blog( $id );

		foreach ( get_intermediate_image_sizes() as $_size ) {
			if ( in_array( $_size, array( 'thumbnail', 'medium', 'medium_large', 'large' ) ) ) {
				$sizes[ $_size ]['width'] = get_option( "{$_size}_size_w" );

				$sizes[ $_size ]['height'] = get_option( "{$_size}_size_h" );

				$sizes[ $_size ]['crop'] = (bool) get_option( "{$_size}_crop" );
			} elseif ( isset( $_wp_additional_image_sizes[ $_size ] ) ) {
				$sizes[ $_size ] = array(

					'width' => $_wp_additional_image_sizes[ $_size ]['width'],

					'height' => $_wp_additional_image_sizes[ $_size ]['height'],

					'crop' => $_wp_additional_image_sizes[ $_size ]['crop'],

				);
			}
		}

		restore_current_blog();
	}

	return $sizes;
}

function netsposts_create_estore_product_thumbnail( $image_url, $alt, $size = 'thumbnail', $image_class = '', $column = 1 ) {
	global $img_sizes;

	if ( ! empty( $image_url ) ) {
		$img = '<img src="' . $image_url . '" alt="' . $alt . '" ';

		if ( $image_class ) {
			$img .= 'class="' . $image_class;
		}

		if ( $column > 1 ) {
			$img .= ' more-column';
		}

		$img .= '"';

		if ( array_key_exists( $size, $img_sizes ) ) {
			$img .= 'width="' . $img_sizes[ $size ]['width'] . 'px" ';

			$img .= 'height="' . $img_sizes[ $size ]['height'] . 'px"';
		}

		$img .= '/>';

		return $img;
	}

	return '';
}