=== Social Relay for Postiz ===
Contributors: dcarrero
Tags: postiz, social media, auto post, schedule, self-hosted
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 0.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Auto-publish and schedule your WordPress content on social networks through your own Postiz instance, self-hosted or Postiz Cloud.

== Description ==

Social Relay for Postiz connects WordPress directly to the public API of **your** Postiz instance. No middleman service, no extra account: enter your Postiz URL and API key, and WordPress sends your posts to the channels you already connected in Postiz (X, LinkedIn, Bluesky, Mastodon, Threads, Instagram, Facebook, Pinterest, Telegram and many more).

Works with self-hosted Postiz and with Postiz Cloud.

= Publish your way =

* **One publishing profile per post type**: channels, message template and timing.
* **Publish now, schedule with a delay, use the next free slot in Postiz, or create drafts in Postiz** to review them there.
* **Templates with tags** such as `{title}`, `{excerpt}`, `{url}`, `{first_category}`, `{hashtags:post_tag}`, `{meta:key}` or `{seo_description}`, filters like `|limit:200`, `|words:30` or `|default:text`, and thread parts separated by `---`. An "Insert tag" menu lists them all.
* **A template per network** when one message does not fit all, with each network's character limit applied.
* **Network settings**: who can reply on X, LinkedIn carousels, Instagram post or story, Pinterest board, Google Business button.
* **Conditions**: share only posts in some categories or tags, never posts in others.
* **UTM parameters per network**, Postiz link shortener and Postiz tags from your categories.
* **Images**: the featured image, the images in the post or the ones you pick, up to what each network accepts (4 on X, 10 on Instagram…).
* **Export and import profiles** to copy your setup to another site.

= In the editor =

* A **"Social networks" panel** in the block editor: turn sharing on or off, pick channels for this post, write a text for all networks or one per network (one tab each), and preview every message as it will look on each network, with its character count.
* **Suggest with AI**: a message for all networks or for one, written by the AI provider you set up in Settings → Connectors (WordPress 7). It only fills the field: you review it before publishing.
* **When, per post**: now, on a date, the next free slot or a draft in Postiz, or keep the profile setting.
* **Images, per post**: keep the profile setting, pick images in the media library or share without images.
* A **pre-publish summary** of the channels and timing that will be used.
* After publishing, the **status of each network**: queued, published (with a link to the post) or the error reported by the platform.
* **Share again now, retry a failed network, or cancel what is still pending** in Postiz, right from the editor.
* A metabox for the **classic editor**, with the same timing options.
* A **"Social networks" column** in the posts list with the status of each channel, and a filter for posts with errors or pending shares.

= Reliable =

* **One request per post for every channel**, to respect the Postiz API rate limit.
* **Background sending** with Action Scheduler, automatic retries and duplicate protection.
* **Failure alerts**: a notice in the dashboard and, if you turn it on, an email summary at most once an hour.
* **Status sync** with Postiz, triggered by the Postiz webhook or every 15 minutes.
* Pending posts are **cancelled in Postiz** when a post is unpublished or trashed.
* A **"Test this profile"** panel: pick a post and see what the profile would send, without sending anything.
* An **activity log** with search, filters and summary cards (published, queued, errors, requests to Postiz in the last hour), and a dashboard widget.

= For developers and AI agents =

