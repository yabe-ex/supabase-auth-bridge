<?php

class SupabaseAuthBridgeAdmin {
    private $option_group = 'supabase_auth_bridge_options';

    public function __construct() {
        // ユーザー削除時のフックを登録
        add_action('delete_user', array($this, 'handle_delete_user'));
    }

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
    }

    function register_settings() {
        // --- 1. API設定 ---
        register_setting($this->option_group, 'sab_supabase_url');
        register_setting($this->option_group, 'sab_supabase_anon_key');
        register_setting($this->option_group, 'sab_supabase_service_role_key'); // Google判定＆削除用

        add_settings_section(
            'sab_general_section',
            'Supabase API接続設定',
            null,
            'supabase-auth-bridge'
        );

        add_settings_field('sab_supabase_url', 'Supabase Project URL', array($this, 'render_field_text'), 'supabase-auth-bridge', 'sab_general_section', array('name' => 'sab_supabase_url', 'placeholder' => 'https://xxx.supabase.co'));
        add_settings_field('sab_supabase_anon_key', 'Supabase Anon Key', array($this, 'render_field_password'), 'supabase-auth-bridge', 'sab_general_section', array('name' => 'sab_supabase_anon_key'));
        add_settings_field('sab_supabase_service_role_key', 'Supabase Service Role Key', array($this, 'render_field_password'), 'supabase-auth-bridge', 'sab_general_section', array('name' => 'sab_supabase_service_role_key', 'desc' => 'Googleログイン判定およびユーザー削除時に使用します（公開されません）。'));


        // --- 2. デザイン・機能設定 ---
        register_setting($this->option_group, 'sab_main_color');
        register_setting($this->option_group, 'sab_auth_method_email');
        register_setting($this->option_group, 'sab_auth_method_google');
        register_setting($this->option_group, 'sab_auth_method_magiclink');

        add_settings_section(
            'sab_design_section',
            'デザイン・機能設定',
            null,
            'supabase-auth-bridge'
        );

        add_settings_field('sab_main_color', 'メインカラー', array($this, 'render_field_color'), 'supabase-auth-bridge', 'sab_design_section', array('name' => 'sab_main_color', 'default' => '#0073aa'));
        add_settings_field('sab_auth_methods', '利用する認証方式', array($this, 'render_field_auth_methods'), 'supabase-auth-bridge', 'sab_design_section');


        // --- 3. リダイレクト設定 ---
        register_setting($this->option_group, 'sab_redirect_after_login');
        register_setting($this->option_group, 'sab_redirect_after_logout');
        register_setting($this->option_group, 'sab_forgot_password_url');
        register_setting($this->option_group, 'sab_password_reset_url');

        add_settings_section(
            'sab_redirect_section',
            'リダイレクト設定 (URL)',
            array($this, 'redirect_section_desc'),
            'supabase-auth-bridge'
        );

        add_settings_field('sab_redirect_after_login', 'ログイン後の移動先', array($this, 'render_field_text'), 'supabase-auth-bridge', 'sab_redirect_section', array('name' => 'sab_redirect_after_login', 'placeholder' => '/my-page'));
        add_settings_field('sab_redirect_after_logout', 'ログアウト後の移動先', array($this, 'render_field_text'), 'supabase-auth-bridge', 'sab_redirect_section', array('name' => 'sab_redirect_after_logout', 'placeholder' => '/'));

        add_settings_field('sab_forgot_password_url', 'パスワード申請ページ (Forgot PW)', array($this, 'render_field_text'), 'supabase-auth-bridge', 'sab_redirect_section', array('name' => 'sab_forgot_password_url', 'placeholder' => '/forgot-password', 'desc' => 'ログイン画面の「パスワードをお忘れですか？」のリンク先'));
        add_settings_field('sab_password_reset_url', 'パスワード再設定ページ (New PW)', array($this, 'render_field_text'), 'supabase-auth-bridge', 'sab_redirect_section', array('name' => 'sab_password_reset_url', 'placeholder' => '/reset-password', 'desc' => 'メール内のリンクをクリックした後の移動先'));
    }

    function redirect_section_desc() {
        echo '<p>各操作の完了後に移動するページのURLパスを入力してください。</p>';
    }

    // --- ユーザー削除時の同期処理 (New!) ---
    function handle_delete_user($user_id) {
        // 1. SupabaseのUUIDを取得（メタデータに保存されているはず）
        $supabase_uuid = get_user_meta($user_id, 'supabase_uuid', true);

        // UUIDがない場合（通常のWP管理ユーザーなど）は何もしない
        if (!$supabase_uuid) {
            return;
        }

        // 2. 必要なAPI情報を取得
        $url = get_option('sab_supabase_url');
        $service_key = get_option('sab_supabase_service_role_key');

        if (!$url || !$service_key) {
            // キーがないと消せないのでログに残す等の処理推奨
            error_log('Supabase Auth Bridge: Service Role Key is missing. Could not delete user from Supabase.');
            return;
        }

        // 3. Supabase Admin APIを実行 (DELETE /auth/v1/admin/users/{id})
        $api_url = rtrim($url, '/') . '/auth/v1/admin/users/' . $supabase_uuid;

        $response = wp_remote_request($api_url, array(
            'method' => 'DELETE',
            'headers' => array(
                'apikey' => $service_key,
                'Authorization' => 'Bearer ' . $service_key,
                'Content-Type' => 'application/json'
            )
        ));

        // 4. 結果確認（エラーログ出力）
        if (is_wp_error($response)) {
            error_log('Supabase Auth Bridge: Error deleting user - ' . $response->get_error_message());
        } else {
            $code = wp_remote_retrieve_response_code($response);
            if ($code >= 400) {
                $body = wp_remote_retrieve_body($response);
                error_log("Supabase Auth Bridge: Failed to delete user (Status: $code) - $body");
            }
        }
    }

    // テキストフィールド
    function render_field_text($args) {
        $name = $args['name'];
        $placeholder = isset($args['placeholder']) ? $args['placeholder'] : '';
        $value = get_option($name);
        echo '<input type="text" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="regular-text" placeholder="' . esc_attr($placeholder) . '">';
        if (isset($args['desc'])) echo '<p class="description">' . esc_html($args['desc']) . '</p>';
    }

    // パスワードフィールド
    function render_field_password($args) {
        $name = $args['name'];
        $value = get_option($name);
        echo '<input type="password" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="regular-text">';
        if (isset($args['desc'])) echo '<p class="description">' . esc_html($args['desc']) . '</p>';
    }

    // カラーピッカー
    function render_field_color($args) {
        $name = $args['name'];
        $default = isset($args['default']) ? $args['default'] : '#0073aa';
        $value = get_option($name, $default);
        echo '<input type="text" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="sab-color-field" data-default-color="' . esc_attr($default) . '">';
    }

    // 認証方式チェックボックス
    function render_field_auth_methods() {
        $email = get_option('sab_auth_method_email');
        $google = get_option('sab_auth_method_google');
        $magic = get_option('sab_auth_method_magiclink');
?>
        <fieldset>
            <label><input type="checkbox" name="sab_auth_method_email" value="1" <?php checked($email, 1); ?>> メールアドレス ＋ パスワード</label><br>
            <label><input type="checkbox" name="sab_auth_method_google" value="1" <?php checked($google, 1); ?>> Google認証 (OAuth)</label><br>
            <label><input type="checkbox" name="sab_auth_method_magiclink" value="1" <?php checked($magic, 1); ?>> メール認証コード / マジックリンク (Passwordless)</label>
        </fieldset>
    <?php
    }

    // CSS/JS読み込み
    function admin_enqueue($hook) {
        if (strpos($hook, 'supabase-auth-bridge') === false) return;

        // カラーピッカーの読み込み
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script(SUPABASE_AUTH_BRIDGE_SLUG . '-admin', SUPABASE_AUTH_BRIDGE_URL . '/js/admin.js', array('jquery', 'wp-color-picker'), SUPABASE_AUTH_BRIDGE_VERSION, true);
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
