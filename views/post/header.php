<?php/** * Created by PhpStorm. * User: Admin * Date: 04.04.2017 * Time: 8:10 */use NetworkPosts\Components\NetsPostsHtmlHelper;if ( ! defined( 'POST_VIEWS_PATH' ) ) {	die();}include 'title.php';$date_post = '';if( $shortcode_mgr->has_value( 'date_format' ) ){	$format = $shortcode_mgr->get( 'date_format' );	if( $format === 'settings' ){		$format = get_option( 'date_format' );	}}else{	$format = 'M j';}if ( array_key_exists( 'post_date', $the_post ) ) {	$date = new DateTime( trim( $the_post['post_date'] ) );	$date_post = date_i18n( $format, $date->getTimestamp() );}$meta_width = $shortcode_mgr->get( 'meta_width' );if ( $meta_width == "100%" ) {	$width = 'width: 100%;';} else {	$width = "width: " . $meta_width . "px;";}if ( ! $shortcode_mgr->get_boolean( 'hide_source' ) ) {	$html .= '<div class="netsposts-source" style="margin-bottom: 5px;' . $width . '">';	if ( $shortcode_mgr->get_boolean( 'meta_info' ) ) {		$html .= __( '<span>Published</span>', 'netsposts' ) . '' . __( '<span>', 'netsposts' ) . ' ' . $date_post . '' . __( '</span>', 'netsposts' ) . ' ' . __( '<span>in</span>', 'netsposts' )		         . '  <span>' . NetsPostsHtmlHelper::create_link( $blog_url, $blog_name, $open_link_in_new_tab ) . '</span><br/>';	}	if ( $shortcode_mgr->get_boolean( 'show_order' ) ) {		if ( isset( $tab_order_by1 ) && count( $tab_order_by1 ) > 0 && isset( $the_post[ $tab_order_by1[0] ] ) ) {			$fieldname = $tab_order_by1[0];			$fullname  = netsposts_create_label_from_id( $fieldname );			$html      .= "<span>" . $fullname . ": " . $the_post[ $tab_order_by1[0] ] . "</span><br/>";		}	}	$show_date   = false;	$date_column = '';	if ( isset( $show_before_date ) ) {		$show_date   = true;		$date_column = $show_before_date[0];	}	if ( isset( $show_after_date ) ) {		$show_date   = true;		$date_column = $show_after_date[0];	}	if ( isset( $show_for_today ) ) {		$show_date   = true;		$date_column = $show_for_today;	}	if ( $show_date && isset( $the_post[ $date_column ] ) ) {		$date = new DateTime( $the_post[ $date_column ] );		$date = date_i18n( $format, $date->getTimestamp() );		$html .= NetsPostsHtmlHelper::create_span( $date ) . '<br/>';	}##  Full metadata	if ( $show_author ) {		if ( $column > 1 ) {			$html .= '<br />';		}		$author_display_name = get_the_author_meta( 'display_name', $the_post['post_author'] );		$html                .= NetsPostsHtmlHelper::create_author_link( $blog_url, $the_post['post_author'], $author_display_name, $open_link_in_new_tab );	}	$html .= '</div>'; //end of netsposts-source}/** * Transferido para title.php * por Erickson Zanon em 17/10/2018 *//*if ( isset( $the_post['categories'] ) ) {	$html .= '<div class="netsposts-categories" style="margin-bottom: 5px; ' . $width . '">';	foreach ( $the_post['categories'] as $category ) {		$html .= NetsPostsHtmlHelper::create_category_link( $blog_url, $category['cat_id'], $category['cat_name'], $open_link_in_new_tab );	}	$html .= '</div>';}*/if( $shortcode_mgr->has_value( 'include_post_meta' ) ){	$meta_keys = $shortcode_mgr->split_array( 'include_post_meta', ',' );	$text = apply_filters( 'netsposts_get_meta_html', $the_post, $meta_keys );	$html .= '<div class="netsposts-extra-meta">';	if( is_string( $text ) ){		$html .= $text;	}	else{		foreach ($meta_keys as $key){			if( isset( $the_post[$key] ) ) {				$html .= '<span>' . $key . ': ' . $the_post[ $key ] . '</span>';			}		}	}	$html .= '</div>';}if( $shortcode_mgr->has_value( 'include_acf_fields' ) && function_exists( 'get_field_objects' ) ){	$fields = get_field_objects( $the_post['ID'], array( 'format_value' => true, 'load_value' => true ) );	if( $fields ) {		$fields_result = array();		if( $shortcode_mgr->get_boolean( 'include_acf_fields' ) ) {			$filter_fields = array_keys( $fields );		}		else{			$filter_fields = $shortcode_mgr->split_array( 'include_acf_fields', ',' );		}		foreach ($fields as $name => $field){			if( in_array( $name, $filter_fields ) ) {				$fields_result[ $name ] = array( 'label' => $field['label'], 'value' => $field['value'] );			}		}		$text = apply_filters( 'netsposts_get_acf_html', $fields_result, $the_post );		$html .= '<div class="netsposts-acf-fields">';		if ( is_string( $text ) ) {			$html .= $text;		} else {			foreach ( $fields_result as $name => $field ) {				$label = $field['label'];				$value = $field['value'];				if ( is_string( $value ) ) {					if( $shortcode_mgr->get_boolean( 'hide_acf_labels' ) ){						$html .= '<span>' . $value . '</span>';					}					else{						$html .= '<span>' . $label . ': ' . $value . '</span>';					}				} elseif ( is_array( $value ) ) {					$str = '';					if ( is_array( $value[0] ) ) {						$str = join( $value, ', ' );					} elseif ( $value[0] instanceof WP_Post ) {						$str = '';						foreach ( $value as $inner_post ) {							$str .= $inner_post->post_title . ', ';						}						$str = mb_substr( $str, 0, - 2 );					}					if( $shortcode_mgr->get_boolean( 'hide_acf_labels' ) ){						$html .= '<span>' . $str . '</span>';					}					else{						$html .= '<span>' . $name . ': ' . $str . '</span>';					}				}			}		}		$html .= '</div>';	}}