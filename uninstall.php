<?php

/**
 * Fired when the plugin is uninstalled.
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// グローバル変数を避けるため、無名関数で処理を囲む
(function () {
    $options = array(
        'sab_supabase_url',
        'sab_supabase_anon_key',
        'sab_supabase_service_role_key',
        'sab_main_color',
        'sab_auth_method_email',
        'sab_auth_method_google',
        'sab_auth_method_magiclink',
        'sab_redirect_after_login',
        'sab_redirect_after_logout',
        'sab_forgot_password_url',
        'sab_password_reset_url',
        'sab_enable_welcome_email',
        'sab_welcome_sender_name',
        'sab_welcome_sender_email',
        'sab_welcome_subject',
        'sab_welcome_body',
        'sab_enable_keep_alive'
    );

    foreach ($options as $option) {
        delete_option($option);
    }
})();