* **WordPress 7 Connectors**: Postiz appears in Settings → Connectors. The API key can come from an environment variable, a constant or the database.
* **WP-CLI**: `wp social-relay status|test|channels|preview|share|shares|cancel|sync|log`.
* **Abilities API**: preview, share and check social posts from AI agents through the MCP Adapter.
* **Hooks** to change the message, the request sent to Postiz, the post types and more. See the [developer documentation](https://github.com/dcarrero/social-relay-for-postiz).

Social Relay for Postiz is a product by [Color Vivo](https://colorvivo.com). It is not affiliated with or endorsed by Postiz.

== Installation ==

1. Install and activate the plugin.
2. In Postiz, open Settings → Developers → Public API and copy your API key.
3. In WordPress, go to Social Relay → Settings → Connection, enter your Postiz URL and the API key, and click "Save and test connection".
4. In "Publishing profiles", choose the channels, template and timing for each post type.
5. Optional: in "Webhook", copy the URL into Postiz → Settings → Webhooks to see each post's status as soon as it is published.

To keep the key out of the database, add this to wp-config.php instead:

`define( 'SOCIALRELAYCVI_POSTIZ_API_KEY', 'your-key' );`

If your Postiz instance runs on the same server or on a private network, also add:

`define( 'SOCIALRELAYCVI_ALLOW_LOCAL_INSTANCE', true );`

== Frequently Asked Questions ==

= Do I need a Postiz account? =

Yes. You need a Postiz instance: your own self-hosted Postiz or a Postiz Cloud account. The social networks are connected in Postiz, not in WordPress.

= Does it work with Postiz Cloud? =

Yes. Use https://api.postiz.com as the URL.

= Which social networks are supported? =

Every channel you connect in Postiz. The plugin knows the character limits and settings of the most common ones (X, LinkedIn, Bluesky, Mastodon, Threads, Instagram, Facebook, Pinterest, TikTok, Google Business, Telegram, Discord…).

= Can I write a different text for each network? =

Yes. Each publishing profile has a common template and an optional template per network, and each post can override both from the editor panel.

= Which tags can I use in the message? =

Post: `{title}`, `{excerpt}`, `{content}`, `{url}`, `{shortlink}`, `{slug}`, `{author}`, `{date}`, `{reading_time}` (minutes). Terms: `{categories}`, `{first_category}` (the Yoast SEO or Rank Math primary category), `{tags}`, `{term:taxonomy}` (public taxonomies), `{hashtags:post_tag}`. Site and SEO: `{site_name}`, `{seo_title}`, `{seo_description}`. Custom fields: `{meta:key}`.

Filters go inside a tag and can be chained: `|limit:180` (characters), `|words:30`, `|upper`, `|lower`, `|strip`, `|hashtag`, `|default:text` (when the value is empty). For example `{excerpt|words:30}` or `{categories|hashtag}`. A line with only `---` starts a new part of a thread.

= How do I set up the Postiz webhook? =

In Social Relay → Settings → Webhook, copy the URL. In Postiz open Settings → Webhooks, add a webhook, paste the URL, choose the channels and press "Send test": the call appears in the "Last calls" list. The webhook only makes statuses update at once; without it they update every 15 minutes. The URL contains a secret, and the plugin never trusts what the call says: it asks the Postiz API for the real status. "Check URL" tells you if a security plugin or a firewall blocks it.

= Will my posts be shared twice if I update them? =

No. Updates are not shared again unless you allow it in the profile and ask for it in the editor. The plugin also skips content that was already sent.

= What happens if Postiz is down? =

The share is retried automatically up to three times. Errors appear in the editor panel, in the log and in the dashboard widget.

= Is the API key safe? =

The key is never sent to the browser and never written to the log. For extra safety, define it in wp-config.php or as an environment variable.

= Where do I see what was sent? =

In the editor panel of each post, in Social Relay → Log, and in the dashboard widget.

== Screenshots ==

1. The "Social networks" panel in the block editor: channels for this post and, in one tab per channel, its own text.
2. Preview drawn like each network: character count against the limit, X thread, link card and per-network text.
3. Pre-publish summary of the channels and timing.
4. Publishing profiles: channels, templates and timing for each post type, with a panel to test the profile on a real post.
5. Activity log with summary cards (published, queued, errors, requests to Postiz in the last hour), search and filters.

== External services ==

This plugin sends data to the Postiz instance whose URL you configure: your own server, or Postiz Cloud (api.postiz.com) if you choose it. It is needed to publish your posts on the social networks you connected in Postiz.

When a post is shared, the plugin sends the generated message, the post URL, the URLs of the chosen images (Postiz downloads them from your site) and the selected channel ids. It also reads the list of channels and the status of the posts it created. If you add the webhook in Postiz, Postiz calls your site when a post goes out, with that post's id, status and public link. Nothing is sent until you configure the connection and enable a publishing profile.

Postiz Cloud terms of service: https://postiz.com/terms
Postiz Cloud privacy policy: https://postiz.com/privacy

"Suggest with AI" only works when you click it, and only if you set up an AI provider in Settings → Connectors (WordPress 7). It then sends the post title, excerpt, categories and text (up to 4,000 characters) to that provider through the WordPress AI Client. The provider and its terms are the ones you chose; the plugin does not add any of its own.

== Privacy ==

The plugin does not track visitors and does not set cookies. A suggested text for your privacy policy is available in Settings → Privacy → Policy Guide.

== Source code ==

The editor panel and the admin screens are built from the JavaScript sources in `assets/src/` with @wordpress/scripts. Full source code and build instructions: https://github.com/dcarrero/social-relay-for-postiz

== Changelog ==

= 0.3.1 =
* Editor panel rebuilt with JSX and @wordpress/scripts.
* {slug} works for drafts; long URLs wrap in the preview.

= 0.3.0 =
* Block editor panel, pre-publish summary and classic editor metabox.
* Status sync with Postiz and webhook receiver.
* Dashboard widget.

= 0.2.0 =
* Per-network templates and settings, term conditions, UTM parameters, Postiz shortener and tags.
* Cancels pending Postiz posts when a post is unpublished.

= 0.1.0 =
* First development version.
