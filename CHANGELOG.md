# Changelog

All notable changes to Social Relay for Postiz. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Planned for 0.4
- Evergreen, bulk publishing and calendar (to be built as Pro modules).
- Settings and log screens rebuilt with DataViews/DataForm.

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

[Unreleased]: https://github.com/dcarrero/social-relay-for-postiz/compare/v0.3.0...HEAD
[0.3.0]: https://github.com/dcarrero/social-relay-for-postiz/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/dcarrero/social-relay-for-postiz/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/dcarrero/social-relay-for-postiz/releases/tag/v0.1.0
