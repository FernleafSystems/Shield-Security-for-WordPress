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
			'Shield Info'    => [
				'Summary'      => $this->getShieldSummary(),
				'Databases'    => $this->getShieldDatabases(),
				'Snapshots'    => $this->snapshots(),
				'Capabilities' => $this->getShieldCapabilities(),
			],
			'System Info'    => [
				'PHP & MySQL' => $this->getPHP(),
				'Environment' => $this->getEnv(),
			],
			'WordPress Info' => [
				'Summary'                                                      => $this->getWordPressSummary(),
				sprintf( 'Active Plugins (%s)', \count( $pluginsActive ) )     => $pluginsActive,
				sprintf( 'Inactive Plugins (%s)', \count( $pluginsInactive ) ) => $pluginsInactive,
				sprintf( 'Active Themes (%s)', \count( $themes ) )             => $themes,
			],
			'Service IPs'    => [
				'Summary' => $this->getServiceIPs(),
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
			$diff = 'failed: '.$e->getMessage();
		}

		return [
			'Host OS'                => \PHP_OS,
			'Server Hostname'        => \gethostname(),
			'Server Time Difference' => $diff,
			'Server IPs'             => \implode( ', ', $IPs ),
			'CloudFlare'             => !empty( $req->server( 'HTTP_CF_REQUEST_ID' ) ) ? 'No' : 'Yes',
			'rDNS'                   => empty( $rDNS ) ? '-' : $rDNS,
			'Server Name'            => $req->server( 'SERVER_NAME' ),
			'Server Signature'       => empty( $sig ) ? '-' : $sig,
			'Server Software'        => empty( $soft ) ? '-' : $soft,
			'Disk Space'             => sprintf( '%s used out of %s (unused: %s)',
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
			'PHP'           => $phpV,
			'MySQL'         => Services::WpDb()->getMysqlServerInfo(),
			'Memory Limit'  => sprintf( '%s (Constant <code>WP_MEMORY_LIMIT: %s</code>)', \ini_get( 'memory_limit' ),
				defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : 'not defined' ),
			'32/64-bit'     => ( \PHP_INT_SIZE === 4 ) ? 32 : 64,
			'Time Limit'    => \ini_get( 'max_execution_time' ),
			'Dir Separator' => \DIRECTORY_SEPARATOR,
			'Document Root' => empty( $root ) ? '-' : $root,
			'Extensions'    => \implode( ', ', $ext ),
		];
	}

	private function getPlugins( bool $filterByActive ) :array {
		$oWpPlugins = Services::WpPlugins();

		$data = [];

		foreach ( $oWpPlugins->getPluginsAsVo() as $VO ) {
			if ( $filterByActive === $VO->active ) {
				$data[ $VO->Name ] = sprintf( '%s / %s / %s',
					$VO->Version, $VO->active ? 'Active' : 'Deactivated',
					$VO->hasUpdate() ? 'Update Available' : 'No Update'
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
				$line = sprintf( '%s / %s / %s',
					$T->Version, $T->active ? 'Active' : 'Deactivated',
					$T->hasUpdate() ? 'Update Available' : 'No Update'
				);

				if ( $WPT->isActiveThemeAChild() && ( $T->is_child || $T->is_parent ) ) {
					$line .= ' / '.( $T->is_parent ? 'Parent' : 'Child' );
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
				$dbh->isReady() ? 'Ready' : 'Not Ready',
				sprintf( 'Rows: %s', $dbh->isReady() ? $dbh->getQuerySelector()->count() : '-' ),
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
					$data[ $auditor::Slug() ] = sprintf( 'entries: %s (previous: %s)',
						\count( $snapshot->data ),
						Services::Request()->carbon( true )->setTimestamp( $snapshot->created_at )->diffForHumans()
					);
				}
			}
			catch ( \Exception $e ) {
				$data[ $auditor::Slug() ] = 'No snapshot required.';
			}
		}

		return $data;
	}

	private function getShieldCapabilities() :array {
		$con = self::con();

		try {
			$loopback = $con->plugin->canSiteLoopback() ? 'Yes' : 'No';
		}
		catch ( \Exception $e ) {
			$loopback = 'Unknown - requires WP v5.4+';
		}

		$data = [
			'Can Loopback Request'       => $loopback,
			'NotBot Frontend JS Loading' => ( new TestNotBotLoading() )->test() ? 'Yes' : 'No',
			'Handshake ShieldNET'        => $con->comps->shieldnet->canHandshake() ? 'Yes' : 'No',
			'WP Hashes Ping'             => ( new ApiPing() )->ping() ? 'Yes' : 'No',
		];

		$data[ 'Ping License Server' ] = ( new Licenses\Keyless\Ping() )->ping() ? 'Yes' : 'No';

		$data[ 'Write TMP/Cache DIR' ] = $con->cache_dir_handler->exists() ? 'Yes: '.$con->cache_dir_handler->dir() : 'No';

		return $data;
	}

	private function getShieldSummary() :array {
		$con = self::con();
		$wpHashes = $con->comps->api_token;

		$nPrevAttempt = $wpHashes->getPreviousAttemptAt();
		if ( empty( $nPrevAttempt ) ) {
			$sPrev = 'Never';
		}
		else {
			$sPrev = 'Last Attempt: '.Services::Request()
											  ->carbon()
											  ->setTimestamp( $nPrevAttempt )
											  ->diffForHumans();
		}

		$data = [
			'Version'                => $con->cfg->version(),
			'PRO'                    => $con->isPremiumActive() ? 'Yes' : 'No',
			'WP Hashes Token'        => ( $wpHashes->hasToken() ? $wpHashes->getToken() : '' ).' ('.$sPrev.')',
			'Security Admin Enabled' => $con->comps->sec_admin->isEnabledSecAdmin() ? 'Yes' : 'No',
			'CrowdSec API Status'    => 'TODO', // $con->comps->crowdsec->getApi()->getAuthStatus(),
			'TMP Dir'                => $con->cache_dir_handler->dir(),
		];

		$source = 'unknown';
		foreach ( $con->opts->optDef( 'visitor_address_source' )[ 'value_options' ] as $optionValue ) {
			if ( $optionValue[ 'value_key' ] == $con->opts->optGet( 'visitor_address_source' ) ) {
				$source = $optionValue[ 'text' ];
				break;
			}
		}

		$ip = Services::Request()->ip();
		$data[ 'Visitor IP Source' ] = $source.': '.( empty( $ip ) ? 'n/a' : $ip );

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
			'URL - Home' => $WP->getHomeUrl(),
			'URL - Site' => $WP->getWpUrl(),
			'WP'         => $WP->getVersion( true ),
		];

		return \array_merge( $data, [
			'URL - Home'  => $WP->getHomeUrl(),
			'URL - Site'  => $WP->getWpUrl(),
			'WP'          => $WP->getVersion( true ),
			'Locale'      => $WP->getLocale(),
			'Multisite'   => $WP->isMultisite() ? 'Yes' : 'No',
			'ABSPATH'     => ABSPATH,
			'Debug Is On' => $WP->isDebug() ? 'Yes' : 'No',
			'Database'    => [
				sprintf( 'Host: <code>%s</code>', DB_HOST ),
				sprintf( 'Name: <code>%s</code>', DB_NAME ),
				sprintf( 'User: <code>%s</code>', DB_USER ),
				sprintf( 'Prefix: <code>%s</code>', Services::WpDb()->getPrefix() ),
			],
		] );
	}
}