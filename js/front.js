jQuery(document).ready(function ($) {
    // 設定チェック
    if (!window.sab_vars || !sab_vars.supabase_url || !sab_vars.supabase_key) {
        console.error('Supabase settings missing');
        return;
    }

    const { createClient } = window.supabase;
    const supabase = createClient(sab_vars.supabase_url, sab_vars.supabase_key);
    const i18n = sab_vars.i18n;

    // ★追加: 登録処理中かどうかのフラグ
    let isProcessingRegistration = false;

    // ---------------------------
    // 【追加】ログイン成功メッセージ表示処理
    // ---------------------------
    // URLパラメータに 'sab_logged_in=1' があればメッセージを表示
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('sab_logged_in') === '1') {
        // URLからパラメータを削除（リロード時に再表示させないため）
        const newUrl = window.location.href.replace(/[\?&]sab_logged_in=1/, '');
        window.history.replaceState({}, document.title, newUrl);

        // トースト要素を作成してbodyに追加
        // ★修正: ハードコードされていた日本語を変数に置き換え
        const $toast = $('<div id="sab-login-toast">' + i18n.logged_in + '</div>');
        $('body').append($toast);

        // フェードイン -> 待機 -> フェードアウト
        $toast.fadeIn(500, function () {
            setTimeout(function () {
                $toast.fadeOut(1000, function () {
                    $(this).remove();
                });
            }, 2500); // 2.5秒間表示
        });
    }

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
            // 手動ログインなので isAutoSync=false (デフォルト)
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

        // ★追加: 登録処理開始フラグON
        isProcessingRegistration = true;

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
            // ★追加: エラー時はフラグを戻す
            isProcessingRegistration = false;
        } else if (data.session) {
            showMsg($msg, i18n.reg_success, false);
            // 手動登録なので isAutoSync=false (デフォルト)
            syncWithWordPress(data.session.access_token, $msg);
        } else {
            showMsg($msg, i18n.check_email, false);
            $btn.text(i18n.check_email_btn);
            // ★追加: 確認メール送信のみでセッションがない場合もフラグを戻す
            isProcessingRegistration = false;
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

            // ★追加: 登録処理中の場合は、ここでの同期を行わない
            if (isProcessingRegistration) {
                console.log('Skipping auto-sync because registration flow is active.');
                return;
            }

            console.log('Supabase Login Detected -> Auto Syncing...');

            // ★変更: 自動同期フラグをtrueにして呼び出す（エラー時はメッセージを出さずログアウトする）
            syncWithWordPress(session.access_token, null, true);
        }
    });

    /**
     * WordPressと同期する関数
     * @param {string} accessToken
     * @param {jQueryObject} $msgElement メッセージを表示する要素（ボタン押下時のみ）
     * @param {boolean} isAutoSync 自動同期かどうか（trueならエラー時に表示せずログアウトする）
     */
    function syncWithWordPress(accessToken, $msgElement, isAutoSync = false) {
        // 手動操作（ボタン押下）の場合のみメッセージを表示
        if (!isAutoSync && $msgElement && $msgElement.length) {
            // テキストのみ更新
            $msgElement.show().text(i18n.sync_login);
        }

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
                    // 成功メッセージは手動時のみ表示
                    if (!isAutoSync && $msgElement && $msgElement.length) {
                        showMsg($msgElement, i18n.success_redirect, false);
                    }
                    setTimeout(function () {
                        let redirectUrl = sab_vars.redirect_url;
                        // パラメータ付与（ログイン完了メッセージ用）
                        redirectUrl += (redirectUrl.indexOf('?') === -1 ? '?' : '&') + 'sab_logged_in=1';
                        window.location.href = redirectUrl;
                    }, 500);
                }
            },
            error: function (xhr) {
                console.error('Sync Error Details:', xhr);

                // ★追加: 自動同期でエラーが出た場合の処理 (サイレントログアウト)
                if (isAutoSync) {
                    console.warn('Auto-sync failed. Signing out from Supabase to clean up state.');
                    // エラーメッセージは出さずに、Supabaseからもログアウトして整合性を取る
                    supabase.auth.signOut();
                    return;
                }

                // --- 以下、手動操作（ボタン押下）時のエラー表示 ---
                let errMsg = i18n.sync_error;

                // サーバーからのエラーメッセージがあればそれを追加
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errMsg += ' (' + xhr.responseJSON.message + ')';
                }
                // WAFなどでHTMLが返ってきた場合やJSONパース不能な場合の403
                else if (xhr.status === 403) {
                    errMsg += ' (403 Forbidden: WAF or Nonce Error)';
                }
                // その他のエラー
                else {
                    errMsg += ' (' + xhr.status + ' ' + xhr.statusText + ')';
                }

                if ($msgElement && $msgElement.length) showMsg($msgElement, errMsg, true);
            }
        });
    }
});
