<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\{
	AssessDirWrite,
	DummyFile
};

class CompatibilityChecks extends BaseHandler {

	private array $checkParams;

	/**
	 * @throws \Exception
	 */
	public function __construct( array $checkParams, string $uuid, int $stopAtTS ) {
		parent::__construct( $uuid, $stopAtTS );
		$this->checkParams = $checkParams;
	}

	/**
	 * Many of these data point are stored in the archive meta, so changes here must consider how meta is gathered and
	 * stored in the WD archive meta "snapshot"
	 */
	public function run() :array {
		$con = self::con();
		$WP = Services::WpGeneral();
		return [
			'server'   => [
				'ip'              => $this->ip(),
				'disk_free_space' => \function_exists( '\disk_free_space' ) ? \disk_free_space( ABSPATH ) : -1,
			],
			'wp'       => [
				'wp_version'   => \function_exists( 'wp_get_wp_version' ) ? wp_get_wp_version() : \get_bloginfo( 'version' ),
				'url_home'     => $WP->getHomeUrl(),
				'url_wp'       => $WP->getWpUrl(),
				'url_rest'     => rest_url(),
				'url_content'  => content_url(),
				'locale'       => get_locale(),
				'timezone'     => wp_timezone_string(),
				'wplang'       => \defined( 'WPLANG' ) ? WPLANG : '',
				'is_multisite' => is_multisite(),
				'plugins'      => $this->plugins(),
				'themes'       => $this->themes(),
			],
			'versions' => [
				'php'    => \phpversion(),
				'driver' => $con->cfg->version(),
				'wp'     => \function_exists( 'wp_get_wp_version' ) ? wp_get_wp_version() : \get_bloginfo( 'version' ),
			],
			'paths'    => $this->paths(),
			'ini'      => $this->ini(),
			'caps'     => $this->caps(),
			'exts'     => \is_array( \get_loaded_extensions() ) ? \get_loaded_extensions() : [],
		];
	}

	private function plugins() :array {
		$enum = [];
		foreach ( Services::WpPlugins()->getPlugins() as $file => $p ) {
			$enum[ $file ] = [
				'name'    => $p[ 'Name' ] ?? '',
				'version' => $p[ 'Version' ] ?? '',
				'dir'     => \dirname( $file ),
				'active'  => (int)is_plugin_active( $file ),
			];
		}
		\ksort( $enum );
		return \array_values( $enum );
	}

	private function themes() :array {
		$enum = [];
		$active = Services::WpThemes()->getCurrent()->get_stylesheet();
		foreach ( Services::WpThemes()->getThemes() as $t ) {
			if ( $t instanceof \WP_Theme ) {
				$enum[ $t->get_stylesheet() ] = [
					'name'    => $t->get( 'Name' ),
					'dir'     => $t->get_stylesheet(),
					'version' => $t->get( 'Version' ),
					'active'  => $active === $t->get_stylesheet() ? 1 : 0,
				];
			}
		}
		\ksort( $enum );
		return \array_values( $enum );
	}

	private function ip() :string {
		$ip = '';
		$body = wp_remote_retrieve_body( wp_remote_get( 'https://ip-detect.workers.aptoweb.com' ) );
		if ( !empty( $body ) ) {
			$ip = \json_decode( $body, true )[ 'ip' ] ?? '';
		}
		return $ip;
	}

	private function caps() :array {
		try {
			$assess = ( new AssessDirWrite( self::con()->cache_dir_handler->dir() ) )->test();
			if ( \count( \array_filter( $assess ) ) !== 3 ) {
				throw new \Exception( 'Failed to write to temp' );
			}
			$canWrite = true;
		}
		catch ( \Exception $e ) {
			$canWrite = false;
		}

		return \array_merge(
			[
				'can_memory_limit'  => \function_exists( 'wp_is_ini_value_changeable' ) ? (int)wp_is_ini_value_changeable( 'memory_limit' ) : -1,
				'can_write_dir_tmp' => (int)$canWrite,
				'can_zip_archive'   => \class_exists( '\ZipArchive' ),
				'can_zip_pcl'       => $this->canPclZip(),
				'can_app_passwords' => \function_exists( 'wp_is_application_passwords_supported' ) ? (int)wp_is_application_passwords_supported() : -1,
			],
			$this->diskSpaceChecks( $canWrite )
		);
	}

	private function diskSpaceChecks( bool $previousSuccess ) :array {
		$tmpDir = self::con()->cache_dir_handler->dir();
		$checks = [];
		foreach ( $this->checkParams[ 'disk_space_checks' ] ?? [ 1024 => '1kb', 1048576 => '1mb', ] as $size => $tag ) {
			$path = path_join( $tmpDir, sprintf( 'test_write_%s.txt', $tag ) );
			if ( !empty( $path ) ) {
				$previousSuccess = $previousSuccess && ( new DummyFile( $path, $size ) )->withRandomBytes( true );
				$checks[ 'can_write_'.$tag ] = $previousSuccess;
			}
		}
		return $checks;
	}

	private function canPclZip() :bool {
		if ( !\class_exists( '\PclZip' ) ) {
			$lib = path_join( ABSPATH, 'wp-admin/includes/class-pclzip.php' );
			if ( \is_file( $lib ) ) {
				require_once( $lib );
			}
		}
		return \class_exists( '\PclZip' );
	}

	private function ini() :array {
		$result = [];
		foreach (
			[
				'error_log',
				'max_execution_time',
			] as $ini
		) {
			$result[ $ini ] = \ini_get( $ini );
		}
		return $result;
	}

	private function paths() :array {
		$wpContent = \defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : '';
		return [
			'wp_abspath'      => trailingslashit( ABSPATH ),
			'script_filename' => (string)Services::Request()->server( 'SCRIPT_FILENAME' ),
			'dir_content'     => (string)$wpContent,
			'dir_plugins'     => \defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : null,
			'dir_includes'    => path_join( $wpContent, \defined( 'WPINC' ) ? WPINC : '' ),
			'url_content'     => \defined( 'WP_CONTENT_URL' ) ? WP_CONTENT_URL : null,
			'WPINC'           => \defined( 'WPINC' ) ? WPINC : null,
			'icwp_plugin_dir' => self::con()->getRootDir(),
		];
	}
}