# Changelog

All notable changes to Social Relay for Postiz. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.0.0] - 2026-11-11

First public release: the changes below, on top of the development versions 0.1.0 to 0.3.1.

### Added
- Webhook tab: status of the Postiz webhook (last call, never received, or no calls for days), a step-by-step guide to set it up in Postiz, a "Check URL" button that calls the webhook from the site itself and explains a 404 (REST API off) or 401/403 (security plugin, Cloudflare, firewall), a warning when the site address is not reachable from the internet (localhost, .test, private IPs), and the last 20 calls with the shares each one named, their current status and the published link. The list refreshes every 10 seconds while the tab is open. The Connection tab says whether statuses update at once or every 15 minutes. Admin REST routes `GET socialrelaycvi/v1/admin/webhook` and `POST …/admin/webhook/check`.
- The webhook call is read as a hint: each call queues its own status sync, right away, for the shares whose Postiz ids it names (a narrower request to Postiz; the 15-minute sync is untouched, and a failed request falls back to a full sync). Statuses still come only from the Postiz API, since webhooks are not signed. Calls carrying the "Check URL" header are never counted as Postiz calls. The Webhook tab refreshes its list only while the window is visible and for 15 minutes.
- Template tags `{first_category}` (Yoast SEO or Rank Math primary category, else the first one) and `{reading_time}` (minutes at 200 words a minute), and filters `|words:N` and `|default:text`.
- "Insert tag" menu grouped by kind, with what each tag gives, in the editor panel and in every template of the settings (common and per network). It replaces the long list of tags under the settings template. The readme FAQ lists every tag and filter.
- Several images per post. The profile chooses the featured image, the featured image plus the images in the post (image, gallery, media & text and cover blocks, and classic `wp-image-N` images), or none; the editor panel has an "Images" section to keep the profile setting, pick images in the media library (up to 10, in order) or send none, and the classic metabox can turn images off. Each network gets as many as it accepts (X, Bluesky and Mastodon 4, LinkedIn 9, Instagram, Facebook, Threads and TikTok 10, Pinterest and Google Business 1), each image is uploaded to Postiz once, and the preview shows them as the network would. Post meta `_socialrelaycvi_images`; filter `socialrelaycvi_image_ids`.
- Export and import of publishing profiles as JSON (Settings → Advanced), to copy them to another site. Categories and tags travel by slug and are matched on the new site (missing ones are reported and left out); channels keep their Postiz ids, and the import says how many the instance does not have. Only the post types in the file are replaced. Admin REST routes `GET socialrelaycvi/v1/admin/profiles/export` and `POST …/admin/profiles/import`.
- Failure alerts: admins see a notice in the dashboard with the open failures (title, channel and error; a later successful retry closes them), links to the posts with errors and to the log filtered by errors, and "Hide until new errors" per user. Optional email summary (Settings → Advanced, off by default), at most once an hour, to the admin email or the `socialrelaycvi_error_email_recipient` filter. The log screen accepts `?level=error`.
- "Suggest with AI" in the editor message tabs (WordPress 7 AI Client): writes a message for all networks or for one, within the network limit and in the language of the post, with `{url}` where the link goes so UTM and short links still apply. It uses the provider set up in Settings → Connectors (the plugin has no AI keys), only fills the field for review, and has "Undo". Hidden when no provider is set up. REST route `POST socialrelaycvi/v1/posts/{id}/suggest`; filter `socialrelaycvi_ai_suggestion` to replace the generator.
- "Social networks" column in the posts list of every post type with an enabled profile: one badge per channel (its Postiz picture or the network initial) with a dot for the latest status; the tooltip names channel, network, status and error. A filter above the list shows posts with errors, or with shares queued or in draft.
- Timing per post ("When" in the editor panel and the classic metabox): now, a date, the next free slot or a draft in Postiz, or the profile setting. Stored in `_socialrelaycvi_mode` and `_socialrelaycvi_publish_at` (site time); a past date sends right away and no date uses the profile delay.
- Actions in the editor status panel: "Share again now" (with a confirmation that names the channels and timing), "Retry" on a failed network (only that channel is sent) and "Cancel pending" (deletes scheduled posts and drafts in Postiz). The panel polls every 5 s for a minute after each action.
- REST routes `POST socialrelaycvi/v1/posts/{id}/share` (optional `channels` to retry only some) and `POST socialrelaycvi/v1/posts/{id}/cancel`, for users who can edit the post.
- WP-CLI: `wp social-relay status|test|channels|preview|share|shares|cancel|sync|log`.
- Abilities API (WordPress 6.9+): `socialrelaycvi/get-status`, `list-channels`, `preview-post`, `share-post`, `get-post-shares` and `cancel-post-shares`, in the `social-media` category, with input/output schemas, behaviour annotations and capability checks. Exposed in REST for AI agents (MCP Adapter) and other tools.
- Admin REST routes (`socialrelaycvi/v1/admin/settings|connection|profiles|stats|advanced|webhook/regenerate|log`), `manage_options` only; the API key is write-only.
- Editor panel: one tab per selected channel for its own text, next to the "All" tab; a dot marks channels with custom text.
- Editor preview drawn like each network: avatar in the network colour, character count against the limit, thread replies and the link card.
- "Test this profile" panel in the publishing profiles tab: pick a post and see the messages the saved profile would send, without sending anything.
- Log: cards with published and failed shares (30 days), queued shares and requests to Postiz in the last hour against the 90/hour limit.
- "Advanced" settings tab: delete all plugin data when the plugin is deleted (also forced by `SOCIALRELAYCVI_DELETE_DATA`).
- Suggested privacy policy text (Settings → Privacy → Policy Guide).
- wordpress.org assets in `.wordpress-org/`: icon, banner and five screenshots (taken with demo data against a local Postiz test double), plus a Live Preview blueprint.
- `bin/build-release.sh`: builds the installable zip (production Composer dependencies, compiled JavaScript, `.distignore` applied).
- Release workflow: on a `v*` tag, builds the zip, attaches it to a GitHub release and deploys to wordpress.org SVN once the `SVN_USERNAME`/`SVN_PASSWORD` secrets exist.

