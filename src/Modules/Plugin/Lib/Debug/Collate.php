<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Debug;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Utility\DbDescribeTable;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot\TestNotBotLoading;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Adhoc\WorldTimeApi;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Decorate\FormatBytes;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\ApiPing;
use FernleafSystems\Wordpress\Services\Utilities\Licenses;

class Collate {

	use PluginControllerConsumer;

	/**
	 * @return array[]
	 */
	public function run() :array {
		$pluginsActive = $this->getPlugins( true );
		$pluginsInactive = $this->getPlugins( false );
		$themes = $this->getThemes( true );
		return [
			sprintf( __( '%s Info', 'wp-simple-firewall' ), self::con()->labels->Name )    => [
				__( 'Summary', 'wp-simple-firewall' )      => $this->getShieldSummary(),
				__( 'Databases', 'wp-simple-firewall' )    => $this->getShieldDatabases(),
				__( 'Snapshots', 'wp-simple-firewall' )    => $this->snapshots(),
				__( 'Capabilities', 'wp-simple-firewall' ) => $this->getShieldCapabilities(),
			],
			__( 'System Info', 'wp-simple-firewall' )    => [
				__( 'PHP & MySQL', 'wp-simple-firewall' ) => $this->getPHP(),
				__( 'Environment', 'wp-simple-firewall' ) => $this->getEnv(),
			],
			__( 'WordPress Info', 'wp-simple-firewall' ) => [
				__( 'Summary', 'wp-simple-firewall' )                                            => $this->getWordPressSummary(),
				sprintf( __( 'Active Plugins (%s)', 'wp-simple-firewall' ), \count( $pluginsActive ) )     => $pluginsActive,
				sprintf( __( 'Inactive Plugins (%s)', 'wp-simple-firewall' ), \count( $pluginsInactive ) ) => $pluginsInactive,
				sprintf( __( 'Active Themes (%s)', 'wp-simple-firewall' ), \count( $themes ) )             => $themes,
			],
			__( 'Service IPs', 'wp-simple-firewall' )    => [
				__( 'Summary', 'wp-simple-firewall' ) => $this->getServiceIPs(),
			],
		];
	}

	private function getEnv() :array {
		$srvIP = Services::IP();
		$req = Services::Request();

		$sig = $req->server( 'SERVER_SIGNATURE' );
		$soft = $req->server( 'SERVER_SOFTWARE' );
		$IPs = $srvIP->getServerPublicIPs();
		$rDNS = '';
		foreach ( $IPs as $ip ) {
			if ( $srvIP->getIpVersion( $ip ) === 4 ) {
				$rDNS = \gethostbyaddr( $ip );
				break;
			}
		}

		$totalDisk = \function_exists( '\disk_total_space' ) ?
			( \disk_total_space( ABSPATH ) === false ? '-' : (int)\disk_total_space( ABSPATH ) ) : '-';
		$freeDisk = \function_exists( '\disk_free_space' ) ?
			( \disk_free_space( ABSPATH ) === false ? '-' : (int)\disk_free_space( ABSPATH ) ) : '-';

		try {
			$diff = ( new WorldTimeApi() )->diffServerWithReal();
		}
		catch ( \Exception $e ) {
			$diff = sprintf( __( 'Failed: %s', 'wp-simple-firewall' ), $e->getMessage() );
		}

		return [
			__( 'Host OS', 'wp-simple-firewall' )                => \PHP_OS,
			__( 'Server Hostname', 'wp-simple-firewall' )        => \gethostname(),
			__( 'Server Time Difference', 'wp-simple-firewall' ) => $diff,
			__( 'Server IPs', 'wp-simple-firewall' )             => \implode( ', ', $IPs ),
			__( 'CloudFlare', 'wp-simple-firewall' )             => !empty( $req->server( 'HTTP_CF_REQUEST_ID' ) )
				? __( 'No', 'wp-simple-firewall' )
				: __( 'Yes', 'wp-simple-firewall' ),
			__( 'rDNS', 'wp-simple-firewall' )                   => empty( $rDNS ) ? '-' : $rDNS,
			__( 'Server Name', 'wp-simple-firewall' )            => $req->server( 'SERVER_NAME' ),
			__( 'Server Signature', 'wp-simple-firewall' )       => empty( $sig ) ? '-' : $sig,
			__( 'Server Software', 'wp-simple-firewall' )        => empty( $soft ) ? '-' : $soft,
			__( 'Disk Space', 'wp-simple-firewall' )             => sprintf(
				__( '%1$s used out of %2$s (unused: %3$s)', 'wp-simple-firewall' ),
				( \is_numeric( $totalDisk ) && \is_numeric( $freeDisk ) ) ? FormatBytes::Format( $totalDisk - $freeDisk, 2, '' ) : '-',
				\is_numeric( $totalDisk ) ? FormatBytes::Format( $totalDisk, 2, '' ) : '-',
				\is_numeric( $freeDisk ) ? FormatBytes::Format( $freeDisk, 2, '' ) : '-'
			)
		];
	}

