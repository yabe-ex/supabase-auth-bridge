=== Supabase Auth Bridge ===
Contributors: edelhearts
Tags: supabase, authentication, login, membership, google-login, passwordless
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect your WordPress site to Supabase Authentication.
Securely manage members with Supabase while keeping WordPress admins separate.

== Description ==

This plugin integrates **Supabase Authentication** into your WordPress site, providing a secure, scalable, and modern membership system.
It allows you to completely separate "Site Administrators" (who use WordPress native auth) from "General Users" (who use Supabase auth).
This ensures your `wp-admin` remains secure while offering a seamless login experience for your customers.

### Key Features

* **Supabase Authentication:** Support for Email/Password, Magic Links (Passwordless), and Social Login (Google).
* **Auto Synchronization:** Users created in Supabase are automatically synced to WordPress as subscribers upon login.
* **Logout Synchronization:** Logging out of WordPress automatically triggers a sign-out from Supabase to ensure session consistency.
* **User Deletion Sync:** Deleting a user in WordPress automatically removes the corresponding user from Supabase (Requires Service Role Key).
* **Secure Admin Separation:** Administrators are blocked from logging in via the frontend forms to prevent privilege escalation attacks.
* **Smart Password Reset:** Automatically detects if a user registered via Google and guides them to use the "Log in with Google" button instead of sending a reset email.
* **Welcome Emails:** Sends customizable "Welcome" emails directly from WordPress upon successful registration. Custom Sender Name and Email are supported.
* **Keep Alive (Maintenance):** Automatically accesses Supabase once a day to prevent free projects from pausing due to inactivity.
* **Developer Friendly:** Includes hooks for customizing user roles and syncing additional metadata.

### Why use this plugin?
Unlike other plugins that sync the entire database, **Supabase Auth Bridge** authenticates users via the Supabase API on the frontend and only creates a WordPress user session when necessary.
This keeps your WordPress database clean and your site fast.

== Installation ==

1.  Upload the plugin files to the `/wp-content/plugins/supabase-auth-bridge` directory, or install the plugin through the WordPress plugins screen directly.
2.  Activate the plugin through the 'Plugins' screen in WordPress.
3.  Go to **Settings > Supabase Auth Bridge**.
4.  **Important:** If you use a caching plugin (like WP Rocket, W3 Total Cache), please **exclude the login and registration pages from caching**.
Otherwise, the security nonce may expire, preventing users from logging in.

### Supabase Setup (Required)

1.  Log in to your [Supabase Dashboard](https://supabase.com/dashboard).
2.  Go to **Project Settings > API**.
3.  Copy the **Project URL**, **anon public key**, and **service_role secret**.
4.  Paste these credentials into the plugin settings page in WordPress.
* *Note: The `service_role` key is stored securely and used for administrative tasks like checking Google users, syncing user deletions, or the "Keep Alive" feature.*

### Email Setup (Optional)

You can configure the plugin to send a "Welcome Email" from WordPress upon new user registration.
Go to **Settings > Supabase Auth Bridge** and configure the **Welcome Email Settings** section.
You can specify the Sender Name, Sender Email, Subject, and Body content.

### Maintenance Settings (Optional)

If you are using Supabase's Free Plan, projects may be paused after 7 days of inactivity.
Enable **"Keep Alive"** in the settings to have WordPress automatically access your Supabase project once a day, preventing it from pausing.

== Frequently Asked Questions ==

= How do I remove the "Powered by Supabase" text from Supabase emails? =
This text is automatically added by Supabase if you are using their built-in email service.
To remove it, you must configure **Custom SMTP** in your Supabase Project Settings.
Once configured, you can edit the Email Templates to remove the footer.

= Does this plugin sync users to the WordPress database? =
Yes, but efficiently.
A WordPress user record is created (or updated) only when a user successfully logs in via Supabase.
This ensures that users exist in WordPress for compatibility with other plugins (like WooCommerce or membership plugins), but authentication is handled by Supabase.

= Does logging out of WordPress log me out of Supabase? =
Yes. The plugin detects the WordPress logout action and triggers a sign-out request to Supabase on the frontend, ensuring both sessions are terminated.

= What happens if I delete a user from WordPress? =
If you have configured the **Service Role Key** in the settings, deleting a user from the WordPress admin screen will also delete the corresponding user from your Supabase project.

= What happens if a Google-registered user tries to reset their password? =
The plugin's "Smart Check" feature detects that the email is associated with a Google provider.
Instead of sending a reset email (which wouldn't work), it displays a helpful message advising the user to log in with Google.

= Can Administrators log in via the Supabase form? =
No. For security reasons, users with `administrator` privileges are blocked from logging in via the frontend Supabase forms.
Admins should continue using the default `/wp-login.php` or `/wp-admin`.

= Where can I find the Redirect URL for Google Login? =
If you use Google Login, you need to add your site's URL to the **Redirect URLs** in Supabase (Authentication > URL Configuration).
Usually, this is just your site's home URL (e.g., `https://example.com`).

== For Developers ==

You can customize the plugin behavior using the following hooks in your theme's `functions.php`:

**1. Change User Role based on Provider**
Example: Assign the `contributor` role to users who register via Google, while keeping Email users as `subscriber`.

`add_filter('supabase_auth_bridge_user_role', function($role, $user_data) {
    // Check the provider (e.g., 'google', 'email')
    $provider = isset($user_data->app_metadata->provider) ? $user_data->app_metadata->provider : '';

    if ($provider === 'google') {
        return 'contributor';
    }
    return $role;
}, 10, 2);`

**2. Sync Display Name**
Example: Sync the "Display name" (checking `display_name`, `full_name`, or `name` in `user_metadata`) from Supabase to WordPress `display_name`.

`add_action('supabase_auth_bridge_after_user_sync', function($user_id, $user_data, $is_new_user) {
    $meta = isset($user_data->user_metadata) ? $user_data->user_metadata : null;
    if (!$meta) return;

    // Check common keys for Display Name
    $name = '';
    if (isset($meta->display_name)) $name = $meta->display_name;
    elseif (isset($meta->full_name)) $name = $meta->full_name;
    elseif (isset($meta->name)) $name = $meta->name;

    if ($name) {
        wp_update_user(array('ID' => $user_id, 'display_name' => $name));
    }
}, 10, 3);`

**3. Record Last Login Time**
Example: Save the timestamp of the last successful Supabase login to user meta.

`add_action('supabase_auth_bridge_after_user_sync', function($user_id, $user_data, $is_new_user) {
    update_user_meta($user_id, 'last_supabase_login', current_time('mysql'));
}, 10, 3);`

== Screenshots ==

1.  **Frontend Login Form:** Supports Email, Magic Link, and Google Login.
2.  **Settings Screen:** Easy configuration for Supabase API keys and Design.
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
* Added Logout Synchronization between WordPress and Supabase.
* Added User Deletion Sync (WP to Supabase).
* Added security protection for Administrator accounts.
* Added Welcome Email settings in WordPress (including Sender Name/Email).
* Added "Keep Alive" feature to prevent Supabase free projects from pausing.
* Added "Login Success" toast notification on the frontend.
* Added developer hooks for role customization and metadata syncing.