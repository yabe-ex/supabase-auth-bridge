<?php

/**
 * Plugin Name: Supabase Auth Bridge
 * Plugin URI:
 * Description: Replace the default WordPress login with Supabase Authentication. Supports Magic Links, Social Login (Google), and auto-syncs users to WordPress.
 * Version: 1.0.0
 * Author: Edel Hearts
 * Author URI:
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) exit();

$info = get_file_data(__FILE__, array('plugin_name' => 'Plugin Name', 'version' => 'Version'));

define('SUPABASE_AUTH_BRIDGE_URL', plugins_url('', __FILE__));
define('SUPABASE_AUTH_BRIDGE_PATH', dirname(__FILE__));
define('SUPABASE_AUTH_BRIDGE_NAME', $info['plugin_name']);
define('SUPABASE_AUTH_BRIDGE_SLUG', 'supabase-auth-bridge');
define('SUPABASE_AUTH_BRIDGE_PREFIX', 'supabase_auth_bridge_');
define('SUPABASE_AUTH_BRIDGE_VERSION', $info['version']);
define('SUPABASE_AUTH_BRIDGE_DEVELOP', true);

class SupabaseAuthBridge {
    public function init() {
        // 管理画面側の処理
        require_once SUPABASE_AUTH_BRIDGE_PATH . '/inc/class-admin.php';
        $admin = new SupabaseAuthBridgeAdmin();

        add_action('admin_menu', array($admin, 'create_menu'));
        add_action('admin_init', array($admin, 'register_settings')); // register_settingsをフックに追加
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($admin, 'plugin_action_links'));
        add_action('admin_enqueue_scripts', array($admin, 'admin_enqueue'));

        // フロントエンドの処理
        require_once SUPABASE_AUTH_BRIDGE_PATH . '/inc/class-front.php';
        $front = new SupabaseAuthBridgeFront();

        add_action('wp_enqueue_scripts', array($front, 'front_enqueue'));
    }
}

$instance = new SupabaseAuthBridge();
$instance->init();
