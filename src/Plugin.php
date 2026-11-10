<?php
/**
 * Plugin bootstrap and service container.
 *
 * @package SocialRelayCvi
 */

declare( strict_types=1 );

namespace SocialRelayCvi;

use SocialRelayCvi\Abilities\AbilitiesRegistration;
use SocialRelayCvi\Admin\ClassicMetabox;
use SocialRelayCvi\Admin\DashboardWidget;
use SocialRelayCvi\Admin\EditorPanel;
use SocialRelayCvi\Admin\ErrorAlerts;
use SocialRelayCvi\Admin\LogPage;
use SocialRelayCvi\Admin\PostsColumn;
use SocialRelayCvi\Admin\Privacy;
use SocialRelayCvi\Admin\SettingsPage;
use SocialRelayCvi\Connector\ConnectorRegistration;
use SocialRelayCvi\Lifecycle\Installer;
use SocialRelayCvi\Lifecycle\PostListener;
use SocialRelayCvi\Log\Logger;
use SocialRelayCvi\Postiz\ChannelRepository;
use SocialRelayCvi\Postiz\Client;
use SocialRelayCvi\Postiz\Gateway;
use SocialRelayCvi\Profiles\ProfileRepository;
use SocialRelayCvi\Queue\Queue;
use SocialRelayCvi\Rest\AdminController;
use SocialRelayCvi\Rest\ConnectionController;
use SocialRelayCvi\Rest\WebhookController;
use SocialRelayCvi\Settings\Connection;
use SocialRelayCvi\Sharing\ShareRepository;
use SocialRelayCvi\Sharing\ShareService;
use SocialRelayCvi\Sync\StatusSync;

/**
 * Owns the shared services and the list of modules.
 *
 * Modules are the extension point for the Pro add-on: it adds its own through the
 * `socialrelaycvi_modules` filter and can reach every service through Plugin::instance().
 */
final class Plugin {

	/**
	 * Singleton instance, created on plugins_loaded.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Registered modules, keyed by id.
	 *
	 * @var array<string, Module>
	 */
	private array $modules = array();

	/**
	 * Connection settings.
	 *
	 * @var Connection
	 */
	public readonly Connection $connection;

	/**
	 * Activity log.
	 *
	 * @var Logger
	 */
	public readonly Logger $logger;

	/**
	 * Publishing profiles.
	 *
	 * @var ProfileRepository
	 */
	public readonly ProfileRepository $profiles;

	/**
	 * Shares table.
	 *
	 * @var ShareRepository
	 */
	public readonly ShareRepository $shares;

	/**
	 * Cached Postiz channels.
	 *
	 * @var ChannelRepository
	 */
	public readonly ChannelRepository $channels;

	/**
	 * Background jobs.
	 *
	 * @var Queue
	 */
	public readonly Queue $queue;

	/**
	 * Share job.
	 *
	 * @var ShareService
	 */
	public readonly ShareService $share_service;

	/**
	 * Status sync with Postiz.
	 *
	 * @var StatusSync
	 */
	public readonly StatusSync $status_sync;

	/**
	 * Builds the services. Nothing is hooked until init() runs.
	 */
	private function __construct() {
		$this->connection    = new Connection();
		$this->logger        = new Logger();
		$this->profiles      = new ProfileRepository();
		$this->shares        = new ShareRepository();
		$this->channels      = new ChannelRepository( $this->connection, fn (): ?Gateway => $this->gateway() );
		$this->queue         = new Queue();
		$this->share_service = new ShareService( $this );
		$this->status_sync   = new StatusSync( $this );
	}

	/**
	 * Entry point, hooked to plugins_loaded.
	 */
	public static function boot(): void {
		if ( null !== self::$instance ) {
			return;
		}

		self::$instance = new self();
		self::$instance->init();
	}

	/**
	 * Returns the running instance.
	 *
	 * @throws \LogicException When called before plugins_loaded.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			throw new \LogicException( 'Social Relay for Postiz has not booted yet.' );
		}

		return self::$instance;
	}

	/**
	 * Returns the gateway to Postiz, or null when not configured.
	 *
	 * By default it is a direct Client. The Agency Hub connection mode replaces it
	 * through the `socialrelaycvi_gateway` filter, so the site never holds the Postiz key.
	 */
	public function gateway(): ?Gateway {
		$gateway = $this->connection->is_configured()
			? new Client( $this->connection->api_base(), $this->connection->api_key() )
			: null;

		/**
		 * Filters the gateway used to reach Postiz.
		 *
		 * @param Gateway|null $gateway Direct client, or null when not configured.
		 * @param Plugin       $plugin  Plugin instance.
		 */
		$gateway = apply_filters( 'socialrelaycvi_gateway', $gateway, $this );

		return $gateway instanceof Gateway ? $gateway : null;
	}

	/**
	 * Returns a module by id.
	 *
	 * @param string $id Module id.
	 */
	public function module( string $id ): ?Module {
		return $this->modules[ $id ] ?? null;
	}

	/**
	 * Registers the core modules, lets add-ons add theirs and hooks them all.
	 */
	private function init(): void {
		Installer::maybe_upgrade();

		// Translations come from translate.wordpress.org language packs, loaded by WordPress on demand.

		$modules = array(
			'connector'     => new ConnectorRegistration( $this ),
			'queue'         => $this->queue,
			'share_service' => $this->share_service,
			'post_listener' => new PostListener( $this ),
			'rest'          => new ConnectionController( $this ),
			'webhook'       => new WebhookController( $this ),
			'admin_rest'    => new AdminController( $this ),
			'status_sync'   => $this->status_sync,
			'settings_page' => new SettingsPage(),
			'log_page'      => new LogPage( $this ),
			'editor_panel'  => new EditorPanel( $this ),
			'metabox'       => new ClassicMetabox( $this ),
			'dashboard'     => new DashboardWidget( $this ),
			'posts_column'  => new PostsColumn( $this ),
			'error_alerts'  => new ErrorAlerts( $this ),
			'privacy'       => new Privacy(),
			'log_cleanup'   => $this->logger,
			'post_meta'     => new PostMeta(),
			'abilities'     => new AbilitiesRegistration( $this ),
		);

		/**
		 * Filters the modules that make up the plugin.
		 *
		 * The Pro add-on hooks here to add its own modules. Each value must implement
		 * SocialRelayCvi\Module; anything else is ignored.
		 *
		 * @param array<string, Module> $modules Modules keyed by id.
		 * @param Plugin                $plugin  Plugin instance.
		 */
		$modules = apply_filters( 'socialrelaycvi_modules', $modules, $this );

		foreach ( (array) $modules as $id => $module ) {
			if ( $module instanceof Module ) {
				$this->modules[ (string) $id ] = $module;
				$module->register();
			}
		}

		if ( defined( 'WP_CLI' ) && class_exists( \WP_CLI::class ) ) {
			\WP_CLI::add_command( 'social-relay', new Cli\Command( $this ) );
		}

		/**
		 * Fires once every module has been registered.
		 *
		 * @param Plugin $plugin Plugin instance.
		 */
		do_action( 'socialrelaycvi_loaded', $this );
	}
}
