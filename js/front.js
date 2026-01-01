jQuery(document).ready(function ($) {
    // 設定チェック
    if (!window.sab_vars || !sab_vars.supabase_url || !sab_vars.supabase_key) {
        console.error('Supabase settings missing');
        return;
    }

    const { createClient } = window.supabase;
    const supabase = createClient(sab_vars.supabase_url, sab_vars.supabase_key);

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

        $btn.prop('disabled', true).text('認証中...');
        $msg.hide();

        const { data, error } = await supabase.auth.signInWithPassword({ email, password });

        if (error) {
            showMsg($msg, 'エラー: ' + error.message, true);
            $btn.prop('disabled', false).text('ログイン');
        } else {
            $btn.text('WordPress連携中...');
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
            $msg.show().text('メールアドレスを入力してください').addClass('sab-error');
            return;
        }

        $btn.prop('disabled', true).text('送信中...');
        $msg.hide();

        const { error } = await supabase.auth.signInWithOtp({
            email: email,
            options: { shouldCreateUser: false }
        });

        if (error) {
            showMsg($msg, 'エラー: ' + error.message, true);
            $btn.prop('disabled', false).text('ログインコードを送信');
        } else {
            showMsg($msg, 'ログイン用のリンク(またはコード)をメールで送信しました。', false);
            $btn.text('送信完了');
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

        $btn.prop('disabled', true).text('登録中...');
        $msg.hide();

        const { data, error } = await supabase.auth.signUp({ email, password });

        if (error) {
            showMsg($msg, 'エラー: ' + error.message, true);
            $btn.prop('disabled', false).text('会員登録');
        } else if (data.session) {
            showMsg($msg, '登録成功！ログインします...', false);
            syncWithWordPress(data.session.access_token, $msg);
        } else {
            showMsg($msg, '確認メールを送信しました。メール内のリンクをクリックしてください。', false);
            $btn.text('メールを確認してください');
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
        if (error) alert('Google Error: ' + error.message);
    });

    // ---------------------------
    // 5. ログアウト
    // ---------------------------
    $('#sab-logout-button').on('click', async function (e) {
        e.preventDefault();
        $(this).text('ログアウト中...');
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

        $btn.prop('disabled', true).text('確認中...');
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
                    showMsg($msg, 'このメールアドレスはGoogleアカウントで登録されています。「Googleでログイン」ボタンをご利用ください。', false);
                    $btn.prop('disabled', false).text('送信する');
                } else {
                    // 通常フロー
                    $btn.text('送信中...');
                    const { error } = await supabase.auth.resetPasswordForEmail(email, {
                        redirectTo: sab_vars.reset_redirect_to
                    });

                    if (error) {
                        showMsg($msg, 'エラー: ' + error.message, true);
                        $btn.prop('disabled', false).text('送信する');
                    } else {
                        showMsg($msg, '再設定メールを送信しました。メールをご確認ください。', false);
                        $btn.text('送信完了');
                    }
                }
            },
            error: function () {
                // エラー時は安全策として送信
                supabase.auth.resetPasswordForEmail(email, { redirectTo: sab_vars.reset_redirect_to });
                showMsg($msg, '処理が完了しました。メールが届かない場合はGoogleログインをお試しください。', false);
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

        $btn.prop('disabled', true).text('更新中...');
        $msg.hide();

        const { error } = await supabase.auth.updateUser({ password: newPassword });

        if (error) {
            showMsg($msg, 'エラー: ' + error.message, true);
            $btn.prop('disabled', false).text('変更する');
        } else {
            showMsg($msg, 'パスワードを変更しました！', false);
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
            if ($msg.length) $msg.show().text('ログイン連携中...');

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
                    if ($msgElement && $msgElement.length) showMsg($msgElement, '成功！移動します...', false);
                    setTimeout(function () {
                        window.location.href = sab_vars.redirect_url;
                    }, 500);
                }
            },
            error: function (xhr) {
                console.error(xhr);
                if ($msgElement && $msgElement.length) showMsg($msgElement, '連携エラーが発生しました。', true);
            }
        });
    }
});
