<?php

/*
Plugin Name: WPU UX Tweaks
Description: Adds UX enhancement & tweaks to WordPress
Version: 1.6.0
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

if (!defined('ABSPATH')) {
    exit();
}

/* ----------------------------------------------------------
  Clean head
---------------------------------------------------------- */

add_action('init', 'wpuux_clean_head');

function wpuux_clean_head() {
    global $wp_widget_factory;

    // Hardcoded recent comments style
    if (isset($wp_widget_factory->widgets['WP_Widget_Recent_Comments'])) {
        remove_action('wp_head', array(
            $wp_widget_factory->widgets['WP_Widget_Recent_Comments'],
            'recent_comments_style'
        ));
    }

    // Meta generator
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'wp_shortlink_wp_head');
}

/* ----------------------------------------------------------
  CONTENT TWEAKS
---------------------------------------------------------- */

/* Prevent bad formed links
 -------------------------- */

add_action('the_content', 'wpuux_bad_formed_links');

function wpuux_bad_formed_links($content) {
    if (apply_filters('disable__wpuux_bad_formed_links', false)) {
        return $content;
    }
    $badform = array();
    $goodform = array();

    $badform[] = 'href="www.';
    $goodform[] = 'href="http://www.';

    $badform[] = 'href="http//';
    $goodform[] = 'href="http://';

    $badform[] = 'href="https//';
    $goodform[] = 'href="https://';

    $badform[] = 'href=" http://';
    $goodform[] = 'href="http://';

    $badform[] = 'href=" https://';
    $goodform[] = 'href="https://';

    $content = str_replace($badform, $goodform, $content);
    return $content;
}

/* Clean up text from PDF
 -------------------------- */

add_filter('the_content', 'wpuux_cleanup_pdf_text');
add_filter('the_excerpt', 'wpuux_cleanup_pdf_text');

function wpuux_cleanup_pdf_text($co) {
    $letters = array(
        'a',
        'A',
        'c',
        'C',
        'e',
        'E',
        'i',
        'I',
        'o',
        'O',
        'u',
        'U'
    );
    foreach ($letters as $letter) {
        $co = str_replace($letter . '̀', '&' . $letter . 'grave;', $co);
        $co = str_replace($letter . '́', '&' . $letter . 'acute;', $co);
        $co = str_replace($letter . '̂', '&' . $letter . 'circ;', $co);
        $co = str_replace($letter . '̈', '&' . $letter . 'uml;', $co);
        $co = str_replace($letter . '¸', '&' . $letter . 'cedil;', $co);
    }
    return $co;
}

/* Specials smileys
 -------------------------- */

add_filter('the_title', 'wpuux_special_smileys');
add_filter('the_content', 'wpuux_special_smileys');
add_filter('the_excerpt', 'wpuux_special_smileys');

function wpuux_special_smileys($content) {
    $content = str_replace(' <3', ' &hearts;', $content);
    $content = str_replace('<3 ', '&hearts; ', $content);
    return $content;
}

/* Add content classes
 -------------------------- */

add_filter('the_content', 'wpuux_content_classes', 99, 1);
function wpuux_content_classes($content) {

    /* Add a class to P tags containing only a A>IMG */
    $content = preg_replace('/<p>([\s]?)<a([^>]*)>([\s]?)<img([^>]*)>([\s]?)<\/a>([\s]?)<\/p>/isU', "<p class=\"only-child-link \">$1<a$2><img$4></a></p>", $content);

    return $content;
}

/* ----------------------------------------------------------
  Prevent invalid characters in file name
---------------------------------------------------------- */

add_filter('sanitize_file_name', 'remove_accents');
add_filter('sanitize_file_name', 'wpuux_uxt_clean_filename');

function wpuux_uxt_clean_filename($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9-_\.]+/', '', $string);
    return $string;
}

/* ----------------------------------------------------------
  Add copyright to content in RSS feed
---------------------------------------------------------- */

// src : http://www.catswhocode.com/blog/useful-snippets-to-protect-your-wordpress-blog-against-scrapers

add_filter('the_excerpt_rss', 'wpuux_add_copyright_feed');
add_filter('the_content', 'wpuux_add_copyright_feed');

