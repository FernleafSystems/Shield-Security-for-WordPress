<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\SiteLoopback;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Connects the early loader to Shield: the setting, the verification response, deactivation and the
 * periodic checks that finish pending installs and removals.
 */
class EarlyLoadingController {

	use ExecOnce;
	use PluginControllerConsumer;

	public const OPT_ENABLE = 'enable_early_loading';

	/**
	 * Shortest gap between checks triggered by admin page loads.
	 */
	private const ADMIN_CHECK_INTERVAL = 300;

	/**
	 * Longer than an install's loopback requests can take.
	 */
	private const GUARD_TTL = 180;

	protected function run() {
		$this->verifyRequest()->respondIfRequested();
		add_action( self::con()->prefix( 'pre_deactivate_plugin' ), [ $this, 'onDeactivate' ] );
		add_action( 'admin_init', [ $this, 'maintainIfDue' ] );
	}

	public function isEnabled() :bool {
		return self::con()->opts->optIs( self::OPT_ENABLE, 'Y' );
	}

	public function state() :State {
		return $this->store()->load();
	}

	/**
	 * Applies a change to the setting. A failed install switches the setting back off.
	 */
	public function applyOption() :void {
		\update_option( $this->changedAtKey(), Services::Request()->ts(), false );
		$guard = $this->acquireGuard();
		if ( $guard === '' ) {
			// Another request is changing the early loader; maintain() reconciles with the setting afterwards.
			return;
		}
		try {
			if ( !$this->isEnabled() ) {
				$this->remove();
			}
			elseif ( $this->isWebRequest() ) {
				try {
					$this->installer()->install();
				}
				catch ( InstallException $e ) {
					self::con()->opts->optSet( self::OPT_ENABLE, 'N' );
				}
			}
		}
		catch ( InstallException $e ) {
			// Recorded in the state for the settings page.
		}
		finally {
			$this->releaseGuard( $guard );
		}
	}

	/**
	 * Deactivation takes Shield's blocks out of the configuration files. The stub stays, inert once the
	 * plugin is gone, until PHP has stopped using it.
	 */
	public function onDeactivate() :void {
		try {
			$this->installer()->removeDirectives();
		}
		catch ( InstallException $e ) {
		}
	}

	public function maintainIfDue() :void {
		$state = $this->state();
		$due = Services::Request()->ts() - $state->checkedAt >= self::ADMIN_CHECK_INTERVAL;
		if ( $due && self::con()->isPluginAdmin() && $this->needsAttention( $state ) ) {
			$this->maintain();
		}
	}

	/**
	 * Installs when the setting is on but nothing is installed (for example after reactivation),
	 * confirms installs, keeps the stub current and completes removals.
	 */
	public function maintain() :void {
		// A request that began before the setting last changed has read the previous setting, and while a
		// settings save is installing or removing, its own loopback requests can start WP-Cron.
		if ( $this->settingChangedDuringThisRequest() ) {
			return;
		}
		$guard = $this->acquireGuard();
		if ( $guard === '' ) {
			return;
		}
		$state = $this->state();
		try {
			if ( !$this->isEnabled() ) {
				if ( $state->status !== State::OFF || ( new StubFile( $this->paths()->stub ) )->exists() ) {
					$this->remove();
				}
			}
			elseif ( \in_array( $state->status, [ State::INSTALLED, State::MANUAL ], true ) ) {
				$this->installer()->refresh();
			}
			elseif ( $this->isWebRequest() ) {
				try {
					$this->installer()->install();
				}
				catch ( InstallException $e ) {
					self::con()->opts->optSet( self::OPT_ENABLE, 'N' );
				}
			}
		}
		catch ( InstallException $e ) {
			// Recorded in the state for the settings page.
		}
		finally {
			$this->releaseGuard( $guard );
		}
	}

	/**
	 * Whether the given file is the stub exactly as Shield generates it, so scans can skip it.
	 */
	public function isGeneratedStub( string $path ) :bool {
		$paths = $this->paths();
		$real = \realpath( $path );
		return $real !== false
			   && $real === \realpath( $paths->stub )
			   && ( new StubFile( $paths->stub ) )->matches( $this->installer()->stubContent( $this->state()->hostPrepend ) );
	}

	/**
	 * The line for an administrator to add when PHP's configuration can't be changed automatically.
	 */
	public function manualDirective() :string {
		return 'auto_prepend_file = '.DirectiveBlocks::quoted( $this->paths()->stub );
	}

	public function installer() :Installer {
		$paths = $this->paths();
		return new Installer(
			$paths,
			$this->environment(),
			new WpLoopbackProbe(
				$this->verifyRequest(),
				new SiteLoopback(),
				\admin_url( 'admin-ajax.php' ),
				\home_url( '/' ),
				$paths->stubUrl
			),
			$this->store()
		);
	}

