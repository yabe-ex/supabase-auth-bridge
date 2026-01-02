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
 * Text Domain: supabase-auth-bridge
 * Domain Path: /languages
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
define('SUPABASE_AUTH_BRIDGE_DEVELOP', false);

class SupabaseAuthBridge {
    public function init() {
        // 管理画面側の処理
        require_once SUPABASE_AUTH_BRIDGE_PATH . '/inc/class-admin.php';
        $admin = new SupabaseAuthBridgeAdmin();

        add_action('admin_menu', array($admin, 'create_menu'));
        add_action('admin_init', array($admin, 'register_settings'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($admin, 'plugin_action_links'));
        add_action('admin_enqueue_scripts', array($admin, 'admin_enqueue'));

        // フロントエンドの処理
        require_once SUPABASE_AUTH_BRIDGE_PATH . '/inc/class-front.php';
        $front = new SupabaseAuthBridgeFront();

        add_action('wp_enqueue_scripts', array($front, 'front_enqueue'));

        // ログイン画面（wp-login.php）でもJSを読み込む
        add_action('login_enqueue_scripts', array($front, 'front_enqueue'));

        // Cron: Keep Alive処理
        add_action('sab_cron_keep_alive', array($admin, 'execute_keep_alive'));
    }
}

$instance = new SupabaseAuthBridge();
$instance->init();

// --- Cronスケジュールの登録・解除 ---

// プラグイン有効化時にスケジュール登録
register_activation_hook(__FILE__, 'sab_activate_cron');
function sab_activate_cron() {
    if (!wp_next_scheduled('sab_cron_keep_alive')) {
        wp_schedule_event(time(), 'daily', 'sab_cron_keep_alive');
    }
}

// プラグイン無効化時にスケジュール解除
register_deactivation_hook(__FILE__, 'sab_deactivate_cron');
function sab_deactivate_cron() {
    $timestamp = wp_next_scheduled('sab_cron_keep_alive');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'sab_cron_keep_alive');
    }
}
