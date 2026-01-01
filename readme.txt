=== Supabase Auth Bridge ===
Contributors: edelhearts
Tags: supabase, authentication, login, membership, google-login, passwordless
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect your WordPress site to Supabase Authentication. Securely manage members with Supabase while keeping WordPress admins separate.

== Description ==

This plugin integrates **Supabase Authentication** into your WordPress site, providing a secure, scalable, and modern membership system.

It allows you to completely separate "Site Administrators" (who use WordPress native auth) from "General Users" (who use Supabase auth). This ensures your `wp-admin` remains secure while offering a seamless login experience for your customers.

### Key Features

* **Supabase Authentication:** Support for Email/Password, Magic Links (Passwordless), and Social Login (Google).
* **Auto Synchronization:** Users created in Supabase are automatically synced to WordPress as subscribers upon login.
* **Secure Admin Separation:** Administrators are blocked from logging in via the frontend forms to prevent privilege escalation attacks.
* **Smart Password Reset:** Automatically detects if a user registered via Google and guides them to use the "Log in with Google" button instead of sending a reset email.
* **High Performance:** Designed to handle thousands of users without performance degradation.
* **Customizable Emails:** Supports sending "Welcome" emails from WordPress upon registration.

### Why use this plugin?

Unlike other plugins that sync the entire database, **Supabase Auth Bridge** authenticates users via the Supabase API on the frontend and only creates a WordPress user session when necessary. This keeps your WordPress database clean and your site fast.

== Installation ==

1.  Upload the plugin files to the `/wp-content/plugins/supabase-auth-bridge` directory, or install the plugin through the WordPress plugins screen directly.
2.  Activate the plugin through the 'Plugins' screen in WordPress.
3.  Go to **Settings > Supabase Auth Bridge**.

### Supabase Setup (Required)

1.  Log in to your [Supabase Dashboard](https://supabase.com/dashboard).
2.  Go to **Project Settings > API**.
3.  Copy the **Project URL**, **anon public key**, and **service_role secret**.
4.  Paste these credentials into the plugin settings page in WordPress.
    * *Note: The `service_role` key is stored securely and used only for administrative tasks (like checking Google users or deleting users).*

### Email Setup (Recommended)

To remove the "Powered by Supabase" footer and use your own sender name:
1.  In Supabase, go to **Project Settings > Authentication > SMTP Settings**.
2.  Enable **Custom SMTP** and enter your SMTP credentials (e.g., SendGrid, Amazon SES, or your hosting provider's SMTP).
3.  Go to **Authentication > Email Templates** and remove the footer from the templates.

== Frequently Asked Questions ==

= How do I remove the "Powered by Supabase" text from emails? =
This text is automatically added by Supabase if you are using their built-in email service. To remove it, you must configure **Custom SMTP** in your Supabase Project Settings. Once configured, you can edit the Email Templates to remove the footer.

= Does this plugin sync users to the WordPress database? =
Yes, but efficiently. A WordPress user record is created (or updated) only when a user successfully logs in via Supabase. This ensures that users exist in WordPress for compatibility with other plugins (like WooCommerce or membership plugins), but authentication is handled by Supabase.

= What happens if a Google-registered user tries to reset their password? =
The plugin's "Smart Check" feature detects that the email is associated with a Google provider. Instead of sending a reset email (which wouldn't work), it displays a helpful message advising the user to log in with Google.

= Can Administrators log in via the Supabase form? =
No. For security reasons, users with `administrator` privileges are blocked from logging in via the frontend Supabase forms. Admins should continue using the default `/wp-login.php` or `/wp-admin`.

= Where can I find the Redirect URL for Google Login? =
If you use Google Login, you need to add your site's URL to the **Redirect URLs** in Supabase (Authentication > URL Configuration). Usually, this is just your site's home URL (e.g., `https://example.com`).

== Screenshots ==

1.  **Frontend Login Form:** Supports Email, Magic Link, and Google Login.
2.  **Settings Screen:** Easy configuration for Supabase API keys.
3.  **Smart Password Reset:** User friendly notification for Google users.

== Shortcodes ==

Use these shortcodes to place forms on your pages:

* `[supabase_login]` - Displays the login form.
* `[supabase_register]` - Displays the registration form.
* `[supabase_forgot_password]` - Displays the password reset request form.
* `[supabase_update_password]` - Displays the new password entry form (for the reset flow).
* `[supabase_logout]` - Displays a logout button (only visible to logged-in users).

== Changelog ==

= 1.0.0 =
* Initial release.
* Added support for Email/Password, Magic Link, and Google Login.
* Implemented "Smart Check" for password resets.
* Added security protection for Administrator accounts.
* Added support for Custom SMTP email flow compatibility.