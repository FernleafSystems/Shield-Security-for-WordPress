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
	private const LOCK_TIMEOUT = 300;

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
		// Requests that start before this one has stored the new setting still read the old one, so the
		// time of the change is also recorded once this request has finished.
		$this->markSettingChanged();
		add_action( 'shutdown', [ $this, 'markSettingChanged' ], \PHP_INT_MAX );

		if ( !$this->lock() ) {
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
			$this->unlock();
		}
	}

	/**
	 * Deactivation takes Shield's blocks out of the configuration files. The stub stays, inert once the
	 * plugin is gone, until PHP has stopped using it. This goes ahead even while another request holds the
	 * lock, because leaving the blocks in place is the greater risk.
	 */
	public function onDeactivate() :void {
		$locked = $this->lock();
		try {
			$this->installer()->removeDirectives();
		}
		catch ( InstallException $e ) {
		}
		finally {
			if ( $locked ) {
				$this->unlock();
			}
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
	 * Puts back interrupted installs, installs when the setting is on but nothing is installed (for example
	 * after reactivation), confirms and settles installs, keeps the stub current and completes removals.
	 */
	public function maintain() :void {
		if ( $this->settingChangedDuringThisRequest() || !$this->lock() ) {
			return;
		}
		try {
			$installer = $this->installer();
			$state = $installer->recoverInterrupted();
			( new StubFile( $this->stubPath() ) )->removeStaleCandidates( Services::Request()->ts() );

			if ( !$this->isEnabled() ) {
				if ( $state->status !== State::OFF || ( new StubFile( $this->stubPath() ) )->exists() ) {
					$this->remove();
				}
			}
			elseif ( \in_array( $state->status, [ State::INSTALLED, State::MANUAL ], true ) ) {
				$installer->refresh();
			}
			elseif ( $this->isWebRequest() ) {
				try {
					$installer->install();
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
			$this->unlock();
		}
	}

	/**
	 * Whether the given file is this site's stub as Shield manages it, so scans neither report it nor offer
	 * to delete it: deleting a stub PHP still prepends would break every request.
	 */
	public function isShieldStub( string $path ) :bool {
		$stub = new StubFile( $this->stubPath() );
		$state = $this->state();
		return VerifyRequest::isOwnStub( $path, $this->stubPath() )
			   && ( $stub->matches( $this->installer()->stubContent( $state->hostPrepend ) )
					|| ( $state->status !== State::OFF && $stub->isShieldStub() ) );
	}

	/**
	 * The settings for an administrator to add when PHP's configuration can't be changed automatically.
	 * @return array{ini:string,pool:string}
	 */
	public function manualDirectives() :array {
		$quoted = DirectiveBlocks::quoted( $this->stubPath() );
		return [
			'ini'  => 'auto_prepend_file = '.$quoted,
			'pool' => 'php_admin_value[auto_prepend_file] = '.$quoted,
		];
	}

	public function installer() :Installer {
		return new Installer(
			$this->paths(),
			$this->environment(),
			new WpLoopbackProbe(
				$this->verifyRequest(),
				new SiteLoopback(),
				\admin_url( 'admin-ajax.php' ),
				\home_url( '/' )
			),
			$this->store()
		);
	}

	/**
	 * Called at the start of a settings change and again at the end of that request.
	 */
	public function markSettingChanged() :void {
		\update_option( $this->changedAtKey(), \time(), false );
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
		$enabled = $this->isEnabled();
		return \in_array( $state->status, [ State::INSTALLING, State::REMOVING ], true )
			   || ( $state->status === State::INSTALLED && !$state->settled )
			   || ( $enabled && $state->status === State::OFF )
			   || ( !$enabled && $state->status !== State::OFF )
			   || ( \in_array( $state->status, [ State::INSTALLED, State::MANUAL ], true )
					&& !( new StubFile( $this->stubPath() ) )->matches( $this->installer()->stubContent( $state->hostPrepend ) ) );
	}

	private function stubPath() :string {
		return \wp_normalize_path( ABSPATH ).StubBuilder::FILE_NAME;
	}

	private function paths() :Paths {
		if ( !\function_exists( 'get_home_path' ) ) {
			require_once ABSPATH.'wp-admin/includes/file.php';
		}
		$home = \trailingslashit( \wp_normalize_path( \get_home_path() ) );
		$userIni = $this->ini( 'user_ini.filename' );
		$con = self::con();
		return new Paths(
			$this->stubPath(),
			\site_url( '/'.StubBuilder::FILE_NAME ),
			$home.'.htaccess',
			Mechanism::isUsableUserIniFilename( $userIni ) ? $home.$userIni : null,
			\wp_normalize_path( \path_join( $con->getRootDir(), 'src/VirtualPatching/EarlyLoading/bootstrap.php' ) ),
			\wp_normalize_path( $con->comps->virtual_patching->rulesetPath() ),
			$this->scope( $home )
		);
	}

	/**
	 * Only this site's WordPress files: those directly in ABSPATH, wp-admin, wp-includes and the content
	 * directory (which may be outside ABSPATH), and the front controller when WordPress has its own
	 * directory. Paths are resolved as PHP reports them for the requested script.
	 */
	private function scope( string $home ) :array {
		$resolve = function ( string $path ) :string {
			$real = \function_exists( 'realpath' ) ? \realpath( $path ) : $path;
			return \is_string( $real ) ? \untrailingslashit( \wp_normalize_path( $real ) ) : '';
		};
		$abspath = $resolve( ABSPATH );
		$frontController = $resolve( $home.'index.php' );
		return [
			'files'     => $frontController !== '' && \dirname( $frontController ) !== $abspath ? [ $frontController ] : [],
			'root_dirs' => \array_filter( [ $abspath ] ),
			'dirs'      => \array_values( \array_unique( \array_filter( [
				$resolve( ABSPATH.'wp-admin' ),
				$resolve( ABSPATH.WPINC ),
				$resolve( WP_CONTENT_DIR ),
			] ) ) ),
		];
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

	/**
	 * WordPress's own lock for upgrades: an atomic insert, released automatically if the holder dies.
	 */
	private function lock() :bool {
		if ( !\class_exists( '\WP_Upgrader' ) ) {
			require_once ABSPATH.'wp-admin/includes/class-wp-upgrader.php';
		}
		return \WP_Upgrader::create_lock( $this->lockName(), self::LOCK_TIMEOUT );
	}

	private function unlock() :void {
		\WP_Upgrader::release_lock( $this->lockName() );
	}

	private function lockName() :string {
		return self::con()->prefix( 'early_loading', '_' );
	}

	private function verifyRequest() :VerifyRequest {
		return new VerifyRequest( self::con()->prefix( 'early_loading_check', '_' ), $this->stubPath() );
	}

	private function store() :WpStateStore {
		return new WpStateStore( self::con()->prefix( 'early_loading_state', '_' ) );
	}
}
