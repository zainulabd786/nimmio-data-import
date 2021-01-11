<?php

/**
 * Plugin Name: Nimmio Data Import
 * Description: Plugin to import data from old Nimmio MongoDB.
 * Version: 1.0
 * Requires at least: 5.2
 * Requires PHP: 7.2
 * Author: Zainul Abideen
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nimmio-data-import
 * Domain Path: /languages
 */

// function ndi_setup_activate(){
//     ndi_menu();
// }
// register_activation_hook(__FILE__, 'ndi_setup_activate');



add_action('admin_menu', 'ndi_menu');
function ndi_menu(){
    add_menu_page('Import Data', 'Nimmio Data Import', 'manage_options', 'nimmio_data_import', 'ndi_import_markup' );
}

function ndi_import_markup(){
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    } ?>

    <div class="wrap">
        <form action="admin-post.php" method="post">
            <input type="hidden" name="action" value="ndi_start_data_import">
            <?php wp_nonce_field("ndi_import_form_verify"); ?>
            <button type="submit" class="button button-primary button-large">Start Import</button>
        </form>

    </div><?php
}


/*Insert*/
add_action("admin_post_ndi_start_data_import", "ndi_start_data_import");
function ndi_start_data_import(){
	if(!current_user_can('edit_theme_options')) wp_die('You are not allowed to be on this page');
	check_admin_referer("ndi_import_form_verify");

    global $wpdb;
    $products = json_decode(file_get_contents('https://api.nimmio.com/api/slides/getSlides/?page=1&limit=500'))->data->results;;
    foreach( $products as $item ) {
        $post_id = wp_insert_post( array(
            'post_title' => $item->title,
            'post_content' => $item->description,
            'post_status' => 'publish',
            'post_type' => "product",
        ) );
        wp_set_object_terms( $post_id, 'simple', 'product_type' );
        update_post_meta( $post_id, '_visibility', 'visible' );
        update_post_meta( $post_id, '_stock_status', 'instock');
        update_post_meta( $post_id, 'total_sales', '0' );
        update_post_meta( $post_id, '_downloadable', 'yes' );
        update_post_meta( $post_id, '_virtual', 'yes' );
        update_post_meta( $post_id, '_regular_price', $item->price );
        update_post_meta( $post_id, '_sale_price', '' );
        update_post_meta( $post_id, '_purchase_note', '' );
        update_post_meta( $post_id, '_featured', 'no' );
        update_post_meta( $post_id, '_weight', '' );
        update_post_meta( $post_id, '_length', '' );
        update_post_meta( $post_id, '_width', '' );
        update_post_meta( $post_id, '_height', '' );
        update_post_meta( $post_id, '_sku', '' );
        update_post_meta( $post_id, '_product_attributes', array() );
        update_post_meta( $post_id, '_sale_price_dates_from', '' );
        update_post_meta( $post_id, '_sale_price_dates_to', '' );
        update_post_meta( $post_id, '_price', $item->price );
        update_post_meta( $post_id, '_sold_individually', '' );
        update_post_meta( $post_id, '_manage_stock', 'no' );
        update_post_meta( $post_id, '_backorders', 'no' );
        update_post_meta( $post_id, '_stock', '' );
        
        $slide = upload_attachment($item->slide_url, $post_id);
        $slide_url = $slide['url'];
        $status = 'ok';
        $downdloadArray = array();
        // file paths will be stored in an array keyed off md5(file path)
        $downdloadArray =array('name'=> $item->title, 'file' => $slide_url);

        $file_path =md5($slide_url);

        $_file_paths[  $file_path  ] = $downdloadArray;
        // grant permission to any newly added files on any existing orders for this product
        do_action( 'woocommerce_process_product_file_download_paths', $post_id, 0, $downdloadArray );
        update_post_meta( $post_id, '_downloadable_files', $_file_paths);
        update_post_meta( $post_id, '_download_limit', '');
        update_post_meta( $post_id, '_download_expiry', '');
        update_post_meta( $post_id, '_download_type', '');
        update_post_meta( $post_id, '_product_image_gallery', ''); 

        $thumbnail = upload_attachment($item->thumbnail_url, $post_id);
        set_post_thumbnail($post_id, $thumbnail['id']);
        $_file_paths = array();
    }
    wp_redirect(admin_url('admin.php?page=nimmio_data_import&status='.$status));
}



function upload_attachment( $image_url, $post_id  ){
    $upload_dir = wp_upload_dir();
    $image_data = file_get_contents($image_url);
    $filename = basename($image_url);
    $file = (wp_mkdir_p($upload_dir['path'])) ? $upload_dir['path'] . '/' . $filename : $upload_dir['basedir'] . '/' . $filename;
    $file = urldecode($file);
    file_put_contents($file, $image_data);
    $wp_filetype = wp_check_filetype($filename, null );
    $attachment = array(
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => sanitize_file_name($filename),
        'post_content' => '',
        'post_status' => 'inherit'
    );
    $attach_id = wp_insert_attachment( $attachment, $file );
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
    $res1= wp_update_attachment_metadata( $attach_id, $attach_data );
   return array('id' => $attach_id, 'url'=> wp_get_attachment_url($attach_id));
}


if (!function_exists('write_log')) {

    function write_log($log) {
        if (true === WP_DEBUG) {
            if (is_array($log) || is_object($log)) {
                error_log(print_r($log, true));
            } else {
                error_log($log);
            }
        }
    }

}