function wpuux_add_copyright_feed($content) {
    if (apply_filters('disable__wpuux_add_copyright_feed', false)) {
        return $content;
    }
    if (is_feed()) {
        $content .= '<hr /><p>&copy; ' . date('Y') . ' ' . get_bloginfo('name') . ' - <a href="' . get_permalink() . '">' . get_the_title() . '</a></p>';
    }
    return $content;
}

/* ----------------------------------------------------------
  Redirect to the only search result.
---------------------------------------------------------- */

add_action('template_redirect', 'wpuux_redirect_only_result_search');

function wpuux_redirect_only_result_search() {
    if (apply_filters('disable__wpuux_redirect_only_result_search', false)) {
        return;
    }
    if (is_search()) {
        global $wp_query;
        if ($wp_query->post_count == 1) {
            wp_redirect(get_permalink($wp_query->post));
        }
    }
}

/* ----------------------------------------------------------
  Avoid 404 on pagination
---------------------------------------------------------- */

add_action('template_redirect', 'wpuux_redirect_avoid_404_pagination');
function wpuux_redirect_avoid_404_pagination() {
    global $wp_rewrite;

    // Get page number if available
    $paged = get_query_var('paged');

    // If page 404 & page number > 0
    if (is_404() && $paged > 0) {
        // Redirect to first page
        wp_redirect(preg_replace("#/$wp_rewrite->pagination_base/$paged(/+)?$#", '', esc_html($_SERVER['REQUEST_URI'])), 301);
        die();
    }
}

/* ----------------------------------------------------------
  Prevent 404 when changed URL format from date to postname
---------------------------------------------------------- */

add_action('template_redirect', 'wpuux_redirect_avoid_404_change_url');
function wpuux_redirect_avoid_404_change_url() {
    if (!is_404()) {
        return;
    }

    if (get_option('permalink_structure') != '/%postname%/') {
        return;
    }

    $_url = $_SERVER['REQUEST_URI'];
    $_old_format = '/\/20([0-9]{2})\/([0-9]{2})\/([^\/]*)/';

    if (!preg_match($_old_format, $_url, $_match) || !isset($_match[3]) || empty($_match[3])) {
        return;
    }

    $_post_test = get_posts(array(
        'name' => $_match[3]
    ));

    if (!empty($_post_test)) {
        wp_redirect(get_permalink($_post_test[0]->ID));
        die;
    }
}

/* ----------------------------------------------------------
  Configure mail from & name
---------------------------------------------------------- */

add_filter('wp_mail_from', 'wpuux_new_mail_from');
function wpuux_new_mail_from($email) {
    $new_email = get_option('wpuux_opt_email');
    if (!empty($new_email) && $new_email !== false) {
        $email = $new_email;
    }

    return $email;
}

add_filter('wp_mail_from_name', 'wpuux_new_mail_from_name');
function wpuux_new_mail_from_name($name) {
    $new_email_name = get_option('wpuux_opt_email_name');
    if (!empty($new_email_name) && $new_email_name !== false) {
        $name = $new_email_name;
    }
    return $name;
}

/* ----------------------------------------------------------
  Prevent heavy 404 pages for static files
---------------------------------------------------------- */

/* From http://www.binarymoon.co.uk/2011/04/optimizing-wordpress-404s/ */

add_filter('template_redirect', 'wpuux_preventheavy404');
function wpuux_preventheavy404() {
    if (!is_404()) {
        return;
    }
    $fileExtension = '';
    $badFileTypes = apply_filters('wpuux_preventheavy404_filestype', array(
        'bmp',
        'css',
        'doc',
        'gif',
        'ico',
        'jpg',
        'js',
        'png',
        'rar',
        'tar',
        'log',
        'txt',
        'xml',
        'zip'
    ));
    if (!empty($_SERVER['REQUEST_URI'])) {
        $fileExtensionParts = strtolower(pathinfo($_SERVER['REQUEST_URI'], PATHINFO_EXTENSION));
        $fileExtensionParts = explode('?', $fileExtensionParts);
        if (isset($fileExtensionParts[0])) {
            $fileExtension = $fileExtensionParts[0];
        }
    }
    if (in_array($fileExtension, $badFileTypes)) {
        do_action('wpuux_preventheavy404_before_headers', $fileExtension);
        status_header(404);
        exit('error');
    }
}

