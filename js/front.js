jQuery(document).ready(function ($) {
    // 設定チェック
    if (!window.sab_vars || !sab_vars.supabase_url || !sab_vars.supabase_key) {
        console.error('Supabase settings missing');
        return;
    }

    const { createClient } = window.supabase;
    const supabase = createClient(sab_vars.supabase_url, sab_vars.supabase_key);
    const i18n = sab_vars.i18n;

    // ---------------------------
    // 【追加】強制ログアウト処理
    // ---------------------------
    // WordPress側でログアウト処理が行われた場合、Cookie経由でフラグが渡される
    if (sab_vars.trigger_logout === '1') {
        console.log('WP Logout detected -> Signing out from Supabase...');
        supabase.auth.signOut().then(() => {
            // ループ防止のためCookieを削除
            document.cookie = 'sab_force_logout=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
        });
    }

    // ヘルパー: メッセージ表示
    function showMsg(elem, text, isError = false) {
        $(elem)
            .show()
            .text(text)
            .removeClass('sab-success sab-error')
            .addClass(isError ? 'sab-error' : 'sab-success');
    }

    // ---------------------------
    // 1. 通常ログイン (Email & Password)
    // ---------------------------
    $('#sab-login-form').on('submit', async function (e) {
        e.preventDefault();
        const email = $('#sab-email').val();
        const password = $('#sab-password').val();
        const $btn = $('#sab-submit');
        const $msg = $('#sab-message');

        $btn.prop('disabled', true).text(i18n.authenticating);
        $msg.hide();

        const { data, error } = await supabase.auth.signInWithPassword({ email, password });

        if (error) {
            showMsg($msg, i18n.error_prefix + error.message, true);
            $btn.prop('disabled', false).text(i18n.login);
        } else {
            $btn.text(i18n.syncing);
            syncWithWordPress(data.session.access_token, $msg);
        }
    });

    // ---------------------------
    // 2. マジックリンク (Email OTP)
    // ---------------------------
    $('#sab-magic-link-btn').on('click', async function () {
        let email = $('#sab-email').val();
        if (!email) email = $('#sab-magic-email').val();

        const $msg = $('#sab-message');
        const $btn = $(this);

        if (!email) {
            $msg.show().text(i18n.enter_email).addClass('sab-error');
            return;
        }

        $btn.prop('disabled', true).text(i18n.sending);
        $msg.hide();

        const { error } = await supabase.auth.signInWithOtp({
            email: email,
            options: { shouldCreateUser: false }
        });

        if (error) {
            showMsg($msg, i18n.error_prefix + error.message, true);
            $btn.prop('disabled', false).text(i18n.send_magic_link);
        } else {
            showMsg($msg, i18n.magic_link_sent, false);
            $btn.text(i18n.sent_done);
        }
    });

    // ---------------------------
    // 3. 新規登録
    // ---------------------------
    $('#sab-register-form').on('submit', async function (e) {
        e.preventDefault();
        const email = $('#sab-reg-email').val();
        const password = $('#sab-reg-password').val();
        const $btn = $('#sab-reg-submit');
        const $msg = $('#sab-reg-message');

        $btn.prop('disabled', true).text(i18n.registering);
        $msg.hide();

        const { data, error } = await supabase.auth.signUp({ email, password });

        if (error) {
            showMsg($msg, i18n.error_prefix + error.message, true);
            $btn.prop('disabled', false).text(i18n.register_btn);
        } else if (data.session) {
            showMsg($msg, i18n.reg_success, false);
            syncWithWordPress(data.session.access_token, $msg);
        } else {
            showMsg($msg, i18n.check_email, false);
            $btn.text(i18n.check_email_btn);
        }
    });

    // ---------------------------
    // 4. Googleログイン
    // ---------------------------
    $(document).on('click', '#sab-google-login, .sab-google-login-btn', async function () {
        const { error } = await supabase.auth.signInWithOAuth({
            provider: 'google',
            options: { redirectTo: window.location.href }
        });
        if (error) alert(i18n.google_error + error.message);
    });

    // ---------------------------
    // 5. ログアウト (ボタンクリック時)
    // ---------------------------
    $('#sab-logout-button').on('click', async function (e) {
        e.preventDefault();
        $(this).text(i18n.logging_out);
        await supabase.auth.signOut();
        window.location.href = sab_vars.logout_url;
    });

    // ---------------------------
    // 6. パスワードリセット申請 (Smart Check)
    // ---------------------------
    $('#sab-forgot-form').on('submit', async function (e) {
        e.preventDefault();
        const email = $('#sab-forgot-email').val();
        const $btn = $('#sab-forgot-submit');
        const $msg = $('#sab-forgot-message');

        $btn.prop('disabled', true).text(i18n.checking);
        $msg.hide();

        // 1. サーバーサイドで判定
        $.ajax({
            url: sab_vars.ajaxurl,
            method: 'POST',
            data: {
                action: 'sab_check_provider',
                nonce: sab_vars.ajax_nonce,
                email: email
            },
            success: async function (response) {
                if (response.success && response.data.provider === 'google') {
                    // Googleユーザー: 案内を表示
                    showMsg($msg, i18n.google_account_alert, false);
                    $btn.prop('disabled', false).text(i18n.send_btn);
                } else {
                    // 通常フロー
                    $btn.text(i18n.sending);
                    const { error } = await supabase.auth.resetPasswordForEmail(email, {
                        redirectTo: sab_vars.reset_redirect_to
                    });

                    if (error) {
                        showMsg($msg, i18n.error_prefix + error.message, true);
                        $btn.prop('disabled', false).text(i18n.send_btn);
                    } else {
                        showMsg($msg, i18n.reset_email_sent, false);
                        $btn.text(i18n.sent_done);
                    }
                }
            },
            error: function () {
                // エラー時は安全策として送信
                supabase.auth.resetPasswordForEmail(email, { redirectTo: sab_vars.reset_redirect_to });
                showMsg($msg, i18n.reset_fallback_msg, false);
            }
        });
    });

    // ---------------------------
    // 7. パスワード更新
    // ---------------------------
    $('#sab-update-password-form').on('submit', async function (e) {
        e.preventDefault();
        const newPassword = $('#sab-new-password').val();
        const $btn = $('#sab-update-submit');
        const $msg = $('#sab-update-message');

        $btn.prop('disabled', true).text(i18n.updating);
        $msg.hide();

        const { error } = await supabase.auth.updateUser({ password: newPassword });

        if (error) {
            showMsg($msg, i18n.error_prefix + error.message, true);
            $btn.prop('disabled', false).text(i18n.change_btn);
        } else {
            showMsg($msg, i18n.pw_changed, false);
            setTimeout(function () {
                window.location.href = sab_vars.redirect_url;
            }, 1000);
        }
    });

    // ---------------------------
    // 8. 自動同期
    // ---------------------------
    supabase.auth.onAuthStateChange(async (event, session) => {
        if (event === 'SIGNED_IN' && session) {
            if (sab_vars.is_logged_in == '1') return;
            let $msg = $('#sab-message');
            if (!$msg.length) $msg = $('#sab-reg-message');

            console.log('Supabase Login Detected -> Syncing...');
            if ($msg.length) $msg.show().text(i18n.sync_login);

            syncWithWordPress(session.access_token, $msg);
        }
    });

    function syncWithWordPress(accessToken, $msgElement) {
        $.ajax({
            url: sab_vars.rest_url,
            method: 'POST',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', sab_vars.nonce);
            },
            contentType: 'application/json',
            data: JSON.stringify({ access_token: accessToken }),
            success: function (response) {
                if (response.success) {
                    if ($msgElement && $msgElement.length) showMsg($msgElement, i18n.success_redirect, false);
                    setTimeout(function () {
                        window.location.href = sab_vars.redirect_url;
                    }, 500);
                }
            },
            error: function (xhr) {
                console.error(xhr);
                if ($msgElement && $msgElement.length) showMsg($msgElement, i18n.sync_error, true);
            }
        });
    }
});
