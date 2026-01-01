jQuery(document).ready(function ($) {
    // 設定チェック
    if (!window.sab_vars || !sab_vars.supabase_url || !sab_vars.supabase_key) {
        console.error('Supabase settings missing');
        return;
    }

    const { createClient } = window.supabase;
    const supabase = createClient(sab_vars.supabase_url, sab_vars.supabase_key);

    // ---------------------------
    // 1. 通常ログイン
    // ---------------------------
    $('#sab-login-form').on('submit', async function (e) {
        e.preventDefault();
        const email = $('#sab-email').val();
        const password = $('#sab-password').val();
        const $btn = $('#sab-submit');
        const $msg = $('#sab-message');

        $btn.prop('disabled', true).text('認証中...');
        $msg.text('');

        const { data, error } = await supabase.auth.signInWithPassword({ email, password });

        if (error) {
            $msg.css('color', 'red').text('エラー: ' + error.message);
            $btn.prop('disabled', false).text('ログイン');
        } else {
            $btn.text('WordPress連携中...');
            syncWithWordPress(data.session.access_token, $msg);
        }
    });

    // ---------------------------
    // 2. 新規登録
    // ---------------------------
    $('#sab-register-form').on('submit', async function (e) {
        e.preventDefault();
        const email = $('#sab-reg-email').val();
        const password = $('#sab-reg-password').val();
        const $btn = $('#sab-reg-submit');
        const $msg = $('#sab-reg-message');

        $btn.prop('disabled', true).text('登録中...');
        $msg.text('');

        // Email確認が必要な場合を考慮し、redirectToに「ログイン後のリダイレクト先」を指定しても良いが、
        // ここではシンプルに登録処理を行う
        const { data, error } = await supabase.auth.signUp({ email, password });

        if (error) {
            $msg.css('color', 'red').text('エラー: ' + error.message);
            $btn.prop('disabled', false).text('会員登録');
        } else if (data.session) {
            $msg.css('color', 'green').text('登録成功！ログインします...');
            syncWithWordPress(data.session.access_token, $msg);
        } else {
            $msg.css('color', 'blue').text('確認メールを送信しました。メール内のリンクをクリックしてください。');
            $btn.text('メールを確認してください');
        }
    });

    // ---------------------------
    // 3. Googleログイン
    // ---------------------------
    $('#sab-google-login').on('click', async function () {
        const { error } = await supabase.auth.signInWithOAuth({
            provider: 'google',
            options: { redirectTo: window.location.href }
        });
        if (error) alert('Google Error: ' + error.message);
    });

    // ---------------------------
    // 4. ログアウト
    // ---------------------------
    $('#sab-logout-button').on('click', async function (e) {
        e.preventDefault();
        $(this).text('ログアウト中...');
        await supabase.auth.signOut();
        window.location.href = sab_vars.logout_url; // 設定画面のURLへ
    });

    // ---------------------------
    // 5. パスワードリセット申請 (Forgot Password)
    // ---------------------------
    $('#sab-forgot-form').on('submit', async function (e) {
        e.preventDefault();
        const email = $('#sab-forgot-email').val();
        const $btn = $('#sab-forgot-submit');
        const $msg = $('#sab-forgot-message');

        $btn.prop('disabled', true).text('送信中...');
        $msg.text('');

        // 設定画面で決めたURLへリダイレクトさせる
        const { error } = await supabase.auth.resetPasswordForEmail(email, {
            redirectTo: sab_vars.reset_redirect_to
        });

        if (error) {
            $msg.css('color', 'red').text('エラー: ' + error.message);
            $btn.prop('disabled', false).text('送信する');
        } else {
            $msg.css('color', 'green').text('再設定メールを送信しました。メール内のリンクをクリックしてください。');
            $btn.text('送信完了');
        }
    });

    // ---------------------------
    // 6. パスワード更新 (Update Password)
    // ---------------------------
    $('#sab-update-password-form').on('submit', async function (e) {
        e.preventDefault();
        const newPassword = $('#sab-new-password').val();
        const $btn = $('#sab-update-submit');
        const $msg = $('#sab-update-message');

        $btn.prop('disabled', true).text('更新中...');
        $msg.text('');

        const { error } = await supabase.auth.updateUser({ password: newPassword });

        if (error) {
            $msg.css('color', 'red').text('エラー: ' + error.message);
            $btn.prop('disabled', false).text('変更する');
        } else {
            $msg.css('color', 'green').text('パスワードを変更しました！トップページへ移動します。');
            // 少し待ってからトップへ、または自動ログイン
            setTimeout(function () {
                window.location.href = sab_vars.redirect_url;
            }, 1000);
        }
    });

    // ---------------------------
    // 7. 認証状態の監視 & 自動同期
    // ---------------------------
    supabase.auth.onAuthStateChange(async (event, session) => {
        // パスワードリセットのリンクを踏んで戻ってきた時 (PASSWORD_RECOVERY)
        if (event === 'PASSWORD_RECOVERY') {
            console.log('パスワードリセットモード');
            // ここで何かUIを表示してもいいが、基本は [supabase_update_password] のフォームに入力させる
        }

        // ログイン成功時 (SIGNED_IN)
        if (event === 'SIGNED_IN' && session) {
            if (sab_vars.is_logged_in == '1') return; // 既にWPログイン済みなら無視

            console.log('Supabaseログイン検知。同期します...');

            // メッセージ表示先を探す
            let $msg = $('#sab-message');
            if (!$msg.length) $msg = $('#sab-reg-message');
            if (!$msg.length) $msg = $('#sab-update-message'); // リセット画面かも

            if ($msg.length) $msg.text('ログイン連携中...');

            syncWithWordPress(session.access_token, $msg);
        }
    });

    // ---------------------------
    // 共通関数: WPへトークン送信
    // ---------------------------
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
                    if ($msgElement && $msgElement.length) {
                        $msgElement.css('color', 'green').text('成功！移動します...');
                    }
                    setTimeout(function () {
                        window.location.href = sab_vars.redirect_url; // 設定画面のURLへ
                    }, 500);
                }
            },
            error: function (xhr) {
                console.error(xhr);
                if ($msgElement && $msgElement.length) {
                    $msgElement.css('color', 'red').text('連携エラーが発生しました。');
                }
            }
        });
    }
});
