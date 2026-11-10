# Changelog

All notable changes to Social Relay for Postiz. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- WP-CLI: `wp social-relay status|test|channels|preview|share|shares|cancel|sync|log`.
- Abilities API (WordPress 6.9+): `socialrelaycvi/get-status`, `list-channels`, `preview-post`, `share-post`, `get-post-shares` and `cancel-post-shares`, in the `social-media` category, with input/output schemas, behaviour annotations and capability checks. Exposed in REST for AI agents (MCP Adapter) and other tools.
- Admin REST routes (`socialrelaycvi/v1/admin/settings|connection|profiles|stats|advanced|webhook/regenerate|log`), `manage_options` only; the API key is write-only.
- Editor panel: one tab per selected channel for its own text, next to the "All" tab; a dot marks channels with custom text.
- Editor preview drawn like each network: avatar in the network colour, character count against the limit, thread replies and the link card.
- "Test this profile" panel in the publishing profiles tab: pick a post and see the messages the saved profile would send, without sending anything.
- Log: cards with published and failed shares (30 days), queued shares and requests to Postiz in the last hour against the 90/hour limit.
- "Advanced" settings tab: delete all plugin data when the plugin is deleted (also forced by `SOCIALRELAYCVI_DELETE_DATA`).
- Suggested privacy policy text (Settings → Privacy → Policy Guide).
- wordpress.org assets in `.wordpress-org/`: icon, banner and five screenshots, plus a Live Preview blueprint.
- `bin/build-release.sh`: builds the installable zip (production Composer dependencies, compiled JavaScript, `.distignore` applied).
- Release workflow: on a `v*` tag, builds the zip, attaches it to a GitHub release and deploys to wordpress.org SVN once the `SVN_USERNAME`/`SVN_PASSWORD` secrets exist.

### Changed
- Settings and log screens rebuilt in React with WordPress components: tabs for connection, publishing profiles (one tab per post type, per-network panels, tag suggestions) and webhook; log with search, level filter and pagination. The old server-rendered forms are gone.
- DataViews was evaluated for the log and discarded: it adds about 1.5 MB to the bundle; the log screen is 5 KB with core components.
- Channel names show their network ("Acme Agency · x") in the pre-publish summary, the editor preview, the per-network list and the log, so channels with the same name on different networks can be told apart.
- readme.txt rewritten for wordpress.org: features, FAQ, screenshots, external services, privacy.
- Local Docker environment with `@wordpress/env` (`npm run env start`, site on port 8890).
- CSS linting (`npm run lint:css`, WordPress stylelint rules) added to CI.

### Fixed
- Plugin Check: every query uses `%i` for table names and static SQL (no interpolated clauses); `load_plugin_textdomain()` removed, translations come from translate.wordpress.org language packs.
- Uninstall also removes the webhook options, the per-post channel and text meta, and the cached channel lists.
- WP-CLI says what happened instead of failing silently: `share` stops with the reason when Postiz is not configured, the profile is off or the post has sharing turned off; `share --now` reports when nothing was sent and why; `cancel` reports how many pending groups it cancelled or that nothing was pending; `log` and `shares` say when there is nothing to show.
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

[Unreleased]: https://github.com/dcarrero/social-relay-for-postiz/compare/v0.3.1...HEAD
[0.3.1]: https://github.com/dcarrero/social-relay-for-postiz/compare/v0.3.0...v0.3.1
[0.3.0]: https://github.com/dcarrero/social-relay-for-postiz/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/dcarrero/social-relay-for-postiz/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/dcarrero/social-relay-for-postiz/releases/tag/v0.1.0
