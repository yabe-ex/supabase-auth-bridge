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
        register_setting($this->option_group, 'sab_supabase_url', array('sanitize_callback' => 'esc_url_raw'));
        register_setting($this->option_group, 'sab_supabase_anon_key', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($this->option_group, 'sab_supabase_service_role_key', array('sanitize_callback' => 'sanitize_text_field'));

        add_settings_section(
            'sab_general_section',
            __('Supabase API Connection Settings', 'supabase-auth-bridge'),
            null,
            'supabase-auth-bridge'
        );

        add_settings_field('sab_supabase_url', __('Supabase Project URL', 'supabase-auth-bridge'), array($this, 'render_field_text'), 'supabase-auth-bridge', 'sab_general_section', array('name' => 'sab_supabase_url', 'placeholder' => 'https://xxx.supabase.co'));
        add_settings_field('sab_supabase_anon_key', __('Supabase Anon Key', 'supabase-auth-bridge'), array($this, 'render_field_password'), 'supabase-auth-bridge', 'sab_general_section', array('name' => 'sab_supabase_anon_key'));
        add_settings_field('sab_supabase_service_role_key', __('Supabase Service Role Key', 'supabase-auth-bridge'), array($this, 'render_field_password'), 'supabase-auth-bridge', 'sab_general_section', array('name' => 'sab_supabase_service_role_key', 'desc' => __('Used for detecting Google Login users and deleting users from Supabase (Not exposed to frontend).', 'supabase-auth-bridge')));


        // --- 2. デザイン・機能設定 ---
        register_setting($this->option_group, 'sab_main_color', array('sanitize_callback' => 'sanitize_hex_color'));
        register_setting($this->option_group, 'sab_auth_method_email');
        register_setting($this->option_group, 'sab_auth_method_google');
        register_setting($this->option_group, 'sab_auth_method_magiclink');

        add_settings_section(
            'sab_design_section',
            __('Design & Function Settings', 'supabase-auth-bridge'),
            null,
            'supabase-auth-bridge'
        );

        add_settings_field('sab_main_color', __('Main Color', 'supabase-auth-bridge'), array($this, 'render_field_color'), 'supabase-auth-bridge', 'sab_design_section', array('name' => 'sab_main_color', 'default' => '#0073aa'));
        add_settings_field('sab_auth_methods', __('Authentication Methods', 'supabase-auth-bridge'), array($this, 'render_field_auth_methods'), 'supabase-auth-bridge', 'sab_design_section');


        // --- 3. 登録完了メール設定 ---
        register_setting($this->option_group, 'sab_enable_welcome_email');
        register_setting($this->option_group, 'sab_welcome_sender_name', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($this->option_group, 'sab_welcome_sender_email', array('sanitize_callback' => 'sanitize_email'));
        register_setting($this->option_group, 'sab_welcome_subject', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($this->option_group, 'sab_welcome_body', array('sanitize_callback' => 'wp_kses_post'));

        add_settings_section(
            'sab_email_section',
            __('Welcome Email Settings', 'supabase-auth-bridge'),
            array($this, 'email_section_desc'),
            'supabase-auth-bridge'
        );

        add_settings_field('sab_enable_welcome_email', __('Enable Welcome Email', 'supabase-auth-bridge'), array($this, 'render_checkbox_simple'), 'supabase-auth-bridge', 'sab_email_section', array('name' => 'sab_enable_welcome_email', 'label' => __('Send a welcome email upon new user registration.', 'supabase-auth-bridge')));
        add_settings_field('sab_welcome_sender_name', __('Sender Name', 'supabase-auth-bridge'), array($this, 'render_field_text'), 'supabase-auth-bridge', 'sab_email_section', array('name' => 'sab_welcome_sender_name', 'placeholder' => get_bloginfo('name')));
        add_settings_field('sab_welcome_sender_email', __('Sender Email', 'supabase-auth-bridge'), array($this, 'render_field_text'), 'supabase-auth-bridge', 'sab_email_section', array('name' => 'sab_welcome_sender_email', 'placeholder' => get_option('admin_email')));

        // ★修正: デフォルト値を国際化対応 (英語ベース)
        add_settings_field('sab_welcome_subject', __('Email Subject', 'supabase-auth-bridge'), array($this, 'render_field_text'), 'supabase-auth-bridge', 'sab_email_section', array('name' => 'sab_welcome_subject', 'default' => __('Registration Thank You', 'supabase-auth-bridge')));
        // ★修正: デフォルト値を国際化対応 (英語ベース)
        add_settings_field('sab_welcome_body', __('Email Body', 'supabase-auth-bridge'), array($this, 'render_textarea'), 'supabase-auth-bridge', 'sab_email_section', array('name' => 'sab_welcome_body', 'default' => __("Registration to {site_name} is complete.\n\nUser: {email}", 'supabase-auth-bridge')));


        // --- 4. リダイレクト設定 ---
        register_setting($this->option_group, 'sab_redirect_after_login', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($this->option_group, 'sab_redirect_after_logout', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($this->option_group, 'sab_forgot_password_url', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($this->option_group, 'sab_password_reset_url', array('sanitize_callback' => 'sanitize_text_field'));

        add_settings_section(
            'sab_redirect_section',
            __('Redirect Settings (URL Paths)', 'supabase-auth-bridge'),
            array($this, 'redirect_section_desc'),
            'supabase-auth-bridge'
        );

        add_settings_field('sab_redirect_after_login', __('Redirect after Login', 'supabase-auth-bridge'), array($this, 'render_field_text'), 'supabase-auth-bridge', 'sab_redirect_section', array('name' => 'sab_redirect_after_login', 'placeholder' => '/my-page'));
        add_settings_field('sab_redirect_after_logout', __('Redirect after Logout', 'supabase-auth-bridge'), array($this, 'render_field_text'), 'supabase-auth-bridge', 'sab_redirect_section', array('name' => 'sab_redirect_after_logout', 'placeholder' => '/'));

        add_settings_field('sab_forgot_password_url', __('Forgot Password Page URL', 'supabase-auth-bridge'), array($this, 'render_field_text'), 'supabase-auth-bridge', 'sab_redirect_section', array('name' => 'sab_forgot_password_url', 'placeholder' => '/forgot-password', 'desc' => __('Link destination for "Forgot Password?" on the login screen.', 'supabase-auth-bridge')));
        add_settings_field('sab_password_reset_url', __('Password Reset Page URL (New PW)', 'supabase-auth-bridge'), array($this, 'render_field_text'), 'supabase-auth-bridge', 'sab_redirect_section', array('name' => 'sab_password_reset_url', 'placeholder' => '/reset-password', 'desc' => __('Destination after clicking the link in the reset email.', 'supabase-auth-bridge')));


        // --- 5. メンテナンス設定 (Keep Alive) ---
        register_setting($this->option_group, 'sab_enable_keep_alive');

        add_settings_section(
            'sab_maintenance_section',
            __('Maintenance Settings', 'supabase-auth-bridge'),
            null,
            'supabase-auth-bridge'
        );

        add_settings_field('sab_enable_keep_alive', __('Enable Keep Alive', 'supabase-auth-bridge'), array($this, 'render_checkbox_simple'), 'supabase-auth-bridge', 'sab_maintenance_section', array('name' => 'sab_enable_keep_alive', 'label' => __('Access Supabase once a day to prevent the free project from pausing.', 'supabase-auth-bridge')));
    }

    function redirect_section_desc() {
        echo '<p>' . esc_html__('Enter the URL path to redirect to after each action completes.', 'supabase-auth-bridge') . '</p>';
    }

    function email_section_desc() {
        echo '<p>' . esc_html__('Configure the automated email sent when a new user registers via Supabase.', 'supabase-auth-bridge') . '<br>' .
            __('Available placeholders: ', 'supabase-auth-bridge') . '<code>{email}</code>, <code>{site_name}</code>, <code>{site_url}</code></p>';
    }

    // --- ユーザー削除時の同期処理 ---
    function handle_delete_user($user_id) {
        $supabase_uuid = get_user_meta($user_id, 'supabase_uuid', true);

        if (!$supabase_uuid) {
            return;
        }

        $url = get_option('sab_supabase_url');
        $service_key = get_option('sab_supabase_service_role_key');

        if (!$url || !$service_key) {
            // キーがない場合は削除できないが、WP側は削除させる
            return;
        }

        $api_url = rtrim($url, '/') . '/auth/v1/admin/users/' . $supabase_uuid;

        wp_remote_request($api_url, array(
            'method' => 'DELETE',
            'headers' => array(
                'apikey' => $service_key,
                'Authorization' => 'Bearer ' . $service_key,
                'Content-Type' => 'application/json'
            )
        ));
    }

    // --- レンダリング用ヘルパー ---

    function render_field_text($args) {
        $name = $args['name'];
        $placeholder = isset($args['placeholder']) ? $args['placeholder'] : '';
        $default = isset($args['default']) ? $args['default'] : '';
        $value = get_option($name, $default);
        echo '<input type="text" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="regular-text" placeholder="' . esc_attr($placeholder) . '">';
        if (isset($args['desc'])) echo '<p class="description">' . esc_html($args['desc']) . '</p>';
    }

    function render_field_password($args) {
        $name = $args['name'];
        $value = get_option($name);
        echo '<input type="password" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="regular-text">';
        if (isset($args['desc'])) echo '<p class="description">' . esc_html($args['desc']) . '</p>';
    }

    function render_field_color($args) {
        $name = $args['name'];
        $default = isset($args['default']) ? $args['default'] : '#0073aa';
        $value = get_option($name, $default);
        echo '<input type="text" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" class="sab-color-field" data-default-color="' . esc_attr($default) . '">';
    }

    function render_checkbox_simple($args) {
        $name = $args['name'];
        $label = isset($args['label']) ? $args['label'] : '';
        $value = get_option($name);
        echo '<label><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked($value, 1, false) . '> ' . esc_html($label) . '</label>';
    }

    function render_textarea($args) {
        $name = $args['name'];
        $default = isset($args['default']) ? $args['default'] : '';
        $value = get_option($name, $default);
        echo '<textarea name="' . esc_attr($name) . '" rows="10" cols="50" class="large-text code">' . esc_textarea($value) . '</textarea>';
    }

    function render_field_auth_methods() {
        $email = get_option('sab_auth_method_email');
        $google = get_option('sab_auth_method_google');
        $magic = get_option('sab_auth_method_magiclink');
?>
        <fieldset>
            <label><input type="checkbox" name="sab_auth_method_email" value="1" <?php checked($email, 1); ?>> <?php esc_html_e('Email + Password', 'supabase-auth-bridge'); ?></label><br>
            <label><input type="checkbox" name="sab_auth_method_google" value="1" <?php checked($google, 1); ?>> <?php esc_html_e('Google Auth (OAuth)', 'supabase-auth-bridge'); ?></label><br>
            <label><input type="checkbox" name="sab_auth_method_magiclink" value="1" <?php checked($magic, 1); ?>> <?php esc_html_e('Email OTP / Magic Link (Passwordless)', 'supabase-auth-bridge'); ?></label>
        </fieldset>
    <?php
    }

    function admin_enqueue($hook) {
        if (strpos($hook, 'supabase-auth-bridge') === false) return;

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script(SUPABASE_AUTH_BRIDGE_SLUG . '-admin', SUPABASE_AUTH_BRIDGE_URL . '/js/admin.js', array('jquery', 'wp-color-picker'), SUPABASE_AUTH_BRIDGE_VERSION, true);
    }

    function plugin_action_links($links) {
        $url = '<a href="' . esc_url(admin_url("/options-general.php?page=supabase-auth-bridge")) . '">' . esc_html__('Settings', 'supabase-auth-bridge') . '</a>';
        array_unshift($links, $url);
        return $links;
    }

    function show_setting_page() {
    ?>
        <div class="wrap">
            <h1><?php esc_html_e('Supabase Auth Bridge Settings', 'supabase-auth-bridge'); ?></h1>
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

    /**
     * CronによるSupabaseへの定期アクセス実行
     */
    public function execute_keep_alive() {
        // 設定が無効なら何もしない
        if (!get_option('sab_enable_keep_alive')) {
            return;
        }

        $url = get_option('sab_supabase_url');
        $service_key = get_option('sab_supabase_service_role_key');

        if (!$url || !$service_key) {
            return;
        }

        // Supabaseのユーザー一覧取得APIを叩いてアクティビティとする
        // 負荷をかけないよう per_page=1 に制限
        $api_url = rtrim($url, '/') . '/auth/v1/admin/users?page=1&per_page=1';

        wp_remote_get($api_url, array(
            'headers' => array(
                'apikey' => $service_key,
                'Authorization' => 'Bearer ' . $service_key,
                'Content-Type' => 'application/json'
            ),
            'blocking' => false, // 結果を待たずに終了
            'timeout'  => 5
        ));
    }
}