/* ----------------------------------------------------------
  Check "Remember me" by default on login
---------------------------------------------------------- */

add_action('login_form', 'wpuux_check_rememberme');
function wpuux_check_rememberme() {
    global $rememberme;
    $rememberme = 1;
}

/* ----------------------------------------------------------
  Images
---------------------------------------------------------- */

/* Clean default image title
 -------------------------- */

add_action('add_attachment', 'wpuux_clean_default_image_title');
function wpuux_clean_default_image_title($post_ID) {
    $post = get_post($post_ID);
    $post->post_title = str_replace(array(
        '-',
        '_'
    ), ' ', $post->post_title);
    $post->post_title = ucwords($post->post_title);
    wp_update_post(array(
        'ID' => $post_ID,
        'post_title' => $post->post_title
    ));
    return $post_ID;
}

/* Set default image link to none
 -------------------------- */

add_action('init', 'wpuux_default_link_type');
function wpuux_default_link_type() {
    $image_default_link_type = get_option('image_default_link_type');
    if ($image_default_link_type != 'none') {
        update_option('image_default_link_type', 'none');
    }
}

/* Thumbnails for post columns
 -------------------------- */

add_filter('manage_posts_columns', 'wpuux_add_column_thumb', 5);
function wpuux_add_column_thumb($defaults) {
    global $post;
    if (apply_filters('disable__wpuux_thumbnail_post_column', false)) {
        return $defaults;
    }
    /* Disable for woocommerce posts */
    if (is_object($post) && function_exists('woocommerce_content') && $post->post_type == 'product') {
        return $defaults;
    }
    $defaults['wpuux_column_thumb'] = __('Thumbnail');
    return $defaults;
}

add_action('manage_posts_custom_column', 'wpuux_add_column_thumb_content', 5, 2);
function wpuux_add_column_thumb_content($column_name, $id) {
    global $post;
    if (apply_filters('disable__wpuux_thumbnail_post_column', false)) {
        return;
    }
    if ($column_name === 'wpuux_column_thumb' && isset($post->ID)) {
        $thumb_id = get_post_thumbnail_id($post->ID);
        if (!$thumb_id) {
            return;
        }
        $image = wp_get_attachment_image_src($thumb_id, 'thumbnail');
        if (isset($image[0])) {
            $w = apply_filters('wpuux_thumbnail_post_column_thumbnail_width', 70);
            echo '<img style="height:' . $w . 'px;width:' . $w . 'px;" src="' . $image[0] . '" alt="" />';
        }
    }
}

/* Set media select to uploaded
 -------------------------- */

/* Thx http://wordpress.stackexchange.com/a/76213 */

class wpuux_set_media_select_uploaded_init {
    public function __construct() {
        add_action('init', array(&$this, 'init'));
    }

    public function init() {
        if (apply_filters('disable__wpuux_set_media_select_uploaded_init', true)) {
            return;
        }
        add_action('admin_footer-post-new.php', array(&$this, 'set_media_select'));
        add_action('admin_footer-post.php', array(&$this, 'set_media_select'));
    }

    public function set_media_select() {
        echo <<<EOT
<script>
jQuery(document).on("DOMNodeInserted", function(){
    var _filter = jQuery('select.attachment-filters');
    if(_filter.length < 1){
        return;
    }
    // Lock uploads to "Uploaded to this post"
    if(_filter.hasClass('default-value-uploaded')){
        return;
    }
    _filter.find('[value="uploaded"]').attr( 'selected', true );
    _filter.trigger('change');
    _filter.addClass('default-value-uploaded');
});
</script>
EOT;
    }
}

new wpuux_set_media_select_uploaded_init();

/* ----------------------------------------------------------
  Disable WP Emoji
---------------------------------------------------------- */

add_action('init', 'wpuux_disable_newemojis');
function wpuux_disable_newemojis() {
    if (apply_filters('disable__wpuux_disable_newemojis', false)) {
        return;
    }
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    add_filter('emoji_svg_url', '__return_empty_string');
}

