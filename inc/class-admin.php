<?php

class SupabaseAuthBridgeAdmin {
    private $option_group = 'supabase_auth_bridge_options';

    function create_menu() {
        add_submenu_page(
            'options-general.php',
            SUPABASE_AUTH_BRIDGE_NAME,
            SUPABASE_AUTH_BRIDGE_NAME,
            'manage_options',
            'supabase-auth-bridge',
            array($this, 'show_setting_page'),
            1
        );
        add_action('admin_init', array($this, 'register_settings'));
    }

    function register_settings() {
        // --- 1. API設定 ---
        register_setting($this->option_group, 'sab_supabase_url');
        register_setting($this->option_group, 'sab_supabase_anon_key');

        add_settings_section(
            'sab_general_section',
            'Supabase API接続設定',
            null,
            'supabase-auth-bridge'
        );

        add_settings_field('sab_supabase_url', 'Supabase Project URL', array($this, 'render_field_text'), 'supabase-auth-bridge', 'sab_general_section', array('name' => 'sab_supabase_url', 'placeholder' => 'https://xxx.supabase.co'));
        add_settings_field('sab_supabase_anon_key', 'Supabase Anon Key', array($this, 'render_field_password'), 'supabase-auth-bridge', 'sab_general_section', array('name' => 'sab_supabase_anon_key'));


        // --- 2. リダイレクト設定 (今回追加) ---
        register_setting($this->option_group, 'sab_redirect_after_login');
        register_setting($this->option_group, 'sab_redirect_after_logout');
        register_setting($this->option_group, 'sab_password_reset_url'); // ★これ重要

        add_settings_section(
            'sab_redirect_section',
            'リダイレクト設定 (URL)',
            array($this, 'redirect_section_desc'),
            'supabase-auth-bridge'
        );

        add_settings_field('sab_redirect_after_login', 'ログイン後の移動先', array($this, 'render_field_text'), 'supabase-auth-bridge', 'sab_redirect_section', array('name' => 'sab_redirect_after_login', 'placeholder' => '/my-page'));
        add_settings_field('sab_redirect_after_logout', 'ログアウト後の移動先', array($this, 'render_field_text'), 'supabase-auth-bridge', 'sab_redirect_section', array('name' => 'sab_redirect_after_logout', 'placeholder' => '/'));
        add_settings_field('sab_password_reset_url', 'パスワード再設定ページ', array($this, 'render_field_text'), 'supabase-auth-bridge', 'sab_redirect_section', array('name' => 'sab_password_reset_url', 'placeholder' => '/reset-password'));
    }

    function redirect_section_desc() {
        echo '<p>各操作の完了後に移動するページのURLパスを入力してください（例: <code>/mypage</code> または <code>http://...</code>）。<br>空欄の場合はトップページになります。</p>';
    }

    // 汎用テキスト入力フィールド描画
    function render_field_text($args) {
        $name = $args['name'];
        $placeholder = isset($args['placeholder']) ? $args['placeholder'] : '';
        $value = get_option($name);
        echo '<input type="text" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="regular-text" placeholder="' . esc_attr($placeholder) . '">';
    }

    // 汎用パスワード入力フィールド描画
    function render_field_password($args) {
        $name = $args['name'];
        $value = get_option($name);
        echo '<input type="password" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="regular-text">';
    }

    // CSS/JS読み込み
    function admin_enqueue($hook) {
        if (strpos($hook, SUPABASE_AUTH_BRIDGE_SLUG) === false) return;
        // (省略: 変更なし)
    }

    // 設定リンク追加
    function plugin_action_links($links) {
        $url = '<a href="' . esc_url(admin_url("/options-general.php?page=supabase-auth-bridge")) . '">設定</a>';
        array_unshift($links, $url);
        return $links;
    }

    // 設定ページ表示
    function show_setting_page() {
?>
        <div class="wrap">
            <h1>Supabase Auth Bridge 設定</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields($this->option_group);
                do_settings_sections('supabase-auth-bridge');
                submit_button();
                ?>
            </form>
        </div>
<?php
    }
}
