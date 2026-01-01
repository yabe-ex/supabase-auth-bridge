<?php

class SupabaseAuthBridgeFront {

    public function __construct() {
        // REST APIエンドポイント
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // AJAX: Googleユーザー判定用
        add_action('wp_ajax_sab_check_provider', array($this, 'ajax_check_provider'));
        add_action('wp_ajax_nopriv_sab_check_provider', array($this, 'ajax_check_provider'));

        // ショートコード登録
        add_shortcode('supabase_login', array($this, 'render_login_form'));
        add_shortcode('supabase_register', array($this, 'render_register_form'));
        add_shortcode('supabase_logout', array($this, 'render_logout_button'));
        add_shortcode('supabase_forgot_password', array($this, 'render_forgot_password_form'));
        add_shortcode('supabase_update_password', array($this, 'render_update_password_form'));
    }

    function front_enqueue() {
        $version  = (defined('SUPABASE_AUTH_BRIDGE_DEVELOP') && true === SUPABASE_AUTH_BRIDGE_DEVELOP) ? time() : SUPABASE_AUTH_BRIDGE_VERSION;
        $strategy = array('in_footer' => true, 'strategy'  => 'defer');

        // Supabase JS
        wp_enqueue_script('supabase-js', 'https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2/dist/umd/supabase.min.js', array(), null, $strategy);

        wp_register_style(SUPABASE_AUTH_BRIDGE_SLUG . '-front',  SUPABASE_AUTH_BRIDGE_URL . '/css/front.css', array(), $version);
        wp_register_script(SUPABASE_AUTH_BRIDGE_SLUG . '-front', SUPABASE_AUTH_BRIDGE_URL . '/js/front.js', array('jquery', 'supabase-js'), $version, $strategy);

        wp_enqueue_style(SUPABASE_AUTH_BRIDGE_SLUG . '-front');

        // メインカラーをCSS変数として埋め込み
        $main_color = get_option('sab_main_color', '#0073aa');
        $custom_css = ":root { --sab-primary-color: {$main_color}; }";
        wp_add_inline_style(SUPABASE_AUTH_BRIDGE_SLUG . '-front', $custom_css);

        wp_enqueue_script(SUPABASE_AUTH_BRIDGE_SLUG . '-front');

        // 設定値の取得
        $login_redirect = get_option('sab_redirect_after_login');
        if (empty($login_redirect)) $login_redirect = home_url();

        $logout_redirect = get_option('sab_redirect_after_logout');
        if (empty($logout_redirect)) $logout_redirect = home_url();

        $reset_path = get_option('sab_password_reset_url', '/reset-password');
        $reset_full_url = (strpos($reset_path, 'http') === 0) ? $reset_path : home_url($reset_path);

        // JSに渡すデータ
        $front = array(
            'ajaxurl'       => admin_url('admin-ajax.php'),
            'rest_url'      => rest_url('supabase-auth-bridge/v1/login'),
            'nonce'         => wp_create_nonce('wp_rest'), // REST API用
            'ajax_nonce'    => wp_create_nonce('sab_ajax_nonce'), // AJAX用
            'supabase_url'  => get_option('sab_supabase_url'),
            'supabase_key'  => get_option('sab_supabase_anon_key'),
            'is_logged_in'  => is_user_logged_in() ? "1" : "0",
            'redirect_url'      => $login_redirect,
            'logout_url'        => wp_logout_url($logout_redirect),
            'reset_redirect_to' => $reset_full_url,
        );
        wp_localize_script(SUPABASE_AUTH_BRIDGE_SLUG . '-front', 'sab_vars', $front);
    }

    // --- フォーム描画 ---