### Fixed
- `bin/build-release.sh` failed to write the zip when given a relative output directory (as the release workflow does): the path is now resolved before the script changes directory.
- Stability review:
  - When Postiz is down, admin screens no longer wait for it on every page: a failed channel request is remembered for 3 minutes and answered once per request, and screens that only show names (failure notice, posts list, dashboard widget, log) use the cached or last good list and never call Postiz.
  - Imports (`WP_IMPORTING`) and posts created already published with a date older than a day are not shared (the `socialrelaycvi_should_share` filter can change it).
  - "Share again" and "Retry" keep their state until the share has a final outcome, so a retried job neither stops as "already sent" nor resends the channels that worked.
  - Failures before the request (Postiz unreachable for good, a bad payload, an unexpected PHP error) are recorded on each target channel, so the editor, the posts list and the alerts show them.
  - A post creation that times out is not retried blindly (Postiz may have created it): it is reported so you can check Postiz.
  - A passing image upload failure retries the whole share instead of sending without the image.
  - When the 90 requests/hour of the Postiz API are almost used up, the share waits 10 minutes instead of failing.
  - Shares without a Postiz id are marked as errors, shares Postiz never reports on are given up after 14 days, and posts deleted in Postiz are marked "No longer in Postiz", so nothing stays "queued" forever. A cancelled share no longer blocks sharing the same content again.
  - New indexes on the shares table (database version 2), one query for the whole posts list, the recurring jobs checked once an hour instead of on every page view, a 15-minute status sync also without Action Scheduler, and a stale "share again" flag is dropped when the profile does not allow it.

