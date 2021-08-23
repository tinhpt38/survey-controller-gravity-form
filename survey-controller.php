<?php
error_reporting(E_ERROR | E_PARSE);
/**
 * Plugin Name: Survey Controller Gravity Form
 * Plugin URI: https://dalathub.com
 * Description: Allow the member can create Gravity Form and invite friends
 * Author: tinhpt.38@gmail.com
 * Author URI: https://dalathub.com
 * Version: 1.0.0
 * Text Domain: survey-controller
 * Domain Path: /languages/
 */


require_once __DIR__ . '/includes/database_helper.php';

add_action('gform_form_list_forms', 'udoo_pre_render_function', 10, 6);

function udoo_pre_render_function($forms, $search_query, $active, $sort_column, $sort_direction, $trash)
{
    error_log(__METHOD__);
    if (current_user_can('administrator')) {
        return $forms;
    }
   
    $db_helper = new DatabaseHelper();
    global $current_user;
    $final_forms = array();
    foreach ($forms as $form) {
        $map_author = $db_helper->get($form->id)[0];
        if ($map_author->user_id == $current_user->ID) {
            array_push($final_forms, $form);
        }
    }
    return $final_forms;
}

add_filter('gform_form_list_count', 'udoo_change_list_count', 10,1);
function udoo_change_list_count($form_count)
{

    global $current_user;
    $db_helper = new DatabaseHelper();
    $all_form = $db_helper->get_by_user($current_user->ID);
    $form_count['total'] = count($all_form);
    return $form_count;
}


add_action('gform_after_save_form', 'udoo_after_save_form', 10, 2);

function udoo_after_save_form($form, $is_new)
{
    error_log(__METHOD__);
    $db_helper = new DatabaseHelper();
    global $current_user;
    if ($is_new) {
        $db_helper->insert($form['id'], $current_user->ID);
    } else {
        $db_helper->update($form['id'], $current_user->ID);
    }
}

add_filter('gform_form_list_columns', 'udoo_change_columns', 10, 1);
function udoo_change_columns($columns)
{
    $columns['author'] = __('Author', 'survey-controller');
    return $columns;
}


/**
 * Create new table in database to map form with user ID for capability
 * @author Tinh Phan <tinhpt.38@gmail.com>
 */

register_activation_hook(__FILE__, 'udoo_create_db');
function udoo_create_db()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'udoo_gf_map_author';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
      id mediumint(9) NOT NULL AUTO_INCREMENT,
      form_id mediumint(9) NOT NULL,
      user_id mediumint(9) NOT NULL,
      PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
