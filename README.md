# Social Relay for Postiz

WordPress plugin that auto-publishes and schedules your content on social networks through **your own Postiz instance** (self-hosted or Postiz Cloud). WordPress talks directly to the Postiz public API: no middleman service.

- Requires WordPress 7.0+ and PHP 8.2+.
- Status: early development, version 0.3.1 (see [CHANGELOG.md](CHANGELOG.md)).

## Features

- Direct connection to your Postiz instance (self-hosted or Postiz Cloud), with automatic API path detection and a card in Settings → Connectors.
- Publishing profile per post type: channels, common and per-network templates, network settings, timing (now, delay, next free slot, draft).
- Templates with tags (`{title}`, `{excerpt}`, `{url}`, `{hashtags:post_tag}`, `{meta:key}`…), filters (`|limit:200`, `|upper`…) and thread parts (`---`).
- Conditions on categories and tags, UTM parameters per network, Postiz shortener and tags.
- Background sending (Action Scheduler) with retries, duplicate protection and an activity log.
- Pending posts are cancelled in Postiz when a post is unpublished.
- Status sync with Postiz (published URL, platform errors), triggered by the Postiz webhook or every 15 minutes.
- Block editor panel (channels, text per network, preview, status), classic editor metabox and dashboard widget.
- REST routes for status, channels, a post's shares and a dry-run preview.
- WP-CLI: `wp social-relay status|test|channels|preview|share|shares|cancel|sync|log` (`wp help social-relay` for details).
- Abilities API: `socialrelaycvi/get-status`, `list-channels`, `preview-post`, `share-post`, `get-post-shares`, `cancel-post-shares`, available to AI agents through the MCP Adapter.
- English base, Spanish (es_ES) translation included.

## Development

```bash
composer install
npm ci
npm run build      # editor panel: assets/src → build/ (required, build/ is not in git)
npm run start      # same, rebuilding on every change
npm run lint:js    # WordPress ESLint rules
composer test      # PHPUnit (unit tests, no WordPress needed)
composer lint      # PHPCS with WordPress Coding Standards + PHPCompatibility
composer analyse   # PHPStan
```

### Layout

| Path | What it does |
|---|---|
| `social-relay-for-postiz.php` | Bootstrap: requirements, autoload, Action Scheduler, hooks |
| `src/Plugin.php` | Service container and module registry (`socialrelaycvi_modules` filter) |
| `src/Postiz/` | API client, endpoint detection, channel cache, per-network rules |
| `src/Templates/` | Template engine (`{tags}`, `\|filters`, `---` threads) and post context |
| `src/Sharing/` | Payload builder, share job and preview, conditions, UTM, shares table |
| `src/Lifecycle/` | Installer (tables) and post listener |
| `src/Connector/` | Settings → Connectors registration (WordPress 7) |
| `src/Rest/` | `socialrelaycvi/v1` routes and the Postiz webhook |
| `src/Sync/` | Status sync with Postiz |
| `src/Cli/` | WP-CLI commands |
| `src/Abilities/` | Abilities API registration |
| `src/Admin/` | Settings and log screens, editor panel loader, classic metabox, dashboard widget |
| `assets/src/editor-panel/` | Block editor panel in JSX (built into `build/` with @wordpress/scripts; @wordpress/* packages come from WordPress) |
| `languages/` | `.pot` template and translations |

### Extending

Add-ons register their own modules:

```php
add_filter( 'socialrelaycvi_modules', function ( array $modules, \SocialRelayCvi\Plugin $plugin ) {
	$modules['my_module'] = new My_Module( $plugin ); // implements \SocialRelayCvi\Module
	return $modules;
}, 10, 2 );
```

Other hooks: `socialrelaycvi_gateway`, `socialrelaycvi_status_changed`, `socialrelaycvi_cancel_on_unpublish`, `socialrelaycvi_template_context`, `socialrelaycvi_payload`, `socialrelaycvi_should_share`, `socialrelaycvi_shared`, `socialrelaycvi_post_types`, `socialrelaycvi_allow_local_instance`, `socialrelaycvi_log_retention_days`.

## Translations

All strings are written in English with the `social-relay-for-postiz` text domain. Bundled translations live in `languages/` (Spanish, `es_ES`, to start). Once the plugin is on wordpress.org, language packs from translate.wordpress.org take precedence.

```bash
composer i18n   # regenerate the .pot, update the .po files and compile the .mo files
```

In JavaScript, write the text domain literally in every call (`__( 'Text', 'social-relay-for-postiz' )`) so the extractor finds it; run `npm run build` before `composer i18n`, because strings are extracted from `build/`. When adding strings: use `_x()` when a word has more than one meaning (e.g. "Post"), `_n()` for counts, and a `translators:` comment for every placeholder.

## License

GPL-2.0-or-later. Not affiliated with or endorsed by Postiz.
