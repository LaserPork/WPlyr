<?php
/**
 * Plugin Name: WPlyr - Better Video Player
 * Description: This plugin implements Plyr for extending WordPress video playback functionality
 * Version: 0.9
 * Author: Jakub Váverka
 * Author URI: https://github.com/LaserPork
 **/

 /**
  * CHANGE THESE VALUES BEFORE FIRST USAGE
  */
if(!defined('wplyr_video_path')){
    define('wplyr_video_path',$_SERVER['DOCUMENT_ROOT'] . "/");
    define('wplyr_video_url',"https://localhost/");
}

if (!defined('ABSPATH')){
    define('ABSPATH', dirname(__FILE__) . '/');
}


include("video_editor/phpFileTree/php_file_tree.php");    
include("wplyr-options-menu.php");
include("wplyr-video-editor.php");


/**
 * This function checks if Wordpress uses new editor. Returns true of it does
 */
function is_gutenberg_active()
{
    return false;
    $gutenberg    = false;
    $block_editor = false;

    if (has_filter('replace_editor', 'gutenberg_init')) {
        // Gutenberg is installed and activated.
        $gutenberg = true;
    }

    if (version_compare($GLOBALS['wp_version'], '5.0-beta', '>')) {
        // Block editor.
        $block_editor = true;
    }

    if (!$gutenberg && !$block_editor) {
        return false;
    }

    include_once ABSPATH . 'wp-admin/includes/plugin.php';

    if (!is_plugin_active('classic-editor/classic-editor.php')) {
        return true;
    }

    $use_block_editor = (get_option('classic-editor-replace') === 'no-replace');

    return $use_block_editor;
}

/**
 *  This function registers all necessery scirpts and styles
 */
function wp_wplyr_video_custom_script_load()
{
    wp_enqueue_script('player-min', plugin_dir_url(__FILE__) . '/player/dist/player.min.js');
    wp_enqueue_script('plyr-polyfilled-min', plugin_dir_url(__FILE__) . '/plyr-3.6.2-dist/plyr.polyfilled.min.js');
    wp_enqueue_style('wplyr-bootstrap-stylesheet', plugin_dir_url(__FILE__) . '/bootstrap-4.3.1-dist/css/bootstrap.min.css');
    wp_enqueue_style('plyr-stylesheet', plugin_dir_url(__FILE__) . '/plyr-3.6.2-dist/plyr.css',array('wplyr-bootstrap-stylesheet'));
    wp_enqueue_style('player-stylesheet', plugin_dir_url(__FILE__) . '/player/dist/player.css',array('plyr-stylesheet'));
}


/**
 * This function registers new block for Gutenberg editor
 * It is currently not used
 */
function wp_wplyr_video_gutenberg_register_block()
{
    if (is_gutenberg_active()) {

  
        wp_register_script(
            'wp_wplyr_video_element',
            plugins_url('wplyr-video-element.js', __FILE__),
            array('wp-element')
        );

        wp_register_script(
            'wp_wplyr_video_gutenberg_block',
            plugins_url('wplyr-gutenberg-block.js', __FILE__),
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp_wplyr_video_element')
        );

        register_block_type('wplyr-better-video/wplyr-video-block', array(
            'script' => 'wp_wplyr_video_element',
            'editor_script' => 'wp_wplyr_video_gutenberg_block',
        ));

        register_meta('post', 'wp_wplyr_meta_block_field', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
        ));
    }
}

/**
 * This function creates new post type that is used for saving videos
 */