### Security
- Audit hardening, second round:
  - Uninstall deletes the plugin data of every site of a multisite network, deciding per site whether deletion was requested.
  - When the API key comes from the environment or a constant, pointing the plugin at a different Postiz host asks for an explicit confirmation ("Use this address"), so the operator's key is never sent to a new server by mistake. Plain `http://` is accepted only for local or private hosts, and only when local instances are allowed.
  - Webhook calls are coalesced: one pending status sync at a time carrying the ids of every call (up to 50), at least 30 seconds apart, so a leaked webhook URL cannot spend the Postiz request quota.
  - The share job checks again that the post is still published right before calling Postiz, and cancels what it just created if the post was unpublished meanwhile. "Retry only these channels" state is cleared on every final outcome.
  - The hourly request counter, the AI suggestion quota (now a fixed one-hour window) and the once-a-minute "share again" slot use atomic database locks instead of non-atomic option updates.
- Security audit fixes:
  - The release zip is built only from files tracked by git (minus `.distignore`), and the build fails if any hidden file reaches the package, so local tool output can never be shipped.
  - Images taken from the post content or set as the featured image are used only when they belong to the post, its author can edit them, they are unattached library images and the author can browse the library, or they are attached to a published post. This keeps images of other people's drafts out of previews and social posts.
  - Deleting a post permanently (REST `force=true`, WP-CLI `--force`, no trash) now cancels its pending Postiz posts, like unpublishing or trashing it; a cancel that fails for a passing reason is retried up to 3 times.
  - The "Social networks" column of the posts list shows share results and errors only for posts the viewer can edit.
  - The "share again" limit (once a minute per post) is atomic and shared by the REST route, the `share-post` ability (which now also returns why a post cannot be shared) and "share again on update".
  - The classic metabox only offers the profile's channels to users who cannot manage the site; saving an unchanged channel list no longer marks it as an administrator's choice.
  - `{meta:key}` refuses meta that WordPress or a plugin marks as protected; `{term:…}` and `{hashtags:…}` read only public taxonomies; imported conditions only accept categories and tags.
  - GitHub Actions: read-only token in CI, actions pinned to commit SHAs, no persisted credentials during the release build, and the release fails when the tag does not match the plugin Version and Stable tag.
- Password-protected posts are not shared (their excerpt would leak the protected text); filter `socialrelaycvi_share_protected` to allow it.
- The plugin's post meta (per-post texts, channels, timing) is only exposed in the REST `edit` context, no longer to anonymous visitors.
- Users who cannot manage the site only pick among the profile's channels for a post (channels outside it are ignored unless an administrator chose them) and only attach images they can edit. "Share again" is limited to once a minute per post, and "Suggest with AI" to 30 suggestions per user and hour.
- Changing the Postiz address asks for the API key again so a stored key is never sent to a new server; plain `http://` addresses are refused unless local instances are allowed; on multisite only network administrators change the connection.
- Requests to Postiz do not follow redirects, so the key cannot be carried to another host. Published links from Postiz are kept only when they are http(s). Webhook-triggered sync jobs are not queued twice for the same posts.

### Changed
- Usability review:
  - Profile edits survive switching settings tabs (the tab shows "*"), and the Connection tab offers "Next: set up a publishing profile". The profiles tab explains itself in one line, and a profile turned on with no channel says it will send nothing.
  - The pre-publish summary says when the post's categories or tags fail the profile conditions.
  - The editor Status panel stays visible after turning sharing off, shows load errors with Retry, and the toggle reads "Share this post". The preview asks to save a draft first and says when there are unsaved changes.
  - Errors are no longer swallowed in "Clear log" and "Create a new URL" (which now has a clear confirm button), and the Advanced toggles confirm "Saved.".
  - Channels show their network name instead of the raw Postiz identifier. Timing has the same names everywhere ("Right away", "Next free time in Postiz"…). "Channel" is used for accounts and "network" for platforms. The "find-slot" and "pending group" jargon is gone.
  - Accessibility: the posts column adds a sign to each status dot (✓ ! … ✎ ×), says "Sharing off", and uses a darker grey for cancelled; tab dots are announced to screen readers; placeholder contrast is higher; the character count is announced; the "Insert tag" button uses an icon.
  - Smaller fixes: dashboard links never have an empty title, the failure notice links to the right post type, the log tells an empty filter from an empty log, and the API key help is shorter.
