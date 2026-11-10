=== Social Relay for Postiz ===
Contributors: dcarrero
Tags: postiz, social media, auto post, schedule, self-hosted
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Auto-publish and schedule your WordPress content on social networks through your own Postiz instance, self-hosted or Postiz Cloud.

== Description ==

Social Relay for Postiz connects WordPress directly to the public API of **your** Postiz instance. No middleman service, no extra account: you enter your Postiz URL and API key, and WordPress sends your posts to the channels you already connected in Postiz (X, LinkedIn, Bluesky, Mastodon, Threads, Instagram, Facebook and many more).

Works with self-hosted Postiz and with Postiz Cloud.

**In this early version**

* Connection to any Postiz instance, with automatic detection of the API path.
* Postiz appears in Settings → Connectors (WordPress 7). The API key can come from an environment variable, a constant or the database.
* One publishing profile per post type: channels, message template and timing.
* Message templates with tags such as {title}, {excerpt}, {url}, {hashtags:post_tag} or {meta:key}, filters like |limit:200, and thread parts separated by ---.
* Per-network templates and settings (who can reply on X, LinkedIn carousels, Instagram post or story, Pinterest board…), with character limits.
* Conditions: share only posts in some categories or tags, never posts in others.
* UTM parameters per network, Postiz link shortener and Postiz tags from your categories.
* Pending posts are cancelled in Postiz when a post is unpublished or trashed.
* Publish now, schedule with a delay, use the next free slot in Postiz, or create drafts in Postiz.
* Featured image upload.
* One request per post for every channel, to respect the Postiz API rate limit.
* Background sending with Action Scheduler, automatic retries and an activity log.

This plugin is not affiliated with or endorsed by Postiz.

== Installation ==

1. Install and activate the plugin.
2. In Postiz, open Settings → Developers → Public API and copy your API key.
3. In WordPress, go to Social Relay → Settings, enter your Postiz URL and the API key, and save.
4. Choose the channels and template for each post type.

To keep the key out of the database, add this to wp-config.php instead:

`define( 'SOCIALRELAYCVI_POSTIZ_API_KEY', 'your-key' );`

If your Postiz instance runs on the same server or on a private network, also add:

`define( 'SOCIALRELAYCVI_ALLOW_LOCAL_INSTANCE', true );`

== Frequently Asked Questions ==

= Does it work with Postiz Cloud? =

Yes. Use https://api.postiz.com as the URL.

= Where do I see what was sent? =

In Social Relay → Log.

== External services ==

This plugin sends data to the Postiz instance whose URL you configure (your own server, or Postiz Cloud at api.postiz.com if you choose it). When a post is shared, it sends the generated message, the post URL, the featured image URL and the selected channel ids. Nothing is sent until you configure the connection and enable a publishing profile.

Postiz Cloud terms: https://postiz.com/terms
Postiz Cloud privacy policy: https://postiz.com/privacy

== Changelog ==

= 0.2.0 =
* Per-network templates and settings, term conditions, UTM parameters, Postiz shortener and tags.
* Cancels pending Postiz posts when a post is unpublished.
* REST routes for a post's shares and a dry-run preview.
* Spanish (es_ES) translation.

= 0.1.0 =
* First development version.