function wp_wplyr_register_content_type()
{
    //Labels for post type
    $labels = array(
        'name'               => 'WPlyr Video Manager',
        'singular_name'      => 'Video',
        'menu_name'          => 'Videos',
        'name_admin_bar'     => 'Video',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New Video',
        'new_item'           => 'New Video',
        'edit_item'          => 'Edit Video',
        'view_item'          => 'View Video',
        'all_items'          => 'All Videos',
        'search_items'       => 'Search Videos',
        'parent_item_colon'  => 'Parent Video:',
        'not_found'          => 'No Videos found.',
        'not_found_in_trash' => 'No Videos found in Trash.',
    );
    //arguments for post type
    $args = array(
        'labels'            => $labels,
        'public'            => false,
        'publicly_queryable' => false,
        'show_ui'           => true,
        'show_in_nav'       => true,
        'query_var'         => true,
        'hierarchical'      => false,
        'supports'          => array('title'),
        'has_archive'       => true,
        'menu_position'     => 20,
        'show_in_admin_bar' => true,
        'menu_icon'         => 'dashicons-video-alt',
        'rewrite'            => array('slug' => 'videos', 'with_front' => 'true')
    );
    //register post type
    register_post_type('wp_wplyr_videos', $args);
}

/**
 * This function allowes the Gutenberg block to access all saved videos over open REST endpoint
 * It is currently not used
 */
function  videos_rest_endpoint($request_data)
{
    $args = array(
        'post_type' => 'wp_wplyr_videos',
        'posts_per_page' => -1,
        'numberposts' => -1
    );
    $posts = get_posts($args);
    foreach ($posts as $key => $post) {
        $posts[$key]->acf = get_post_meta($post->ID);
    }
    return  $posts;
}


/**
 * This function echoes JavaScript functions used for loading data for video player into frontend context
 * It could be seperated into external file, but it will be soon removed after REST endpoint full implementation
 */
function wp_wplyr_video_setup()
{

    ?>
    <script>
        function wp_wplyr_video_setup() {

        }

        function wp_wplyr_add_source(id, source, type) {
            if (typeof window.videoSourceMap === 'undefined') {
                window.videoSourceMap = {};
            }
            if (!(id in window.videoSourceMap)) {
                window.videoSourceMap[id] = [];
            }
            window.videoSourceMap[id].push({
                source: source,
                type: type
            });
        }
    </script>
<?php
}

/**
 * This function echoes the videoplayer in the place of a saved shortcode
 */
function wp_wplyr_video_shortcode($id)
{
    extract(shortcode_atts(array(
        'id' => 'id'
    ), $id));
   
    $html = '<div class="wplyr_container">
                 <video controls crossorigin playsinline class="wplyr_player wplyr_video_' . $id . '">
                 </video>
                </div>
                <script>';
    for ($i = 1; $i < sizeof(unserialize(get_post_meta($id)["_wp_wplyr_video_source"][0])); $i++) {
        $path = unserialize(get_post_meta($id)["_wp_wplyr_video_source"][0])[$i];
        if (empty($path)) {
            $source = '';
        } else {
            $source = wplyr_video_url. $path;
        }
        $type = unserialize(get_post_meta($id)["_wp_wplyr_video_type"][0])[$i];
        $html .= 'wp_wplyr_add_source(' . $id . ',"' . $source . '","' . $type . '");';
    }
    $html .= '</script>';
    return $html;
}

/**
 * after function declarations this file also either registers Gutenberg block or the shortcode fallback
 */

//add_filter( 'script_loader_tag', 'wp_wplyr_modify_jsx_tag', 10, 3 );
add_action('init', 'wp_wplyr_register_content_type');
if (is_gutenberg_active()) {
    add_action('init', 'wp_wplyr_video_gutenberg_register_block');
} else {
    add_shortcode("wplyr", "wp_wplyr_video_shortcode");
}

add_action('wp_enqueue_scripts', 'wp_wplyr_video_setup');
add_action('wp_enqueue_scripts', 'wp_wplyr_video_custom_script_load', 11);

/**
 * REST endpoint declaration
 * Can be used for fetching all created videos
 */
add_action('rest_api_init', function () {
    register_rest_route('wplyr', '/videos/', array(
        'methods' => 'GET',
        'callback' => 'videos_rest_endpoint'
    ));
});