/* ----------------------------------------------------------
  Disable WP Oembeds
---------------------------------------------------------- */

add_action('init', 'wpuux_disable_oembed');
function wpuux_disable_oembed() {
    if (apply_filters('disable__wpuux_disable_oembed', false)) {
        return;
    }
    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    remove_action('wp_head', 'wp_oembed_add_host_js');
}

/* ----------------------------------------------------------
  Fix admin
---------------------------------------------------------- */

add_action('admin_head', 'wpuux_fixchromebug_admin');
function wpuux_fixchromebug_admin() {
    echo '<style>#adminmenu{-webkit-transform:translateZ(0);transform:translateZ(0);}</style>';
}

/* ----------------------------------------------------------
  Editor can use menus
---------------------------------------------------------- */

add_action('init', 'wpuux_add_menus_editor');
function wpuux_add_menus_editor() {
    $roleObject = get_role('editor');
    if (is_object($roleObject) && !$roleObject->has_cap('edit_theme_options')) {
        $roleObject->add_cap('edit_theme_options');
    }
}

add_action('admin_head', 'wpuux_add_menus__hide_menu_full');
function wpuux_add_menus__hide_menu_full() {
    $user = wp_get_current_user();
    $roles = (array) $user->roles;
    if (in_array('editor', $roles)) {
        remove_submenu_page('themes.php', 'themes.php');
        remove_submenu_page('themes.php', 'widgets.php');
        remove_submenu_page('themes.php', 'custom-header');
        remove_submenu_page('themes.php', 'custom-background');
    }
}

add_action('admin_head', 'wpuux_add_menus__hide_menuhead_full');
function wpuux_add_menus__hide_menuhead_full() {
    global $submenu;
    $user = wp_get_current_user();
    $roles = (array) $user->roles;
    if (in_array('editor', $roles)) {
        unset($submenu['themes.php'][6]);
        unset($submenu['themes.php'][15]);
        unset($submenu['themes.php'][20]);
    }
}

/* ----------------------------------------------------------
  WP Login logo & URL
---------------------------------------------------------- */

class wpuux_login_logo {
    public function __construct() {
        add_action('init', array(&$this, 'init'));
    }

    public function init() {
        add_filter('get_site_icon_url', array(&$this, 'override_favicon_image'), 10, 3);
        add_filter('theme_mod_header_image', array(&$this, 'override_header_image'), 10, 1);
        if (has_header_image()) {
            add_action('login_enqueue_scripts', array(&$this, 'set_image'));
        }
        add_filter('login_headerurl', array(&$this, 'set_url'));
        add_filter('login_headertext', array(&$this, 'set_title'));
        add_filter('admin_head', array(&$this, 'set_icon_admin'));
    }

    public function override_favicon_image($url, $size, $blog_id) {
        if (defined('WPU_UX_TWEAKS_FAVICON_URL')) {
            return WPU_UX_TWEAKS_FAVICON_URL;
        }
        if (!$url) {
            $base_uri = get_stylesheet_directory_uri();
            $base_dir = get_stylesheet_directory();
            $images = array(
                '/favicon.ico',
                '/favicon.svg',
                '/favicon.png',
                '/favicon.jpg',
                '/assets/favicon.ico',
                '/assets/favicon.svg',
                '/assets/favicon.png',
                '/assets/favicon.jpg',
                '/assets/images/favicon.ico',
                '/assets/images/favicon.svg',
                '/assets/images/favicon.png',
                '/assets/images/favicon.jpg'
            );
            foreach ($images as $imagetest) {
                if (file_exists($base_dir . $imagetest)) {
                    return $base_uri . $imagetest;
                }
            }
        }

        return $url;
    }

    public function override_header_image($image) {
        if (defined('WPU_UX_TWEAKS_HEADER_IMAGE_URL')) {
            return WPU_UX_TWEAKS_HEADER_IMAGE_URL;
        }
        if (!$image) {
            $base_uri = get_stylesheet_directory_uri();
            $base_dir = get_stylesheet_directory();
            $images = array(
                '/assets/images/logo.svg',
                '/assets/images/logo.png',
                '/assets/images/logo.jpg',
                '/images/logo.svg',
                '/images/logo.png',
                '/images/logo.jpg'
            );
            foreach ($images as $imagetest) {
                if (file_exists($base_dir . $imagetest)) {
                    return $base_uri . $imagetest;
                }
            }
        }

        return $image;
    }