    function render_login_form() {
        if (is_user_logged_in()) return '<p>既にログインしています。</p>';

        $use_email = get_option('sab_auth_method_email');
        $use_google = get_option('sab_auth_method_google');
        $use_magic = get_option('sab_auth_method_magiclink');

        // ★追加: パスワード申請ページURLの取得
        $forgot_path = get_option('sab_forgot_password_url', '/forgot-password');
        $forgot_full_url = (strpos($forgot_path, 'http') === 0) ? $forgot_path : home_url($forgot_path);

        ob_start();
?>
        <div id="sab-login-container" class="sab-container">
            <div id="sab-message" class="sab-message" style="display:none;"></div>

            <?php if ($use_email): ?>
                <form id="sab-login-form">
                    <div class="sab-form-group">
                        <label for="sab-email">Email</label>
                        <input type="email" id="sab-email" class="sab-input" required>
                    </div>
                    <div class="sab-form-group">
                        <label for="sab-password">Password</label>
                        <input type="password" id="sab-password" class="sab-input" required>
                    </div>
                    <button type="submit" id="sab-submit" class="sab-btn sab-btn-primary">ログイン</button>
                </form>
                <p class="sab-forgot-link"><a href="<?php echo esc_url($forgot_full_url); ?>">パスワードをお忘れですか？</a></p>
            <?php endif; ?>

            <?php if ($use_magic): ?>
                <?php if ($use_email) echo '<div class="sab-divider"><span>OR</span></div>'; ?>
                <div id="sab-magic-section">
                    <?php if (!$use_email): // メール欄が上のフォームにない場合のみ表示 
                    ?>
                        <div class="sab-form-group">
                            <label for="sab-magic-email">Email</label>
                            <input type="email" id="sab-magic-email" class="sab-input" required>
                        </div>
                    <?php endif; ?>
                    <button type="button" id="sab-magic-link-btn" class="sab-btn sab-btn-secondary">ログインコードを送信 (Passwordless)</button>
                </div>
            <?php endif; ?>

            <?php if ($use_google): ?>
                <?php if ($use_email || $use_magic) echo '<div class="sab-divider"><span>OR</span></div>'; ?>
                <button type="button" id="sab-google-login" class="sab-btn sab-btn-google">Googleでログイン</button>
            <?php endif; ?>
        </div>
    <?php
        return ob_get_clean();
    }

    function render_register_form() {
        if (is_user_logged_in()) return '<p>既にログインしています。</p>';

        $use_email = get_option('sab_auth_method_email');
        $use_google = get_option('sab_auth_method_google');

        ob_start();
    ?>
        <div id="sab-register-container" class="sab-container">
            <div id="sab-reg-message" class="sab-message" style="display:none;"></div>

            <?php if ($use_email): ?>
                <form id="sab-register-form">
                    <div class="sab-form-group">
                        <label for="sab-reg-email">Email</label>
                        <input type="email" id="sab-reg-email" class="sab-input" required>
                    </div>
                    <div class="sab-form-group">
                        <label for="sab-reg-password">Password</label>
                        <input type="password" id="sab-reg-password" class="sab-input" required>
                    </div>
                    <button type="submit" id="sab-reg-submit" class="sab-btn sab-btn-primary">会員登録</button>
                </form>
            <?php endif; ?>

            <?php if ($use_google): ?>
                <?php if ($use_email) echo '<div class="sab-divider"><span>OR</span></div>'; ?>
                <button type="button" class="sab-btn sab-btn-google sab-google-login-btn" onclick="document.getElementById('sab-google-login').click()">Googleで登録</button>
            <?php endif; ?>
        </div>
    <?php
        return ob_get_clean();
    }

    function render_logout_button() {
        if (!is_user_logged_in()) return '';
        return '<button id="sab-logout-button" class="sab-btn sab-btn-secondary">ログアウト</button>';
    }

    function render_forgot_password_form() {
        if (is_user_logged_in()) return '<p>既にログインしています。</p>';
        ob_start();
    ?>
        <div id="sab-forgot-container" class="sab-container">
            <p>登録したメールアドレスを入力してください。<br>パスワード再設定用のリンクをお送りします。</p>
            <form id="sab-forgot-form">
                <div class="sab-form-group">
                    <label for="sab-forgot-email">Email</label>
                    <input type="email" id="sab-forgot-email" class="sab-input" required>
                </div>
                <button type="submit" id="sab-forgot-submit" class="sab-btn sab-btn-primary">送信する</button>
            </form>
            <div id="sab-forgot-message" class="sab-message" style="display:none;"></div>
        </div>
    <?php
        return ob_get_clean();
    }

