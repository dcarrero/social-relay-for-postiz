# Social Relay for Postiz

WordPress plugin that auto-publishes and schedules your content on social networks through **your own Postiz instance** (self-hosted or Postiz Cloud). WordPress talks directly to the Postiz public API: no middleman service.

- Requires WordPress 7.0+ and PHP 8.2+.
- Status: early development (0.1).

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
| `src/Sharing/` | Payload builder, share job, shares table |
| `src/Lifecycle/` | Installer (tables) and post listener |
| `src/Connector/` | Settings → Connectors registration (WordPress 7) |
| `src/Rest/` | `socialrelaycvi/v1` routes |
| `src/Admin/` | Settings and log screens |

### Extending

Add-ons register their own modules:

```php
add_filter( 'socialrelaycvi_modules', function ( array $modules, \SocialRelayCvi\Plugin $plugin ) {
	$modules['my_module'] = new My_Module( $plugin ); // implements \SocialRelayCvi\Module
	return $modules;
}, 10, 2 );
```

Other hooks: `socialrelaycvi_gateway`, `socialrelaycvi_cancel_on_unpublish`, `socialrelaycvi_template_context`, `socialrelaycvi_payload`, `socialrelaycvi_should_share`, `socialrelaycvi_shared`, `socialrelaycvi_post_types`, `socialrelaycvi_allow_local_instance`, `socialrelaycvi_log_retention_days`.

## Translations

All strings are written in English with the `social-relay-for-postiz` text domain. Bundled translations live in `languages/` (Spanish, `es_ES`, to start). Once the plugin is on wordpress.org, language packs from translate.wordpress.org take precedence.

```bash
composer i18n   # regenerate the .pot, update the .po files and compile the .mo files
```

When adding strings: use `_x()` when a word has more than one meaning (e.g. "Post"), `_n()` for counts, and a `translators:` comment for every placeholder.

## License

GPL-2.0-or-later. Not affiliated with or endorsed by Postiz.
