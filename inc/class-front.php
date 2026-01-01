<?php

class SupabaseAuthBridgeFront {

    public function __construct() {
        // REST APIエンドポイント
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // ショートコード登録
        add_shortcode('supabase_login', array($this, 'render_login_form'));
        add_shortcode('supabase_register', array($this, 'render_register_form'));
        add_shortcode('supabase_logout', array($this, 'render_logout_button'));

        // ★新規追加: パスワードリセット用ショートコード
        add_shortcode('supabase_forgot_password', array($this, 'render_forgot_password_form')); // 申請フォーム
        add_shortcode('supabase_update_password', array($this, 'render_update_password_form')); // 更新フォーム
    }

    function front_enqueue() {
        $version  = (defined('SUPABASE_AUTH_BRIDGE_DEVELOP') && true === SUPABASE_AUTH_BRIDGE_DEVELOP) ? time() : SUPABASE_AUTH_BRIDGE_VERSION;
        $strategy = array('in_footer' => true, 'strategy'  => 'defer');

        // Supabase JS (UMD版)
        wp_enqueue_script('supabase-js', 'https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2/dist/umd/supabase.min.js', array(), null, $strategy);

        wp_register_style(SUPABASE_AUTH_BRIDGE_SLUG . '-front',  SUPABASE_AUTH_BRIDGE_URL . '/css/front.css', array(), $version);
        wp_register_script(SUPABASE_AUTH_BRIDGE_SLUG . '-front', SUPABASE_AUTH_BRIDGE_URL . '/js/front.js', array('jquery', 'supabase-js'), $version, $strategy);

        wp_enqueue_style(SUPABASE_AUTH_BRIDGE_SLUG . '-front');
        wp_enqueue_script(SUPABASE_AUTH_BRIDGE_SLUG . '-front');

        // --- 設定値の取得と加工 ---
        $login_redirect = get_option('sab_redirect_after_login');
        if (empty($login_redirect)) $login_redirect = home_url();

        $logout_redirect = get_option('sab_redirect_after_logout');
        if (empty($logout_redirect)) $logout_redirect = home_url();

        // パスワードリセットページの完全なURLを作る
        $reset_path = get_option('sab_password_reset_url', '/reset-password');
        $reset_full_url = (strpos($reset_path, 'http') === 0) ? $reset_path : home_url($reset_path);

        // JSに渡すデータ
        $front = array(
            'ajaxurl'       => admin_url('admin-ajax.php'),
            'rest_url'      => rest_url('supabase-auth-bridge/v1/login'),
            'nonce'         => wp_create_nonce('wp_rest'),
            'supabase_url'  => get_option('sab_supabase_url'),
            'supabase_key'  => get_option('sab_supabase_anon_key'),
            'is_logged_in'  => is_user_logged_in() ? "1" : "0",

            // ★動的なURL設定
            'redirect_url'      => $login_redirect,
            'logout_url'        => wp_logout_url($logout_redirect), // WPログアウト後に指定URLへ
            'reset_redirect_to' => $reset_full_url, // メール内のリンクの飛び先
        );
        wp_localize_script(SUPABASE_AUTH_BRIDGE_SLUG . '-front', 'sab_vars', $front);
    }

    // --- フォーム描画 ---

    function render_login_form() {
        if (is_user_logged_in()) return '<p>既にログインしています。</p>';
        ob_start();
?>
        <div id="sab-login-container" class="sab-container">
            <form id="sab-login-form">
                <p><label>Email<br><input type="email" id="sab-email" required style="width:100%"></label></p>
                <p><label>Password<br><input type="password" id="sab-password" required style="width:100%"></label></p>
                <button type="submit" id="sab-submit">ログイン</button>
            </form>
            <div style="margin-top: 15px; border-top: 1px solid #ccc; padding-top: 15px;">
                <button type="button" id="sab-google-login" style="background:#4285F4; color:white; width:100%;">Googleでログイン</button>
            </div>
            <p style="margin-top:10px; font-size:0.9em;"><a href="<?php echo esc_url(home_url('/forgot-password')); ?>">パスワードをお忘れですか？</a></p>
            <div id="sab-message" style="margin-top:10px;"></div>
        </div>
    <?php
        return ob_get_clean();
    }

    function render_register_form() {
        if (is_user_logged_in()) return '<p>既にログインしています。</p>';
        ob_start();
    ?>
        <div id="sab-register-container" class="sab-container">
            <form id="sab-register-form">
                <p><label>Email<br><input type="email" id="sab-reg-email" required style="width:100%"></label></p>
                <p><label>Password<br><input type="password" id="sab-reg-password" required style="width:100%"></label></p>
                <button type="submit" id="sab-reg-submit">会員登録</button>
            </form>
            <div style="margin-top: 15px; border-top: 1px solid #ccc; padding-top: 15px;">
                <button type="button" class="sab-google-login-btn" style="background:#4285F4; color:white; width:100%;" onclick="document.getElementById('sab-google-login').click()">Googleで登録</button>
            </div>
            <div id="sab-reg-message" style="margin-top:10px;"></div>
        </div>
    <?php
        return ob_get_clean();
    }

    function render_logout_button() {
        if (!is_user_logged_in()) return '';
        return '<button id="sab-logout-button">ログアウト</button>';
    }

    // ★追加: パスワードリセット申請フォーム（メアド入力）
    function render_forgot_password_form() {
        if (is_user_logged_in()) return '<p>既にログインしています。</p>';
        ob_start();
    ?>
        <div id="sab-forgot-container" class="sab-container">
            <p>登録したメールアドレスを入力してください。<br>パスワード再設定用のリンクをお送りします。</p>
            <form id="sab-forgot-form">
                <p><label>Email<br><input type="email" id="sab-forgot-email" required style="width:100%"></label></p>
                <button type="submit" id="sab-forgot-submit">送信する</button>
            </form>
            <div id="sab-forgot-message" style="margin-top:10px;"></div>
        </div>
    <?php
        return ob_get_clean();
    }

    // ★追加: パスワード更新フォーム（新しいPW入力）
    function render_update_password_form() {
        ob_start();
    ?>
        <div id="sab-update-password-container" class="sab-container">
            <h3>新しいパスワードの設定</h3>
            <form id="sab-update-password-form">
                <p><label>新しいパスワード<br><input type="password" id="sab-new-password" required style="width:100%"></label></p>
                <button type="submit" id="sab-update-submit">パスワードを変更</button>
            </form>
            <div id="sab-update-message" style="margin-top:10px;"></div>
        </div>
<?php
        return ob_get_clean();
    }

    // --- REST API ---
    function register_rest_routes() {
        register_rest_route('supabase-auth-bridge/v1', '/login', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_login_request'),
            'permission_callback' => '__return_true',
        ));
    }

    function handle_login_request($request) {
        // (前回と同じため省略なしで記述)
        $params = $request->get_json_params();
        $access_token = isset($params['access_token']) ? $params['access_token'] : '';

        if (empty($access_token)) return new WP_Error('no_token', 'No Token', array('status' => 400));

        $supabase_url = get_option('sab_supabase_url');
        $supabase_key = get_option('sab_supabase_anon_key');

        if (!$supabase_url || !$supabase_key) return new WP_Error('config_error', 'Config Error', array('status' => 500));

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