- The log names how a share was sent ("to publish now", "scheduled", "as a draft") instead of the raw Postiz type.
- Conditions: each column explains its rule, the site categories have a "Categories" label, sit in a scrolling box with a search when there are more than eight, and show how many are selected.
- Design pass 2: the Connection tab shows the status ("Connected · 12 channels", or why Postiz cannot be reached) and fields at a readable width; the save bar of the profiles only sticks to the bottom while there are unsaved changes, says when they were saved, and the page asks before leaving with unsaved changes; the "Test this profile" search has a hint; the webhook card has a title. In the editor status panel, the summary uses proper plurals ("4 errors"), removed channels show their network with "Channel not in Postiz", and a disabled "Share again" says why. On small screens the log shows each entry as a block and the settings tabs wrap.
- Design pass: the failure notice groups errors by post ("Post — LinkedIn, X: error") and counts the rest; the log shows each channel with its avatar and network, readable actions (Share, Cancel, Status sync…) and "Channel not in Postiz" for removed channels; the dashboard widget names the network instead of an internal id; the Conditions card is hidden for post types without categories or tags; clearer help under the editor message.
- The profile switch "Attach the featured image" became the "Images" setting; profiles saved with it off keep sending no images. Images are part of the duplicate check, so changing them allows sharing again.
- Settings and log screens rebuilt in React with WordPress components: tabs for connection, publishing profiles (one tab per post type, per-network panels, tag suggestions) and webhook; log with search, level filter and pagination. The old server-rendered forms are gone.
- DataViews was evaluated for the log and discarded: it adds about 1.5 MB to the bundle; the log screen is 5 KB with core components.
- Channel names show their network ("Acme Agency · x") in the pre-publish summary, the editor preview, the per-network list and the log, so channels with the same name on different networks can be told apart.
- readme.txt rewritten for wordpress.org: features, FAQ, screenshots, external services, privacy.
- Local Docker environment with `@wordpress/env` (`npm run env start`, site on port 8890).
- CSS linting (`npm run lint:css`, WordPress stylelint rules) added to CI.
- Editor sidebar closer to the mockups and to core styles: each channel shows its Postiz picture (or its initial on the network colour), name and network on two lines; "All · None" links; the share toggle summarises profile, channels in use and timing; message tabs are pills; the textarea shows the template it falls back to; "Insert tag" and "Reset" buttons; the status panel has a summary, a colour bar and status pills. Publishing profiles and previews use the same channel rows and pictures.

### Fixed
- Plugin Check: every query uses `%i` for table names and static SQL (no interpolated clauses); `load_plugin_textdomain()` removed, translations come from translate.wordpress.org language packs.
- Uninstall also removes the webhook options, the per-post channel and text meta, and the cached channel lists.
- WP-CLI says what happened instead of failing silently: `share` stops with the reason when Postiz is not configured, the profile is off or the post has sharing turned off; `share --now` reports when nothing was sent and why; `cancel` reports how many pending groups it cancelled or that nothing was pending; `log` and `shares` say when there is nothing to show.
- "Requests to Postiz in the last hour" counted share batches, not requests: in "next free slot" mode each channel is its own request, and failed attempts and retries count against the 90/hour limit too. Every POST /posts is now counted (`RequestCounter`).
- Editor panel: with several channels the per-channel tabs ran off the sidebar and two channels with the same name could not be told apart. Tabs now wrap, stay compact and add the network only when two names match.
- Plugin CSS is versioned by file time as well, so a CSS-only change is not hidden by the browser cache.
- Log: in "next free slot" mode each "Sent to Postiz" entry shows its channel.
- Channels removed from Postiz (or reconnected with a new id) produced one warning per channel in the editor preview. Skipped channels are now grouped into one notice per reason, and the editor and the publishing profile say how many saved channels are gone with a "Remove them" button.
- A "share again" request that ended up sending nothing (conditions not met, no channels, no image for a network that needs one) left the flag on, so the next update of the post shared it.
- Unticking every channel in the editor or the classic metabox fell back to the profile channels; it now means no channels.
- Styles imported from shared components are enqueued too (`build/{entry}.css` as well as `build/style-{entry}.css`).

## [0.3.1] - 2026-11-10