    function render_update_password_form() {
        ob_start();
    ?>
        <div id="sab-update-password-container" class="sab-container">
            <h3>新しいパスワードの設定</h3>
            <form id="sab-update-password-form">
                <div class="sab-form-group">
                    <label for="sab-new-password">新しいパスワード</label>
                    <input type="password" id="sab-new-password" class="sab-input" required>
                </div>
                <button type="submit" id="sab-update-submit" class="sab-btn sab-btn-primary">パスワードを変更</button>
            </form>
            <div id="sab-update-message" class="sab-message" style="display:none;"></div>
        </div>
<?php
        return ob_get_clean();
    }

    // --- AJAX: Googleユーザー判定 (Service Role Key使用) ---
    function ajax_check_provider() {
        check_ajax_referer('sab_ajax_nonce', 'nonce');

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        if (!$email) wp_send_json_error('Email required');

        $url = get_option('sab_supabase_url');
        $service_key = get_option('sab_supabase_service_role_key');

        if (!$url || !$service_key) {
            wp_send_json_success(array('provider' => 'unknown'));
        }

        // Supabase Admin API: ユーザー一覧取得 (簡易実装)
        $response = wp_remote_get($url . '/auth/v1/admin/users', array(
            'headers' => array(
                'apikey' => $service_key,
                'Authorization' => 'Bearer ' . $service_key
            )
        ));

        if (is_wp_error($response)) {
            wp_send_json_success(array('provider' => 'unknown'));
        }

        $users = json_decode(wp_remote_retrieve_body($response), true);
        $provider = 'email'; // デフォルト

        if (isset($users['users']) && is_array($users['users'])) {
            foreach ($users['users'] as $u) {
                if (strtolower($u['email']) === strtolower($email)) {
                    // provider特定
                    if (isset($u['app_metadata']['provider'])) {
                        $provider = $u['app_metadata']['provider'];
                    } elseif (isset($u['identities']) && !empty($u['identities'])) {
                        $provider = $u['identities'][0]['provider'];
                    }
                    break;
                }
            }
        }

        wp_send_json_success(array('provider' => $provider));
    }

    // --- REST API (ログイン連携) ---
    function register_rest_routes() {
        register_rest_route('supabase-auth-bridge/v1', '/login', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_login_request'),
            'permission_callback' => '__return_true',
        ));
    }

    function handle_login_request($request) {
        $params = $request->get_json_params();
        $access_token = isset($params['access_token']) ? $params['access_token'] : '';

        if (empty($access_token)) return new WP_Error('no_token', 'No Token', array('status' => 400));

        $supabase_url = get_option('sab_supabase_url');
        $supabase_key = get_option('sab_supabase_anon_key');

        $response = wp_remote_get($supabase_url . '/auth/v1/user', array(
            'headers' => array('Authorization' => 'Bearer ' . $access_token, 'apikey' => $supabase_key)
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error('invalid_token', 'Invalid Token', array('status' => 403));
        }

        $user_data = json_decode(wp_remote_retrieve_body($response));
        $email = $user_data->email;
        $uuid  = $user_data->id;

        $user = get_user_by('email', $email);
        if (!$user) {
            $user_id = wp_create_user($email, wp_generate_password(), $email);
            if (is_wp_error($user_id)) return $user_id;
            $user_obj = new WP_User($user_id);
            $user_obj->set_role('subscriber');
            update_user_meta($user_id, 'supabase_uuid', $uuid);
        } else {
            $user_id = $user->ID;
            update_user_meta($user_id, 'supabase_uuid', $uuid);
        }

        wp_clear_auth_cookie();
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        return rest_ensure_response(array('success' => true));
    }
}