	private function getPHP() :array {
		$DP = Services::Data();
		$req = Services::Request();

		$phpV = $DP->getPhpVersionCleaned();
		if ( $phpV !== $DP->getPhpVersion() ) {
			$phpV .= sprintf( ' (%s)', $DP->getPhpVersion() );
		}

		$ext = \get_loaded_extensions();
		\natsort( $ext );

		$root = $req->server( 'DOCUMENT_ROOT' );
		return [
			__( 'PHP', 'wp-simple-firewall' )                     => $phpV,
			__( 'MySQL', 'wp-simple-firewall' )                   => Services::WpDb()->getMysqlServerInfo(),
			__( 'Memory Limit', 'wp-simple-firewall' )            => sprintf(
				/* translators: %1$s: ini memory limit, %2$s: WP memory limit */
				__( '%1$s (Constant <code>WP_MEMORY_LIMIT: %2$s</code>)', 'wp-simple-firewall' ),
				\ini_get( 'memory_limit' ),
				\defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : __( 'not defined', 'wp-simple-firewall' )
			),
			__( '32/64-bit', 'wp-simple-firewall' )               => ( \PHP_INT_SIZE === 4 ) ? 32 : 64,
			__( 'Time Limit', 'wp-simple-firewall' )              => \ini_get( 'max_execution_time' ),
			__( 'Dir Separator', 'wp-simple-firewall' )          => \DIRECTORY_SEPARATOR,
			__( 'Document Root', 'wp-simple-firewall' )           => empty( $root ) ? '-' : $root,
			__( 'Extensions', 'wp-simple-firewall' )              => \implode( ', ', $ext ),
		];
	}

	private function getPlugins( bool $filterByActive ) :array {
		$oWpPlugins = Services::WpPlugins();

		$data = [];

		foreach ( $oWpPlugins->getPluginsAsVo() as $VO ) {
			if ( $filterByActive === $VO->active ) {
				$data[ $VO->Name ] = sprintf(
					'%s / %s / %s',
					$VO->Version,
					$VO->active ? __( 'Active', 'wp-simple-firewall' ) : __( 'Deactivated', 'wp-simple-firewall' ),
					$VO->hasUpdate() ? __( 'Update Available', 'wp-simple-firewall' ) : __( 'No Update', 'wp-simple-firewall' )
				);
			}
		}

		return $data;
	}

	private function getThemes( bool $filterByActive ) :array {
		$WPT = Services::WpThemes();

		$data = [];

		foreach ( $WPT->getThemesAsVo() as $T ) {

			$tActive = $T->active ||
					   ( $WPT->isActiveThemeAChild() && ( $T->is_child || $T->is_parent ) );

			if ( $filterByActive == $tActive ) {
				$line = sprintf(
					'%s / %s / %s',
					$T->Version,
					$T->active ? __( 'Active', 'wp-simple-firewall' ) : __( 'Deactivated', 'wp-simple-firewall' ),
					$T->hasUpdate() ? __( 'Update Available', 'wp-simple-firewall' ) : __( 'No Update', 'wp-simple-firewall' )
				);

				if ( $WPT->isActiveThemeAChild() && ( $T->is_child || $T->is_parent ) ) {
					$line .= ' / '.( $T->is_parent ? __( 'Parent', 'wp-simple-firewall' ) : __( 'Child', 'wp-simple-firewall' ) );
				}
				$data[ $T->Name ] = $line;
			}
		}

		return $data;
	}