	/**
	 * @throws InstallException
	 */
	private function remove() :void {
		$installer = $this->installer();
		$state = $installer->removeDirectives();
		if ( $state->status === State::REMOVING ) {
			$installer->finishRemoval();
		}
	}

	private function needsAttention( State $state ) :bool {
		return $state->status === State::REMOVING
			   || $state->isPending( Services::Request()->ts() )
			   || ( $this->isEnabled() && $state->status === State::OFF )
			   || ( !$this->isEnabled() && $state->status !== State::OFF );
	}

	private function paths() :Paths {
		if ( !\function_exists( 'get_home_path' ) ) {
			require_once ABSPATH.'wp-admin/includes/file.php';
		}
		$home = \trailingslashit( \wp_normalize_path( \get_home_path() ) );
		$userIni = $this->ini( 'user_ini.filename' );
		$con = self::con();
		return new Paths(
			\wp_normalize_path( ABSPATH ).StubBuilder::FILE_NAME,
			\site_url( '/'.StubBuilder::FILE_NAME ),
			$home.'.htaccess',
			Mechanism::isUsableUserIniFilename( $userIni ) ? $home.$userIni : null,
			\wp_normalize_path( \path_join( $con->getRootDir(), 'src/VirtualPatching/EarlyLoading/bootstrap.php' ) ),
			\wp_normalize_path( $con->comps->virtual_patching->rulesetPath() ),
			$this->scopeDirs()
		);
	}

	/**
	 * ABSPATH and the content directory, which may be outside it, as PHP will report them when resolved.
	 * @return string[]
	 */
	private function scopeDirs() :array {
		$dirs = [];
		foreach ( [ ABSPATH, WP_CONTENT_DIR ] as $dir ) {
			$real = \function_exists( 'realpath' ) ? \realpath( $dir ) : $dir;
			if ( \is_string( $real ) ) {
				$dirs[] = \untrailingslashit( \wp_normalize_path( $real ) );
			}
		}
		return \array_values( \array_unique( $dirs ) );
	}

	private function environment() :Environment {
		return new Environment(
			// Without ini_get() the current auto_prepend_file can't be known, so only manual setup is offered.
			\function_exists( 'ini_get' ) ? \PHP_SAPI : '',
			$this->ini( 'user_ini.filename' ),
			(int)$this->ini( 'user_ini.cache_ttl' ),
			$this->ini( 'auto_prepend_file' ),
			(string)( $_SERVER[ 'SERVER_SOFTWARE' ] ?? '' ),
			Services::WpGeneral()->isMultisite(),
			Services::WpFs()->isFilesystemAccessDirect(),
			$this->isWebRequest(),
			Services::Request()->ts()
		);
	}

	/**
	 * Hosts can disable ini_get().
	 */
	private function ini( string $key ) :string {
		return \function_exists( 'ini_get' ) ? (string)\ini_get( $key ) : '';
	}

	/**
	 * Only a web request sees the PHP configuration the site's visitors get; WP-CLI reads neither
	 * .htaccess nor .user.ini.
	 */
	private function isWebRequest() :bool {
		return \PHP_SAPI !== 'cli' && !Services::WpGeneral()->isWpCli();
	}

	private function settingChangedDuringThisRequest() :bool {
		// Read from the database, not from what this request cached when it started.
		\wp_cache_delete( $this->changedAtKey(), 'options' );
		\wp_cache_delete( 'notoptions', 'options' );
		return (int)\get_option( $this->changedAtKey(), 0 ) >= (int)( $_SERVER[ 'REQUEST_TIME' ] ?? \time() );
	}

	private function changedAtKey() :string {
		return self::con()->prefix( 'early_loading_changed_at', '_' );
	}

	private function acquireGuard() :string {
		$key = self::con()->prefix( 'early_loading_guard', '_' );
		if ( \get_transient( $key ) !== false ) {
			return '';
		}
		$token = \wp_generate_uuid4();
		if ( !\set_transient( $key, $token, self::GUARD_TTL ) ) {
			return '';
		}
		return \get_transient( $key ) === $token ? $token : '';
	}

	private function releaseGuard( string $token ) :void {
		$key = self::con()->prefix( 'early_loading_guard', '_' );
		if ( \hash_equals( $token, (string)\get_transient( $key ) ) ) {
			\delete_transient( $key );
		}
	}

	private function verifyRequest() :VerifyRequest {
		return new VerifyRequest( self::con()->prefix( 'early_loading_check', '_' ) );
	}

	private function store() :WpStateStore {
		return new WpStateStore( self::con()->prefix( 'early_loading_state', '_' ) );
	}
}
