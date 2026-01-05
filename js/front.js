jQuery(document).ready(function ($) {
    // 設定チェック
    if (!window.sab_vars || !sab_vars.supabase_url || !sab_vars.supabase_key) {
        console.error('Supabase settings missing');
        return;
    }

    const { createClient } = window.supabase;
    const supabase = createClient(sab_vars.supabase_url, sab_vars.supabase_key);
    const i18n = sab_vars.i18n;

    // ★修正: ハードコードを削除し、i18n変数を使用
    const msgWeak = i18n.weak_password;
    const msgGood = i18n.strong_password;

    let isProcessingRegistration = false;

    // ---------------------------
    // 【追加】ログイン成功メッセージ表示処理
    // ---------------------------
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('sab_logged_in') === '1') {
        const newUrl = window.location.href.replace(/[\?&]sab_logged_in=1/, '');
        window.history.replaceState({}, document.title, newUrl);

        // ★修正: ハードコードを削除し、i18n変数を使用
        const $toast = $('<div id="sab-login-toast">' + i18n.logged_in + '</div>');
        $('body').append($toast);

        $toast.fadeIn(500, function () {
            setTimeout(function () {
                $toast.fadeOut(1000, function () {
                    $(this).remove();
                });
            }, 2500);
        });
    }

    // ---------------------------
    // 【追加】強制ログアウト処理
    // ---------------------------
    if (sab_vars.trigger_logout === '1') {
        console.log('WP Logout detected -> Signing out from Supabase...');
        supabase.auth.signOut().then(() => {
            document.cookie = 'sab_force_logout=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
        });
    }

    function showMsg(elem, text, isError = false) {
        $(elem)
            .show()
            .text(text)
            .removeClass('sab-success sab-error')
            .addClass(isError ? 'sab-error' : 'sab-success');
    }

    // ---------------------------
    // パスワード表示切り替え (Eye Icon)
    // ---------------------------
    $(document).on('click', '.sab-password-toggle', function () {
        const $btn = $(this);
        const $input = $btn.siblings('input');
        const currentType = $input.attr('type');

        if (currentType === 'password') {
            $input.attr('type', 'text');
            $btn.html(
                '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>'
            );
        } else {
            $input.attr('type', 'password');
            $btn.html(
                '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>'
            );
        }
    });

    // ---------------------------
    // パスワード強度チェック
    // ---------------------------
    $('.sab-password-check').on('input', function () {
        const password = $(this).val();
        const $wrapper = $(this).closest('.sab-password-wrapper');
        const $meter = $wrapper.next('.sab-strength-meter');
        const $submitBtn = $wrapper.closest('form').find('button[type="submit"]');

        if (password.length === 0) {
            $meter.hide();
            $submitBtn.prop('disabled', false);
            return;
        }

        // 強度判定: 8文字以上 + 大文字 + 小文字 + 数字
        const hasLength = password.length >= 8;
        const hasUpper = /[A-Z]/.test(password);
        const hasLower = /[a-z]/.test(password);
        const hasNumber = /[0-9]/.test(password);

        // すべての条件を満たしているか
        const isStrongEnough = hasLength && hasUpper && hasLower && hasNumber;

        $meter.show();
        if (isStrongEnough) {
            $meter.text(msgGood).removeClass('sab-strength-weak').addClass('sab-strength-good');
            $submitBtn.prop('disabled', false);
        } else {
            $meter.text(msgWeak).removeClass('sab-strength-good').addClass('sab-strength-weak');
            $submitBtn.prop('disabled', true);
        }
    });

    // ---------------------------
    // 1. 通常ログイン
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
            // 手動ログインなので第3引数はfalse
            // 第4引数は個別のリダイレクト設定（あれば）
            const customRedirect = $('#sab-login-container').data('redirect-to');
            syncWithWordPress(data.session.access_token, $msg, false, customRedirect);
        }
    });

    // ---------------------------
    // 2. マジックリンク
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
            isProcessingRegistration = false;
        } else if (data.session) {
            showMsg($msg, i18n.reg_success, false);
            const customRedirect = $('#sab-register-container').data('redirect-to');
            syncWithWordPress(data.session.access_token, $msg, false, customRedirect);
        } else {
            showMsg($msg, i18n.check_email, false);
            $btn.text(i18n.check_email_btn);
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
    // 5. ログアウト
    // ---------------------------
    $('#sab-logout-button').on('click', async function (e) {
        e.preventDefault();
        $(this).text(i18n.logging_out);
        await supabase.auth.signOut();
        window.location.href = sab_vars.logout_url;
    });

    // ---------------------------
    // 6. パスワードリセット申請
    // ---------------------------
    $('#sab-forgot-form').on('submit', async function (e) {
        e.preventDefault();
        const email = $('#sab-forgot-email').val();
        const $btn = $('#sab-forgot-submit');
        const $msg = $('#sab-forgot-message');

        $btn.prop('disabled', true).text(i18n.checking);
        $msg.hide();

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
                    showMsg($msg, i18n.google_account_alert, false);
                    $btn.prop('disabled', false).text(i18n.send_btn);
                } else {
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
            if (isProcessingRegistration) return;

            console.log('Supabase Login Detected -> Auto Syncing...');

            let customRedirect = $('#sab-login-container').data('redirect-to');
            if (!customRedirect) {
                customRedirect = $('#sab-register-container').data('redirect-to');
            }

            // 第3引数 isAutoSync=true (エラー時にログアウト)
            syncWithWordPress(session.access_token, null, true, customRedirect);
        }
    });

    function syncWithWordPress(accessToken, $msgElement, isAutoSync = false, customRedirectUrl = '') {
        if (!isAutoSync && $msgElement && $msgElement.length) {
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
                    if (!isAutoSync && $msgElement && $msgElement.length) {
                        showMsg($msgElement, i18n.success_redirect, false);
                    }
                    setTimeout(function () {
                        let redirectUrl = customRedirectUrl ? customRedirectUrl : sab_vars.redirect_url;
                        redirectUrl += (redirectUrl.indexOf('?') === -1 ? '?' : '&') + 'sab_logged_in=1';
                        window.location.href = redirectUrl;
                    }, 500);
                }
            },
            error: function (xhr) {
                console.error('Sync Error Details:', xhr);

                if (isAutoSync) {
                    console.warn('Auto-sync failed. Signing out from Supabase to clean up state.');
                    supabase.auth.signOut();
                    return;
                }

                let errMsg = i18n.sync_error;
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errMsg += ' (' + xhr.responseJSON.message + ')';
                } else if (xhr.status === 403) {
                    errMsg += ' (403 Forbidden: WAF or Nonce Error)';
                } else {
                    errMsg += ' (' + xhr.status + ' ' + xhr.statusText + ')';
                }

                if ($msgElement && $msgElement.length) showMsg($msgElement, errMsg, true);
            }
        });
    }
});
