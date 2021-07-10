<?php
/**
 * Plugin Name: Test project
 * Description: Testing project
 * Plugin URI: https://arman.co
 * Author: Armanul Islam
 * Author URI: https://arman.co
 * Version: 1.0
 * License: GPL2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action('init', 'register_brewery_cpt');

function register_brewery_cpt() {
    register_post_type('brewery', [
        'label' => 'Breweries',
        'public'    => true,
        'capability_type' => 'post'
    ]);
}

if( ! wp_next_scheduled('update_brewery_list') ) {
    wp_schedule_event( time(), 'weekly', 'update_brewery_list');
}

add_action( 'update_brewery_list', 'update_brewery_list' );
add_action( 'wp_ajax_nopriv_get_breweries_from_api', 'update_brewery_list' );
add_action( 'wp_ajax_get_breweries_from_api', 'update_brewery_list' );

function update_brewery_list() {
    $current_page = ( ! empty( $_POST['current_page'] ) ) ? $_POST['current_page'] : 1;
    $brr     = array();
    $results = wp_remote_retrieve_body( wp_remote_get('https://api.openbrewerydb.org/breweries/?page=' . $current_page .'&per_page=50'));
    $results = json_decode( $results );

    if( ! is_array( $results ) || empty( $results ) ){
        return false;
    }
    $brr[] = $results;

    foreach( $brr[0] as $br ) {
        $br_slug = slugify( $br->name . '-' . $br->id );
        $existing_br    = get_page_by_path( $br_slug, 'OBJECT', 'brewery');

        if( $existing_br === null ) {
            $inserted_brewery = wp_insert_post( [
                'post_name' => $br_slug,
                'post_title' => $br_slug,
                'post_type' => 'brewery',
                'post_status' => 'publish'
              ] );

              if( is_wp_error( $inserted_brewery ) || $inserted_brewery === 0 ) {
                  continue;
              }

              $fields = array(
                'field_60e9bf403eab9' => 'name',
                'field_60e9bf7c3eaba' => 'brewery_type',
                'field_60e9bf873eabb' => 'street',
                'field_60e9c45a45324' => 'updated_at',
              );

              foreach( $fields as $key => $name ) {
                update_field( $key, $br->$name, $inserted_brewery );
              }
        } else {
            $existing_br_id = $existing_br->ID;
            $existing_br_time = get_field('update_at', $existing_br_id);

            if( $br->updated_at >= $existing_br_time ) {
                $fields = array(
                    'field_60e9bf403eab9' => 'name',
                    'field_60e9bf7c3eaba' => 'brewery_type',
                    'field_60e9bf873eabb' => 'street',
                    'field_60e9c45a45324' => 'updated_at',
                  );
                foreach( $fields as $key => $name ){
                    update_field( $key, $existing_br_id );
                }
            }
        }
    }

    $current_page = $current_page + 1;

    wp_remote_post( admin_url('admin-ajax.php?action=get_breweries_from_api'), array( 
        'blocking'  => false,
        'sslverify' => false, 
        'body' => [
            'current_page'  => $current_page
        ]
    ) );
}

function slugify($text){

    // remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);
  
    // trim
    $text = trim($text, '-');
  
    // remove duplicate -
    $text = preg_replace('~-+~', '-', $text);
  
    // lowercase
    $text = strtolower($text);
  
    if (empty($text)) {
      return 'n-a';
    }
  
    return $text;
  }
