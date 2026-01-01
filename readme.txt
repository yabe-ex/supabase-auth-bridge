=== Supabase Auth Bridge for WordPress ===
Contributors: edelhearts
Tags: supabase, authentication, login, membership, google-login
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect your WordPress site to Supabase Authentication. Securely manage members with Supabase while keeping WordPress admins separate.

== Description ==

This plugin integrates Supabase Authentication into your WordPress site, providing a secure and scalable membership system. It allows you to separate "Site Administrators" (WordPress native auth) from "General Users" (Supabase auth).

It is designed to solve the complexity of hybrid user management.

### Key Features

* **Supabase Authentication:** Users can sign up and log in using Supabase (Email/Password or Social Providers like Google).
* **Smart Password Reset Flow:**
    * Automatically detects user type (Supabase User vs. WordPress Admin).
    * **Google Account Support:** If a user registered via Google tries to reset their password, the system intelligently skips sending an email and guides them to use the "Log in with Google" button instead.
    * **Security:** Prevents User Enumeration attacks by unifying success messages regardless of registration status.
* **Shortcodes:** Easily place Login, Registration, and Password Reset forms anywhere using shortcodes.
* **Admin/User Separation:** Keeps your `wp-admin` secure by using standard WordPress login for administrators, while frontend users authenticate via Supabase.

### Usage

1.  Create a Supabase project and get your URL and Anon Key.
2.  Install this plugin and enter your Supabase credentials in the settings page.
3.  Create "Login", "Register", and "Password Reset" pages in WordPress.
4.  Paste the provided shortcodes into those pages.

== Installation ==

1.  Upload the plugin files to the `/wp-content/plugins/supabase-auth-bridge` directory, or install the plugin through the WordPress plugins screen directly.
2.  Activate the plugin through the 'Plugins' screen in WordPress.
3.  Go to the "Supabase Auth" settings page and configure your Supabase URL and Public Key.
4.  Use the shortcodes `[supabase_login]`, `[supabase_register]`, and `[supabase_reset_password]` in your pages.

== Frequently Asked Questions ==

= Does this plugin sync users to the WordPress database? =
No. To keep the database clean and secure, general users exist only in Supabase. They are authenticated via session on the frontend. (Or, if your plugin creates a temporary WP user, update this answer accordingly).

= What happens if a Google-registered user tries to reset their password? =
The plugin detects that the email is associated with a Google provider in Supabase. For security and usability, it will NOT send a reset email. Instead, the user will see a notification advising them to log in via the Google button.

= Can I use both WordPress Admins and Supabase Users? =
Yes. Administrators should continue using the default `/wp-login.php` to access the dashboard. General users will use the frontend forms provided by this plugin.

== Screenshots ==

1.  Login Form (Frontend)
2.  Password Reset Request Flow
3.  Settings Screen

== Changelog ==

= 1.0.0 =
* Initial release.
* Added Supabase authentication support.
* Implemented smart password reset logic with Google account detection.
* Added shortcode support.

== Upgrade Notice ==

= 1.0.0 =
This is the first version of the plugin.