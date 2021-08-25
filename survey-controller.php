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


//region -- GRAVITY HANDLER

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

add_filter('gform_form_list_count', 'udoo_change_list_count', 10, 1);
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


//before -- gform_shortcode_builder_forms

add_filter('gform_block_form_forms', function ($forms) {
    if (current_user_can('administrator')) {
        return $forms;
    }
    $db_helper = new DatabaseHelper();
    global $current_user;
    foreach ($forms as $key => $form) {
        $map_author = $db_helper->get($form['id'])[0];
        if (strval($map_author->user_id) != strval($current_user->ID)) {
            unset($forms[$key]);
        }
    }
    return array_values($forms);
});


//endregion

//region -- CUSTOM POST TYPE

function udoo_compile_post_type_capabilities($singular = 'post', $plural = 'posts')
{
    return [
        'read_post' => "read_$singular",
        'edit_post' => "edit_$singular",
        'delete_post' => "delete_$singular",
        'publish_post' => "publish_$singular",

        'read' => "read_$singular",
        'read_private_posts' => "read_private_$plural",

        'edit_posts' => "edit_$plural",
        'edit_others_posts' => "edit_others_$plural",
        'edit_private_posts' => "edit_private_$plural",
        'edit_published_posts' => "edit_published_$plural",

        'delete_posts' => "delete_$plural",
        'delete_private_posts' => "delete_private_$plural",
        'delete_published_posts' => "delete_published_$plural",
        'delete_others_posts' => "delete_others_$plural",
        'create_posts' => "edit_$plural",
        'publish_posts' => "publish_$plural",
    ];
}

function udoo_register_survey_share_post()
{
    $label = array(
        'name' => __('Take Survey', 'survey-controller'),
        'singular_name' => __('Take Survey', 'survey-controller'),
        'add_new_item' => __('New Take Survey', 'survey-controller'),
        'edit_item' => __('Edit Take Survey', 'survey-controller'),
        'view_item' => __('View Take Survey', 'survey-controller'),
        'view_items' => __('View Take Surveys', 'survey-controller'),
        'search_items' => __('Search survey', 'survey-controller'),
        'not_found' => __('No Take Survey found', 'survey-controller'),
        'not_found_in_trash' => __('No Take Survey found in trash', 'survey-controller'),
        'all_items' => __('All Take Survey', 'survey-controller'),
    );

    $args = array(
        'labels' => $label,
        'description' => __('Embber Gravity Form in post'),
        'supports' => array(
            'title',
            'editor',
            'author',
            'comments',
            'thumbnail',
        ),
        'menu_icon' => 'dashicons-welcome-write-blog',
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_nav_menus' => true,
        'show_in_admin_bar' => true,
        'menu_position' => 5,
        'can_export' => true,
        'map_meta_cap' => true,
        'show_in_rest' => true, // Required for Gutenberg
        'hierarchical' => false,
        //        'publicly_queryable' => false,
        'query_var' => true,
        'rewrite' => array('slug' => 'tsurvey'),
        'has_archive' => true,
        'capability_type' => 'post',
        'capabilities' => udoo_compile_post_type_capabilities('tsurvey', 'tsurveys')
    );

    register_post_type('tsurvey', $args);
}

add_action('init', 'udoo_register_survey_share_post');

function udoo_exclude_another_author_posts($query)
{
    if (current_user_can('administrator')) {
        return $query;
    }
    if ($query->query['post_type'] !== 'tsurvey') {
        return $query;
    }

    global $current_user;
    $query->set('author', $current_user->ID);
    return $query;
}

add_action('pre_get_posts', 'udoo_exclude_another_author_posts');


function udoo_register_meta_boxes()
{
    add_meta_box('invite-friends', __('Invite Friends', 'survey-controller'), 'udoo_render_invite_friend', 'tsurvey');
}

function get_option_users()
{
    $user = get_users();
    $uid = array_column($user, 'ID');
    $un = array_column($user, 'display_name');
    return array_combine($uid, $un);
}

function find_friend($value, &$users)
{
    if (is_numeric($value)) {
        $username = $users[$value];
        unset($users[$value]);
        return array($value => $username);
    }
    return array($value => $value);
}

function udoo_render_invite_friend()
{
    $users = get_option_users();
    global $post;
    $encrypted = get_post_meta($post->ID, '__invite_friends__', true);
    $options = json_decode($encrypted);
    if (!empty($options)) {
        $friends = array();
        if (isset($options)) {
            foreach ($options as $key) {
                array_push($friends, find_friend($key, $users));
            }
        }
        $invite = [];
        $final = [];
        foreach ($friends as $user) {
            $id = key($user);
            $invite[$id] = $user[$id];
            array_push($invite, $id);
            $final[$id] = $user[$id];
        }

        foreach ($users as $id => $user) {
            $final[$id] = $user;
        }
    }


    ?>
    <div>
        <select name="udoo_invite_friends[]" class="form-control" multiple="multiple">
            <?php if (empty($options)) : ?>
                <?php foreach ($users as $id => $name) : ?>
                    <option value="<?php echo $id ?>"><?php echo $name ?></option>
                <?php endforeach; ?>
            <?php else : ?>
                <?php foreach ($final as $id => $name) : ?>
                    <option <?php echo in_array($id, $invite) ? 'selected' : '' ?>
                            value="<?php echo $id ?>"><?php echo $name ?></option>
                <?php endforeach; ?>
            <?php endif; ?>

        </select>
    </div>
    <span> <?php echo __('Type user name or email to invite this post', 'survey-controller') ?></span>
    <?php
}

add_action('add_meta_boxes', 'udoo_register_meta_boxes');

function is_phone($phone){
    return preg_match('/^[0-9]{10}+$/', $phone);
}

function validate_email($email){
    return (!preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", $email)) ? false : true;
}

function udoo_handle_invite_share_survey($post_id)
{
    error_log(__METHOD__);
    $friends = $_POST['udoo_invite_friends'];
    udoo_save_share_survey($friends, $post_id);
    $email_list = [];
    $phone_list = [];

    foreach ($friends as $friend){
      if(validate_email($friend)){
            array_push($email_list,$friend);
        }else if(is_phone($friend)){
            array_push($phone_list,$friend);
        }else if(is_numeric($friend)){
          $user = get_users(array('ID' => $friend))[0];
          array_push($email_list,$user->user_email);
      }
    }
    error_log(print_r($email_list,true));
    error_log(print_r($phone_list,true));
}

function udoo_save_share_survey($friends, $post_id){
    $encrypted = json_encode($friends);
    if (get_post_meta($post_id, '__invite_friends__') !== null) {
        update_post_meta($post_id, '__invite_friends__', $encrypted);
    } else {
        add_post_meta($post_id, '__invite_friends__', $encrypted);
    }
}

add_action("save_post_tsurvey", 'udoo_handle_invite_share_survey', 10, 1);


//region -- PLUGIN HANDLE

function udoo_equenue_js()
{
    wp_enqueue_script('invite_friend_meta_box', plugin_dir_url(__FILE__) . '/admin/js/invite_friend_metabox.js', array(), '1.0');
    wp_enqueue_script('udoo_select2', plugin_dir_url(__FILE__) . '/admin/js/select2.js', array(), '4.1.0');
    wp_enqueue_style('udoo_select2_css', plugin_dir_url(__FILE__) . '/admin/css/select2.css', false, '1.0.0');
    wp_enqueue_style('udoo_invite_friend_css', plugin_dir_url(__FILE__) . '/admin/css/styles.css', false, '1.0.0');
}

add_action('admin_enqueue_scripts', 'udoo_equenue_js');


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

//endregion