### Changed
- Editor panel rewritten in JSX and split into components (`assets/src/editor-panel/`), built with `@wordpress/scripts` into `build/`. Script dependencies and version come from the generated `editor-panel.asset.php`.
- JavaScript linting (WordPress ESLint rules) and build added to CI.
- JavaScript translations now point to the built file.

### Fixed
- `{slug}` (and therefore `utm_campaign`) was empty for drafts: it now falls back to the title, as WordPress does on publish.
- Long URLs no longer overflow the preview box.

## [0.3.0] - 2026-11-10

### Added
- Block editor sidebar panel ("Social networks"): share on/off, channels for this post, text for all networks and per network, preview with character counts (from the last saved version), "share again", and per-network status after publishing (refreshes every 30 s while something is queued).
- Pre-publish panel with the channels and timing that will be used.
- Classic editor metabox: on/off, channels, common text, "share again" and status.
- Dashboard widget: published, queued and errors in the last 7 days, latest errors and next scheduled posts.
- Status sync: reads `GET /posts` for queued and draft shares and records published (with the post URL), error (with the platform message) and draft states. Runs every 15 minutes while something is pending and two minutes after each send.
- Postiz webhook receiver at `/wp-json/socialrelaycvi/v1/webhook/{secret}`: it only triggers the sync, so the payload format does not matter. URL, last call and "Create a new URL" in Settings.
- `_socialrelaycvi_channels` post meta (channels chosen for a post) and `socialrelaycvi_status_changed` action.
- JavaScript translations (`wp i18n make-json`); Spanish updated (159 strings).

### Changed
- The editor panel uses the packages WordPress already loads (no build step).

## [0.2.0] - 2026-11-10

### Added
- Per-network template in each profile. Priority: post text for that network, post text, profile template for that network, common profile template.
- Per-network settings from the Postiz OpenAPI schemas: X (who can reply, community), LinkedIn (image carousel), Instagram (post or story), Pinterest (board, required), Google Business (button).
- Conditions: share only posts in some categories or tags, never posts in others.
- UTM parameters per network (`utm_source` = network, `utm_medium` = social, `utm_campaign` with template tags such as `{slug}`).
- Postiz link shortener option and Postiz tags from the post categories.
- `{slug}` template tag and `_socialrelaycvi_overrides` post meta (per-network text for a post).
- Pending Postiz posts (drafts and future schedules) are cancelled when a post is unpublished or trashed.
- REST routes: `GET /socialrelaycvi/v1/posts/{id}/shares` and `GET /socialrelaycvi/v1/posts/{id}/preview` (dry run, nothing is sent).
- `Postiz\Gateway` interface and `socialrelaycvi_gateway` filter, so the future Agency Hub can replace the direct connection.
- Spanish (es_ES) translation, `.pot` template and `composer i18n` script.

### Changed
- Connection test stops at the first network error and names the unreachable host instead of waiting for every API path.
- Translation contexts for ambiguous words ("Post", "Update") and real plurals in log messages.

## [0.1.0] - 2026-11-10

### Added
- Connection to any Postiz instance with automatic detection of the public API path; Postiz Cloud supported.
- Postiz card in Settings → Connectors (WordPress 7). API key from environment variable, constant or database.
- Publishing profile per post type: channels, message template, timing (now, delay, next free slot, draft) and featured image.
- Template engine with tags, filters and thread parts (`---`), and per-network character limits.
- One request per post for every channel; background sending with Action Scheduler, retries and duplicate protection.
- Shares and log tables, settings and log screens, `socialrelaycvi/v1` status and channels routes.
- Module registry (`socialrelaycvi_modules` filter) for the Pro add-on.

[Unreleased]: https://github.com/dcarrero/social-relay-for-postiz/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/dcarrero/social-relay-for-postiz/compare/v0.3.1...v1.0.0
[0.3.1]: https://github.com/dcarrero/social-relay-for-postiz/compare/v0.3.0...v0.3.1
[0.3.0]: https://github.com/dcarrero/social-relay-for-postiz/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/dcarrero/social-relay-for-postiz/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/dcarrero/social-relay-for-postiz/releases/tag/v0.1.0
