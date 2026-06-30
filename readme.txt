=== All-in-one Download Gemini Tracker ===
Contributors: tcacamou
Tags: torrent, download, gemini-tracker, all-in-one-download
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 0.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add-on for All-in-one Download that allows downloading torrents from Gemini Tracker.

== Description ==

All-in-one Download Gemini Tracker is an add-on for the All-in-one Download plugin. It integrates with the Gemini Tracker API to automatically search and download `.torrent` files for movies and TV shows.

Features:

* Automatically search for torrents matching a movie or TV show title via the Gemini Tracker API.
* Support for season and episode filtering for TV shows.
* Language filtering (French audio: VFF, TRUEFRENCH, FRENCH).
* Downloads the best available torrent (sorted by seeders) and stores it in the WordPress uploads directory.
* Settings page in the WordPress admin to store your Gemini Tracker API credentials.
* Auto-updates via GitHub releases.

== Requirements ==

* WordPress 5.0 or higher
* PHP 8.0 or higher
* All-in-one Download plugin (main plugin)
* A valid Gemini Tracker API key (https://gemini-tracker.org)

== Installation ==

1. Upload the `all-in-one-download-gemini-tracker` folder to the `/wp-content/plugins/` directory.
2. Run `composer install` inside the plugin folder to install dependencies.
3. Activate the plugin through the "Plugins" menu in WordPress.
4. Navigate to **All-in-one Download > Gemini Tracker** in the WordPress admin.
5. Enter your Gemini Tracker API Key, then click **Save**.

== Configuration ==

After activation, go to **All-in-one Download > Gemini Tracker** and fill in:

* **Gemini Tracker API Key** — your personal API key from Gemini Tracker.

This credential is stored securely as a WordPress option and is used to authenticate requests to the Gemini Tracker API.

== Frequently Asked Questions ==

= Where do I get my Gemini Tracker API Key? =

Log in to your account on https://gemini-tracker.org and navigate to your profile settings to retrieve your API key.

= Where are the downloaded torrent files stored? =

Torrent files are saved to `wp-content/uploads/gemini-tracker/`.

= Does this plugin work independently? =

No. This plugin is an add-on and requires the All-in-one Download plugin to be installed and active.

== Changelog ==

= 0.0.1 =
* Initial release.
* Movie and TV show torrent search via Gemini Tracker API.
* Admin settings page for API credentials.
* Auto-update support via GitHub.

== Upgrade Notice ==

= 0.0.1 =
Initial release.