    public function set_image() {
        echo '<style type="text/css">#login h1 a,.login h1 a{margin:0 24px;width:auto;background-size:contain;background-image:url(' . get_header_image() . ')}</style>';
    }

    public function set_icon_admin() {
        $icon_url = get_site_icon_url(40);
        if (!$icon_url) {
            return;
        }
        echo '<style type="text/css">#wpadminbar #wp-admin-bar-wp-logo > .ab-item .ab-icon:before {color: transparent;background: #F0F0F0 url(' . $icon_url . ') no-repeat center center;background-size: cover;}</style>';
    }

    public function set_url() {
        return home_url();
    }

    public function set_title() {
        return get_bloginfo('name');
    }

}

new wpuux_login_logo();

/* ----------------------------------------------------------
  Avoid bugs in redirections
---------------------------------------------------------- */

add_filter('login_redirect', 'wpuux_login_redirect_fixes', 10, 1);

function wpuux_login_redirect_fixes($redirect_to, $requested_redirect_to = null, $user = null) {
    return str_replace(array(
        '/wp-cms'
    ), '', $redirect_to);
}

/* ----------------------------------------------------------
  Style for default WordPress emails
---------------------------------------------------------- */

function wpuux_mailcontenttype() {
    return "text/html";
}

add_action('email_change_email', 'wpuux_change_email', 30, 3);
add_action('password_change_email', 'wpuux_change_email', 30, 3);
function wpuux_change_email($email_details, $user, $userdata) {

    /* WooCommerce */
    if (class_exists('WooCommerce')) {
        add_filter('wp_mail_content_type', 'wpuux_mailcontenttype');
        $email_details['message'] = WC()->mailer()->wrap_message('', $email_details['message']);
    }

    return $email_details;
}

/* ----------------------------------------------------------
  Disable link on item in order when product is in draft
---------------------------------------------------------- */

add_filter('woocommerce_order_item_name', 'wpuux_woocommerce_order_item_name', 10, 3);
function wpuux_woocommerce_order_item_name($item_name, $item, $is_visible) {
    if (is_object($item)) {
        $product_id = $item->get_product_id();
        if (get_post_status($product_id) == 'draft') {
            return $item->get_name();
        }
    }
    return $item_name;
}

/* ----------------------------------------------------------
  Remove some default widgets
---------------------------------------------------------- */

add_action('wp_network_dashboard_setup', 'wpuux_remove_widgets', 20);
add_action('wp_user_dashboard_setup', 'wpuux_remove_widgets', 20);
add_action('wp_dashboard_setup', 'wpuux_remove_widgets', 20);
function wpuux_remove_widgets() {
    /* Remove events & WP news widget */
    remove_meta_box('dashboard_primary', get_current_screen(), 'side');
}

/* ----------------------------------------------------------
  Remove novalidate attribute on comments form
---------------------------------------------------------- */

add_action('wp_footer', 'wpuux_remove_novalidate_wp_footer');
function wpuux_remove_novalidate_wp_footer() {
    if (!is_singular() || !comments_open()) {
        return;
    }

    echo '<script type="text/javascript">';
    echo '(function(){';
    echo 'var $f = document.getElementById("commentform");';
    echo 'if($f){';
    echo '$f.removeAttribute("novalidate")';
    echo '}';
    echo '}())';
    echo '</script>';
}

/* ----------------------------------------------------------
  Store last user login
---------------------------------------------------------- */

add_action('wp_login', 'wpuux_user_last_login', 10, 2);
function wpuux_user_last_login($user_login, $user) {
    if (apply_filters('disable__wpuux__user_last_login', false)) {
        return;
    }
    if (!is_object($user) || !isset($user->ID)) {
        return;
    }
    update_user_meta($user->ID, 'last_login', date_i18n('Y-m-d H:i:s'));
}

/* Admin Widget
-------------------------- */