	private function getShieldDatabases() :array {
		$WPDB = Services::WpDb();
		$DBs = [];
		foreach ( self::con()->db_con->loadAll() as $dbhDef ) {
			/** @var Handler $dbh */
			$dbh = $dbhDef[ 'handler' ];
			$DBs[ $dbhDef[ 'def' ][ 'name' ] ] = sprintf( '<code>%s</code> | %s | %s | %s',
				$dbh->getTableSchema()->table,
				$dbh->isReady() ? __( 'Ready', 'wp-simple-firewall' ) : __( 'Not Ready', 'wp-simple-firewall' ),
				sprintf( __( 'Rows: %s', 'wp-simple-firewall' ), $dbh->isReady() ? $dbh->getQuerySelector()->count() : '-' ),
				self::con()->action_router->render( DbDescribeTable::class, [
					'show_table' => $WPDB->selectCustom( sprintf( 'DESCRIBE %s', $dbh->getTableSchema()->table ) )
				] )
			);
		}
		return $DBs;
	}

	private function snapshots() :array {
		$data = [];

		$auditCon = self::con()->comps->activity_log;
		foreach ( $auditCon->getAuditors() as $auditor ) {
			try {
				if ( $auditor->getSnapper() ) {
					$snapshot = $auditCon->getSnapshot( $auditor::Slug() );
					$data[ $auditor::Slug() ] = sprintf(
						/* translators: %1$s: entries count, %2$s: time difference */
						__( 'entries: %1$s (previous: %2$s)', 'wp-simple-firewall' ),
						\count( $snapshot->data ),
						Services::Request()->carbon( true )->setTimestamp( $snapshot->created_at )->diffForHumans()
					);
				}
			}
			catch ( \Exception $e ) {
				$data[ $auditor::Slug() ] = __( 'No snapshot required.', 'wp-simple-firewall' );
			}
		}

		return $data;
	}

	private function getShieldCapabilities() :array {
		$con = self::con();

		try {
			$loopback = $this->yesNo( $con->plugin->canSiteLoopback() );
		}
		catch ( \Exception $e ) {
			$loopback = __( 'Unknown - requires WP v5.4+', 'wp-simple-firewall' );
		}

		$data = [
			__( 'Can Loopback Request', 'wp-simple-firewall' )       => $loopback,
			__( 'NotBot Frontend JS Loading', 'wp-simple-firewall' ) => $this->yesNo( ( new TestNotBotLoading() )->test() ),
			sprintf( __( 'Handshake %s', 'wp-simple-firewall' ), $con->labels->getBrandName( 'shieldnet' ) ) => $this->yesNo( $con->comps->shieldnet->canHandshake() ),
			__( 'WP Hashes Ping', 'wp-simple-firewall' )             => $this->yesNo( ( new ApiPing() )->ping() ),
		];

		$data[ __( 'Ping License Server', 'wp-simple-firewall' ) ] =
			$this->yesNo( ( new Licenses\Keyless\Ping() )->ping() );

		$data[ __( 'Write TMP/Cache DIR', 'wp-simple-firewall' ) ] = $con->cache_dir_handler->exists() ?
			sprintf( __( 'Yes: %s', 'wp-simple-firewall' ), $con->cache_dir_handler->dir() ) :
			__( 'No', 'wp-simple-firewall' );

		return $data;
	}

