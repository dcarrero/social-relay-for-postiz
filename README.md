# Social Relay for Postiz

WordPress plugin that auto-publishes and schedules your content on social networks through **your own Postiz instance** (self-hosted or Postiz Cloud). WordPress talks directly to the Postiz public API: no middleman service.

- Requires WordPress 7.0+ and PHP 8.2+.
- Status: early development, version 0.2 (see [CHANGELOG.md](CHANGELOG.md)).

## Features

- Direct connection to your Postiz instance (self-hosted or Postiz Cloud), with automatic API path detection and a card in Settings → Connectors.
- Publishing profile per post type: channels, common and per-network templates, network settings, timing (now, delay, next free slot, draft).
- Templates with tags (`{title}`, `{excerpt}`, `{url}`, `{hashtags:post_tag}`, `{meta:key}`…), filters (`|limit:200`, `|upper`…) and thread parts (`---`).
- Conditions on categories and tags, UTM parameters per network, Postiz shortener and tags.
- Background sending (Action Scheduler) with retries, duplicate protection and an activity log.
- Pending posts are cancelled in Postiz when a post is unpublished.
- Status sync with Postiz (published URL, platform errors), triggered by the Postiz webhook or every 15 minutes.
- REST routes for status, channels, a post's shares and a dry-run preview.
- English base, Spanish (es_ES) translation included.

## Development

```bash
composer install
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
| `src/Admin/` | Settings and log screens |
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

When adding strings: use `_x()` when a word has more than one meaning (e.g. "Post"), `_n()` for counts, and a `translators:` comment for every placeholder.

## License

GPL-2.0-or-later. Not affiliated with or endorsed by Postiz.
