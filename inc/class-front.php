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
            // JavaScript用翻訳テキスト (i18n)
            'i18n' => array(
                'authenticating' => __('Authenticating...', 'supabase-auth-bridge'),
                'login' => __('Login', 'supabase-auth-bridge'),
                'syncing' => __('Syncing with WordPress...', 'supabase-auth-bridge'),
                'error_prefix' => __('Error: ', 'supabase-auth-bridge'),
                'enter_email' => __('Please enter your email address.', 'supabase-auth-bridge'),
                'sending' => __('Sending...', 'supabase-auth-bridge'),
                'send_magic_link' => __('Send Login Code', 'supabase-auth-bridge'),
                'magic_link_sent' => __('Login link (or code) sent to your email.', 'supabase-auth-bridge'),
                'sent_done' => __('Sent', 'supabase-auth-bridge'),
                'registering' => __('Registering...', 'supabase-auth-bridge'),
                'register_btn' => __('Register', 'supabase-auth-bridge'),
                'reg_success' => __('Registration successful! Logging in...', 'supabase-auth-bridge'),
                'check_email' => __('Confirmation email sent. Please check your inbox.', 'supabase-auth-bridge'),
                'check_email_btn' => __('Check your email', 'supabase-auth-bridge'),
                'google_error' => __('Google Error: ', 'supabase-auth-bridge'),
                'logging_out' => __('Logging out...', 'supabase-auth-bridge'),
                'checking' => __('Checking...', 'supabase-auth-bridge'),
                'google_account_alert' => __('This email is registered with Google. Please use the "Log in with Google" button.', 'supabase-auth-bridge'),
                'send_btn' => __('Send', 'supabase-auth-bridge'),
                'reset_email_sent' => __('Reset email sent. Please check your inbox.', 'supabase-auth-bridge'),
                'reset_fallback_msg' => __('Process completed. If you do not receive an email, please try Google Login.', 'supabase-auth-bridge'),
                'updating' => __('Updating...', 'supabase-auth-bridge'),
                'change_btn' => __('Change Password', 'supabase-auth-bridge'),
                'pw_changed' => __('Password changed!', 'supabase-auth-bridge'),
                'sync_login' => __('Syncing login...', 'supabase-auth-bridge'),
                'success_redirect' => __('Success! Redirecting...', 'supabase-auth-bridge'),
                'sync_error' => __('Sync error occurred.', 'supabase-auth-bridge'),
            )
        );
        wp_localize_script(SUPABASE_AUTH_BRIDGE_SLUG . '-front', 'sab_vars', $front);
    }

    // --- フォーム描画 ---

    function render_login_form() {
        if (is_user_logged_in()) return '<p>' . __('You are already logged in.', 'supabase-auth-bridge') . '</p>';

        $use_email = get_option('sab_auth_method_email');
        $use_google = get_option('sab_auth_method_google');
        $use_magic = get_option('sab_auth_method_magiclink');

        $forgot_path = get_option('sab_forgot_password_url', '/forgot-password');
        $forgot_full_url = (strpos($forgot_path, 'http') === 0) ? $forgot_path : home_url($forgot_path);

        ob_start();
?>
        <div id="sab-login-container" class="sab-container">
            <div id="sab-message" class="sab-message" style="display:none;"></div>

            <?php if ($use_email): ?>
                <form id="sab-login-form">
                    <div class="sab-form-group">
                        <label for="sab-email"><?php esc_html_e('Email', 'supabase-auth-bridge'); ?></label>
                        <input type="email" id="sab-email" class="sab-input" required>
                    </div>
                    <div class="sab-form-group">
                        <label for="sab-password"><?php esc_html_e('Password', 'supabase-auth-bridge'); ?></label>
                        <input type="password" id="sab-password" class="sab-input" required>
                    </div>
                    <button type="submit" id="sab-submit" class="sab-btn sab-btn-primary"><?php esc_html_e('Login', 'supabase-auth-bridge'); ?></button>
                </form>
                <p class="sab-forgot-link"><a href="<?php echo esc_url($forgot_full_url); ?>"><?php esc_html_e('Forgot your password?', 'supabase-auth-bridge'); ?></a></p>
            <?php endif; ?>

            <?php if ($use_magic): ?>
                <?php if ($use_email) echo '<div class="sab-divider"><span>' . __('OR', 'supabase-auth-bridge') . '</span></div>'; ?>
                <div id="sab-magic-section">
                    <?php if (!$use_email): ?>
                        <div class="sab-form-group">
                            <label for="sab-magic-email"><?php esc_html_e('Email', 'supabase-auth-bridge'); ?></label>
                            <input type="email" id="sab-magic-email" class="sab-input" required>
                        </div>
                    <?php endif; ?>
                    <button type="button" id="sab-magic-link-btn" class="sab-btn sab-btn-secondary"><?php esc_html_e('Send Login Code (Passwordless)', 'supabase-auth-bridge'); ?></button>
                </div>
            <?php endif; ?>

            <?php if ($use_google): ?>
                <?php if ($use_email || $use_magic) echo '<div class="sab-divider"><span>' . __('OR', 'supabase-auth-bridge') . '</span></div>'; ?>
                <button type="button" id="sab-google-login" class="sab-btn sab-btn-google"><?php esc_html_e('Log in with Google', 'supabase-auth-bridge'); ?></button>
            <?php endif; ?>
        </div>
    <?php
        return ob_get_clean();
    }

    function render_register_form() {
        if (is_user_logged_in()) return '<p>' . __('You are already logged in.', 'supabase-auth-bridge') . '</p>';

        $use_email = get_option('sab_auth_method_email');
        $use_google = get_option('sab_auth_method_google');

        ob_start();
    ?>
        <div id="sab-register-container" class="sab-container">
            <div id="sab-reg-message" class="sab-message" style="display:none;"></div>

            <?php if ($use_email): ?>
                <form id="sab-register-form">
                    <div class="sab-form-group">
                        <label for="sab-reg-email"><?php esc_html_e('Email', 'supabase-auth-bridge'); ?></label>
                        <input type="email" id="sab-reg-email" class="sab-input" required>
                    </div>
                    <div class="sab-form-group">
                        <label for="sab-reg-password"><?php esc_html_e('Password', 'supabase-auth-bridge'); ?></label>
                        <input type="password" id="sab-reg-password" class="sab-input" required>
                    </div>
                    <button type="submit" id="sab-reg-submit" class="sab-btn sab-btn-primary"><?php esc_html_e('Register', 'supabase-auth-bridge'); ?></button>
                </form>
            <?php endif; ?>

            <?php if ($use_google): ?>
                <?php if ($use_email) echo '<div class="sab-divider"><span>' . __('OR', 'supabase-auth-bridge') . '</span></div>'; ?>
                <button type="button" class="sab-btn sab-btn-google sab-google-login-btn" onclick="document.getElementById('sab-google-login').click()"><?php esc_html_e('Register with Google', 'supabase-auth-bridge'); ?></button>
            <?php endif; ?>
        </div>
    <?php
        return ob_get_clean();
    }

    function render_logout_button() {
        if (!is_user_logged_in()) return '';
        return '<button id="sab-logout-button" class="sab-btn sab-btn-secondary">' . __('Logout', 'supabase-auth-bridge') . '</button>';
    }

    function render_forgot_password_form() {
        if (is_user_logged_in()) return '<p>' . __('You are already logged in.', 'supabase-auth-bridge') . '</p>';
        ob_start();
    ?>
        <div id="sab-forgot-container" class="sab-container">
            <p><?php esc_html_e('Enter your registered email address. We will send you a link to reset your password.', 'supabase-auth-bridge'); ?></p>
            <form id="sab-forgot-form">
                <div class="sab-form-group">
                    <label for="sab-forgot-email"><?php esc_html_e('Email', 'supabase-auth-bridge'); ?></label>
                    <input type="email" id="sab-forgot-email" class="sab-input" required>
                </div>
                <button type="submit" id="sab-forgot-submit" class="sab-btn sab-btn-primary"><?php esc_html_e('Send', 'supabase-auth-bridge'); ?></button>
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
            <h3><?php esc_html_e('Set New Password', 'supabase-auth-bridge'); ?></h3>
            <form id="sab-update-password-form">
                <div class="sab-form-group">
                    <label for="sab-new-password"><?php esc_html_e('New Password', 'supabase-auth-bridge'); ?></label>
                    <input type="password" id="sab-new-password" class="sab-input" required>
                </div>
                <button type="submit" id="sab-update-submit" class="sab-btn sab-btn-primary"><?php esc_html_e('Change Password', 'supabase-auth-bridge'); ?></button>
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
        if (!$email) wp_send_json_error(__('Email required', 'supabase-auth-bridge'));

        $url = get_option('sab_supabase_url');
        $service_key = get_option('sab_supabase_service_role_key');

        if (!$url || !$service_key) {
            wp_send_json_success(array('provider' => 'unknown'));
        }

        // -------------------------------------------------------------
        // 戦略1: まずWordPressユーザーとして存在するか確認 (高速化・DoS対策)
        // -------------------------------------------------------------
        $user_wp = get_user_by('email', $email);

        if ($user_wp) {
            $uuid = get_user_meta($user_wp->ID, 'supabase_uuid', true);
            if ($uuid) {
                // UUIDがわかればAPIで直接取得できる (ループ不要)
                $response = wp_remote_get(rtrim($url, '/') . '/auth/v1/admin/users/' . $uuid, array(
                    'headers' => array(
                        'apikey' => $service_key,
                        'Authorization' => 'Bearer ' . $service_key
                    )
                ));

                if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                    $u = json_decode(wp_remote_retrieve_body($response), true);
                    $provider = 'email';
                    if (isset($u['app_metadata']['provider'])) {
                        $provider = $u['app_metadata']['provider'];
                    } elseif (isset($u['identities']) && !empty($u['identities'])) {
                        $provider = $u['identities'][0]['provider'];
                    }
                    wp_send_json_success(array('provider' => $provider));
                    return; // 終了
                }
            }
        }

        // -------------------------------------------------------------
        // 戦略2: WPにいない場合はSupabase全件走査 (人数制限解除・負荷許容)
        // -------------------------------------------------------------
        $page = 1;
        $per_page = 1000; // 1回あたりの取得数
        $found_provider = 'unknown';

        // タイムアウト対策（可能な範囲で拡張）
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        do {
            $api_url = rtrim($url, '/') . '/auth/v1/admin/users?page=' . $page . '&per_page=' . $per_page;

            $response = wp_remote_get($api_url, array(
                'headers' => array(
                    'apikey' => $service_key,
                    'Authorization' => 'Bearer ' . $service_key
                )
            ));

            if (is_wp_error($response)) {
                break; // 通信エラー等はループ終了
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            // レスポンス形式の確認
            $users = isset($body['users']) ? $body['users'] : (is_array($body) ? $body : array());

            if (empty($users)) {
                break; // ユーザーがいなくなったら終了
            }

            // このページ内を検索
            foreach ($users as $u) {
                if (isset($u['email']) && strtolower($u['email']) === strtolower($email)) {
                    $found_provider = 'email'; // デフォルト
                    if (isset($u['app_metadata']['provider'])) {
                        $found_provider = $u['app_metadata']['provider'];
                    } elseif (isset($u['identities']) && !empty($u['identities'])) {
                        $found_provider = $u['identities'][0]['provider'];
                    }
                    break 2; // ループを2つ(foreachとdo-while)抜ける
                }
            }

            // 取得件数がper_page未満なら、これ以上ページはないので終了
            if (count($users) < $per_page) {
                break;
            }

            $page++;
            // サーバー負荷軽減のためわずかに待機
            usleep(50000);
        } while (true);

        // 結果を返す (見つからなければ unknown)
        wp_send_json_success(array('provider' => $found_provider));
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

        if (empty($access_token)) return new WP_Error('no_token', __('No Token', 'supabase-auth-bridge'), array('status' => 400));

        $supabase_url = get_option('sab_supabase_url');
        $supabase_key = get_option('sab_supabase_anon_key');

        // 1. Supabaseでトークン検証 & ユーザー情報取得
        $response = wp_remote_get($supabase_url . '/auth/v1/user', array(
            'headers' => array('Authorization' => 'Bearer ' . $access_token, 'apikey' => $supabase_key)
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error('invalid_token', __('Invalid Token', 'supabase-auth-bridge'), array('status' => 403));
        }

        $user_data = json_decode(wp_remote_retrieve_body($response));
        $email = isset($user_data->email) ? $user_data->email : '';
        $uuid  = isset($user_data->id) ? $user_data->id : '';

        // [セキュリティ] メール未確認ユーザーはログインさせない
        if (empty($user_data->email_confirmed_at) && empty($user_data->confirmed_at)) {
            return new WP_Error('email_not_confirmed', __('Email not confirmed. Please check your inbox.', 'supabase-auth-bridge'), array('status' => 403));
        }

        if (!$email || !$uuid) {
            return new WP_Error('invalid_user_data', __('Invalid User Data', 'supabase-auth-bridge'), array('status' => 400));
        }

        // 2. WordPressユーザーの検索・同期
        // [修正] EmailではなくSupabase UUIDで検索 (メール変更対応)
        $user_query = get_users(array(
            'meta_key' => 'supabase_uuid',
            'meta_value' => $uuid,
            'number' => 1,
            'count_total' => false
        ));

        $user = !empty($user_query) ? $user_query[0] : null;
        $user_id = 0;

        if ($user) {
            // 既存ユーザーが見つかった場合
            $user_id = $user->ID;

            // [セキュリティ] 管理者権限を持つユーザーは自動連携ログインを拒否 (乗っ取り防止)
            if (user_can($user, 'manage_options')) {
                return new WP_Error('forbidden', __('Administrator login via Supabase is disabled for security.', 'supabase-auth-bridge'), array('status' => 403));
            }

            // Supabase側でメールが変わっていたらWP側も更新
            if (strtolower($user->user_email) !== strtolower($email)) {
                $updated = wp_update_user(array('ID' => $user_id, 'user_email' => $email));
                if (is_wp_error($updated)) {
                    return new WP_Error('update_failed', __('Failed to update email.', 'supabase-auth-bridge'), array('status' => 500));
                }
            }
        } else {
            // UUIDで見つからない場合、Emailで再検索 (初回連携 or 既存WPユーザーとの紐づけ)
            $user_by_email = get_user_by('email', $email);

            if ($user_by_email) {
                // Emailで既存ユーザーが見つかった
                $user_id = $user_by_email->ID;

                // [セキュリティ] 管理者チェック
                if (user_can($user_by_email, 'manage_options')) {
                    return new WP_Error('forbidden', __('Administrator login via Supabase is disabled for security.', 'supabase-auth-bridge'), array('status' => 403));
                }

                // UUIDを紐付け
                update_user_meta($user_id, 'supabase_uuid', $uuid);
            } else {
                // 新規ユーザー作成
                $password = wp_generate_password();
                $user_id = wp_create_user($email, $password, $email);

                if (is_wp_error($user_id)) {
                    return $user_id;
                }

                // 権限設定 (購読者)
                $user_obj = new WP_User($user_id);
                $user_obj->set_role('subscriber');

                // UUID保存
                update_user_meta($user_id, 'supabase_uuid', $uuid);
            }
        }

        // 3. ログイン処理
        wp_clear_auth_cookie();
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        return rest_ensure_response(array('success' => true));
    }
}