	private function getShieldSummary() :array {
		$con = self::con();
		$wpHashes = $con->comps->api_token;

		$nPrevAttempt = $wpHashes->getPreviousAttemptAt();
		if ( empty( $nPrevAttempt ) ) {
			$sPrev = __( 'Never', 'wp-simple-firewall' );
		}
		else {
			$sPrev = sprintf( __( 'Last Attempt: %s', 'wp-simple-firewall' ), Services::Request()
											  ->carbon()
											  ->setTimestamp( $nPrevAttempt )
											  ->diffForHumans() );
		}

		$data = [
			__( 'Version', 'wp-simple-firewall' )                => $con->cfg->version(),
			__( 'PRO', 'wp-simple-firewall' )                    => $this->yesNo( $con->isPremiumActive() ),
			__( 'WP Hashes Token', 'wp-simple-firewall' )        => ( $wpHashes->hasToken() ? $wpHashes->getToken() : '' ).' ('.$sPrev.')',
			__( 'Security Admin Enabled', 'wp-simple-firewall' ) => $this->yesNo( $con->comps->sec_admin->isEnabledSecAdmin() ),
			__( 'CrowdSec API Status', 'wp-simple-firewall' )    => 'TODO', // $con->comps->crowdsec->getApi()->getAuthStatus(),
			__( 'TMP Dir', 'wp-simple-firewall' )                => $con->cache_dir_handler->dir(),
		];

		$source = __( 'unknown', 'wp-simple-firewall' );
		foreach ( $con->opts->optDef( 'visitor_address_source' )[ 'value_options' ] as $optionValue ) {
			if ( $optionValue[ 'value_key' ] == $con->opts->optGet( 'visitor_address_source' ) ) {
				$source = $optionValue[ 'text' ];
				break;
			}
		}

		$ip = Services::Request()->ip();
		$data[ __( 'Visitor IP Source', 'wp-simple-firewall' ) ] =
			$source.': '.( empty( $ip ) ? __( 'n/a', 'wp-simple-firewall' ) : $ip );

		return $data;
	}

	private function getServiceIPs() :array {
		return [
			'ips' => var_export( Services::ServiceProviders()->getProviders(), true ),
		];
	}

	private function getWordPressSummary() :array {
		$WP = Services::WpGeneral();
		$data = [
			__( 'URL - Home', 'wp-simple-firewall' ) => $WP->getHomeUrl(),
			__( 'URL - Site', 'wp-simple-firewall' ) => $WP->getWpUrl(),
			__( 'WP', 'wp-simple-firewall' )         => $WP->getVersion( true ),
		];

		return \array_merge( $data, [
			__( 'URL - Home', 'wp-simple-firewall' )  => $WP->getHomeUrl(),
			__( 'URL - Site', 'wp-simple-firewall' )  => $WP->getWpUrl(),
			__( 'WP', 'wp-simple-firewall' )          => $WP->getVersion( true ),
			__( 'Locale', 'wp-simple-firewall' )      => $WP->getLocale(),
			__( 'Multisite', 'wp-simple-firewall' )   => $this->yesNo( $WP->isMultisite() ),
			__( 'ABSPATH', 'wp-simple-firewall' )     => ABSPATH,
			__( 'Debug Is On', 'wp-simple-firewall' ) => $this->yesNo( $WP->isDebug() ),
			__( 'Database', 'wp-simple-firewall' )    => [
				sprintf( __( 'Host: <code>%s</code>', 'wp-simple-firewall' ), DB_HOST ),
				sprintf( __( 'Name: <code>%s</code>', 'wp-simple-firewall' ), DB_NAME ),
				sprintf( __( 'User: <code>%s</code>', 'wp-simple-firewall' ), DB_USER ),
				sprintf( __( 'Prefix: <code>%s</code>', 'wp-simple-firewall' ), Services::WpDb()->getPrefix() ),
			],
		] );
	}

	private function yesNo( bool $value ) :string {
		return $value ? __( 'Yes', 'wp-simple-firewall' ) : __( 'No', 'wp-simple-firewall' );
	}
}