function wpuux_user_add_dashboard_get_users() {
    $cache_id = 'wpuux_user_add_dashboard_get_users';
    $cache_duration = 30;
    $top_users = wp_cache_get($cache_id);
    if ($top_users === false) {
        $top_users = get_users(array(
            'meta_key' => 'last_login',
            'number' => 10,
            'orderby' => 'meta_value',
            'order' => 'DESC'
        ));
        wp_cache_set($cache_id, $top_users, '', $cache_duration);
    }

    if (!is_array($top_users)) {
        return array();
    }

    if (count($top_users) < 2) {
        return array();
    }

    return $top_users;
}

add_action('wp_dashboard_setup', 'wpuux_user_add_dashboard_widgets');
function wpuux_user_add_dashboard_widgets() {
    if (!current_user_can('delete_users')) {
        return;
    }
    $top_users = wpuux_user_add_dashboard_get_users();
    if (empty($top_users)) {
        return;
    }
    wp_add_dashboard_widget(
        'wpuux_user_dashboard_widget',
        'Last Login',
        'wpuux_user_dashboard_widget__content'
    );
}

function wpuux_user_dashboard_widget__content() {
    $top_users = wpuux_user_add_dashboard_get_users();
    if (empty($top_users)) {
        return;
    }
    echo '<ul>';
    foreach ($top_users as $top_user) {
        $last_login = strtotime(get_user_meta($top_user->ID, 'last_login', 1));
        $format = get_option('date_format') . ' - ' . get_option('time_format');
        echo '<li>';
        echo '<strong><a href="' . admin_url('user-edit.php?user_id=' . $top_user->ID) . '">' . esc_html($top_user->display_name) . '</a></strong> : ' . date_i18n($format, $last_login);
        echo '</li>';
    }
    echo '</ul>';

}

/* ----------------------------------------------------------
  Disable Block Editor default FullScreen mode in WordPress 5.4
---------------------------------------------------------- */

/**
 * Thx to @audrasjb
 * Source : https://jeanbaptisteaudras.com/en/2020/03/disable-block-editor-default-fullscreen-mode-in-wordpress-5-4/
 */

add_action('enqueue_block_editor_assets', 'jba_disable_editor_fullscreen_by_default');
function jba_disable_editor_fullscreen_by_default() {
    $script = "jQuery( window ).load(function() { const isFullscreenMode = wp.data.select( 'core/edit-post' ).isFeatureActive( 'fullscreenMode' ); if ( isFullscreenMode ) { wp.data.dispatch( 'core/edit-post' ).toggleFeature( 'fullscreenMode' ); } });";
    wp_add_inline_script('wp-blocks', $script);
}

/* ----------------------------------------------------------
  User contact methods
---------------------------------------------------------- */

add_filter('user_contactmethods', 'wpuux_edit_contactmethods', 10, 1);
function wpuux_edit_contactmethods($contactmethods) {
    $contactmethods['facebook'] = 'Facebook';
    $contactmethods['instagram'] = 'Instagram';
    $contactmethods['pinterest'] = 'Pinterest';
    $contactmethods['twitter'] = 'Twitter';
    $contactmethods['youtube'] = 'Youtube';
    unset($contactmethods['yim']);
    unset($contactmethods['aim']);
    unset($contactmethods['jabber']);
    return $contactmethods;
}

/* ----------------------------------------------------------
  Lazy loading
---------------------------------------------------------- */

add_filter('post_thumbnail_html', 'wpuux_post_thumbnail_html_lazyloading', 10, 5);
function wpuux_post_thumbnail_html_lazyloading($html, $post_id, $post_thumbnail_id, $size, $attr) {
    if (!function_exists('wp_lazy_loading_enabled')) {
        $html = str_replace('<img', '<img loading="lazy"', $html);
    }
    return $html;
}

add_filter('wp_get_attachment_image_attributes', 'wpuux_wp_get_attachment_image_attributes_lazyloading', 10, 3);
function wpuux_wp_get_attachment_image_attributes_lazyloading($attr, $attachment, $size) {
    if (!function_exists('wp_lazy_loading_enabled')) {
        $attr['loading'] = 'lazy';
    }
    return $attr;
}
