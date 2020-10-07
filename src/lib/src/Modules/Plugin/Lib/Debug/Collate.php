<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Debug;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\FormatBytes;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\ApiPing;
use FernleafSystems\Wordpress\Services\Utilities\Licenses;

/**
 * Class Collate
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Debug
 */
class Collate {

	use ModConsumer;

	/**
	 * @return array[]
	 */
	public function run() :array {
		return [
			'Shield Info'    => [
				'Summary'      => $this->getShieldSummary(),
				'Integrity'    => $this->getShieldIntegrity(),
				'Capabilities' => $this->getShieldCapabilities(),
			],
			'System Info'    => [
				'PHP'         => $this->getPHP(),
				'Environment' => $this->getEnv(),
			],
			'WordPress Info' => [
				'Summary'            => $this->getWordPressSummary(),
				'Plugins (Active)'   => $this->getPlugins( true ),
				'Plugins (Inactive)' => $this->getPlugins( false ),
				'Themes (Active)'    => $this->getThemes( true ),
			],
		];
	}

	/**
	 * @return array
	 */
	private function getEnv() {
		$srvIP = Services::IP();
		$oReq = Services::Request();

		$sig = $oReq->server( 'SERVER_SIGNATURE' );
		$soft = $oReq->server( 'SERVER_SOFTWARE' );
		$aIPs = $srvIP->getServerPublicIPs();
		$rDNS = '';
		foreach ( $aIPs as $ip ) {
			if ( $srvIP->getIpVersion( $ip ) === 4 ) {
				$rDNS = gethostbyaddr( $ip );
				break;
			}
		}

		$totalDisk = disk_total_space( ABSPATH );
		$freeDisk = disk_free_space( ABSPATH );
		return [
			'Host OS'                           => PHP_OS,
			'Server Hostname'                   => gethostname(),
			'Server IPs'                        => implode( ', ', $aIPs ),
			'rDNS'                              => empty( $rDNS ) ? '-' : $rDNS,
			'Server Name'                       => $oReq->server( 'SERVER_NAME' ),
			'Server Signature'                  => empty( $sig ) ? '-' : $sig,
			'Server Software'                   => empty( $soft ) ? '-' : $soft,
			'Disk Space (Used/Available/Total)' => sprintf( '%s / %s / %s',
				FormatBytes::Format( $totalDisk - $freeDisk, 2, '' ),
				FormatBytes::Format( $freeDisk, 2, '' ),
				FormatBytes::Format( $totalDisk, 2, '' )
			)
		];
	}

	/**
	 * @return array
	 */
	private function getPHP() {
		$oDP = Services::Data();
		$req = Services::Request();

		$sPHP = $oDP->getPhpVersionCleaned();
		if ( $sPHP !== $oDP->getPhpVersion() ) {
			$sPHP .= sprintf( ' (%s)', $oDP->getPhpVersion() );
		}

		$ext = get_loaded_extensions();
		natsort( $ext );

		$root = $req->server( 'DOCUMENT_ROOT' );
		return [
			'PHP'           => $sPHP,
			'Memory Limit'  => ini_get( 'memory_limit' ),
			'32/64-bit'     => ( PHP_INT_SIZE === 4 ) ? 32 : 64,
			'Time Limit'    => ini_get( 'max_execution_time' ),
			'Dir Separator' => DIRECTORY_SEPARATOR,
			'Document Root' => empty( $root ) ? '-' : $root,
			'Extensions'    => implode( ', ', $ext ),
		];
	}

	/**
	 * @param bool $bActive
	 * @return array
	 */
	private function getPlugins( $bActive ) {
		$oWpPlugins = Services::WpPlugins();

		$aD = [];

		foreach ( $oWpPlugins->getPluginsAsVo() as $oVO ) {
			if ( $bActive === $oVO->active ) {
				$aD[ $oVO->Name ] = sprintf( '%s / %s / %s',
					$oVO->Version, $oVO->active ? 'Active' : 'Deactivated',
					$oVO->hasUpdate() ? 'Update Available' : 'No Update'
				);
			}
		}

		return array_merge(
			[ 'Total' => count( $aD ), ],
			$aD
		);
	}

	/**
	 * @param bool $bActive
	 * @return array
	 */
	private function getThemes( $bActive ) {
		$oWpT = Services::WpThemes();

		$aD = [];

		foreach ( $oWpT->getThemesAsVo() as $oVO ) {

			$bIsActive = $oVO->active ||
						 ( $oWpT->isActiveThemeAChild() && ( $oVO->is_child || $oVO->is_parent ) );

			if ( $bActive == $bIsActive ) {
				$sLine = sprintf( '%s / %s / %s',
					$oVO->Version, $oVO->active ? 'Active' : 'Deactivated',
					$oVO->hasUpdate() ? 'Update Available' : 'No Update'
				);

				if ( $oWpT->isActiveThemeAChild() && ( $oVO->is_child || $oVO->is_parent ) ) {
					$sLine .= ' / '.( $oVO->is_parent ? 'Parent' : 'Child' );
				}
				$aD[ $oVO->Name ] = $sLine;
			}
		}

		return array_merge(
			[ 'Total' => count( $aD ), ],
			$aD
		);
	}

	/**
	 * @return array
	 */
	private function getShieldIntegrity() {
		$con = $this->getCon();
		$DB = Services::WpDb();
		$data = [];

		$dbh = $con->getModule_Sessions()->getDbHandler_Sessions();
		$data[ 'DB Table: Sessions' ] = $dbh->isReady() ?
			sprintf( '%s (rows: ~%s)', 'Ready', $dbh->getQuerySelector()->count() )
			: 'Missing';

		$dbh = $con->getModule_IPs()->getDbHandler_IPs();
		$data[ 'DB Table: IP' ] = $dbh->isReady() ?
			sprintf( '%s (rows: ~%s)', 'Ready', $dbh->getQuerySelector()->count() )
			: 'Missing';

		$dbh = $con->getModule_HackGuard()->getDbHandler_ScanResults();
		$data[ 'DB Table: Scan' ] = $dbh->isReady() ?
			sprintf( '%s (rows: ~%s)', 'Ready', $dbh->getQuerySelector()->count() )
			: 'Missing';

		$dbh = $con->getModule_Traffic()->getDbHandler_Traffic();
		$data[ 'DB Table: Traffic' ] = $dbh->isReady() ?
			sprintf( '%s (rows: ~%s)', 'Ready', $dbh->getQuerySelector()->count() )
			: 'Missing';

		$dbh = $con->getModule_Events()->getDbHandler_Events();
		$data[ 'DB Table: Events' ] = $dbh->isReady() ?
			sprintf( '%s (rows: ~%s)', 'Ready', $dbh->getQuerySelector()->count() )
			: 'Missing';

		return $data;
	}

	/**
	 * @return array
	 */
	private function getShieldCapabilities() {
		$con = $this->getCon();
		$modPlug = $con->getModule_Plugin();

		$sHome = Services::WpGeneral()->getHomeUrl();
		$data = [
			sprintf( 'Loopback To %s', $sHome ) => $modPlug->getCanSiteCallToItself() ? 'Yes' : 'No',
			'Handshake ShieldNET'               => $modPlug->getShieldNetApiController()
														   ->canHandshake() ? 'Yes' : 'No',
			'WP Hashes Ping'                    => ( new ApiPing() )->ping() ? 'Yes' : 'No',
		];

		$oPing = new Licenses\Keyless\Ping();
		$oPing->lookup_url_stub = $this->getOptions()->getDef( 'license_store_url_api' );
		$data[ 'Ping License Server' ] = $oPing->ping() ? 'Yes' : 'No';

		$sTmpPath = $con->getPluginCachePath();
		$data[ 'Write TMP DIR' ] = empty( $sTmpPath ) ? 'No' : 'Yes: '.$sTmpPath;

		return $data;
	}

	/**
	 * @return array
	 */
	private function getShieldSummary() {
		$oCon = $this->getCon();
		$oModLicense = $oCon->getModule_License();
		$oModPlugin = $oCon->getModule_Plugin();
		$oWpHashes = $oModLicense->getWpHashesTokenManager();

		$nPrevAttempt = $oWpHashes->getPreviousAttemptAt();
		if ( empty( $nPrevAttempt ) ) {
			$sPrev = 'Never';
		}
		else {
			$sPrev = 'Last Attempt: '.Services::Request()
											  ->carbon()
											  ->setTimestamp( $nPrevAttempt )
											  ->diffForHumans();
		}

		$aD = [
			'Version'                => $oCon->getVersion(),
			'PRO'                    => $oCon->isPremiumActive() ? 'Yes' : 'No',
			'WP Hashes Token'        => ( $oWpHashes->hasToken() ? $oWpHashes->getToken() : '' ).' ('.$sPrev.')',
			'Security Admin Enabled' => $oCon->getModule_SecAdmin()->isEnabledSecurityAdmin() ? 'Yes' : 'No',
		];

		/** @var Options $oOptsIP */
		$oOptsPlugin = $oModPlugin->getOptions();
		$sSource = $oOptsPlugin->getSelectOptionValueText( 'visitor_address_source' );
		$aD[ 'Visitor IP Source' ] = $sSource.' - '.Services::Request()->server( $sSource );

		return $aD;
	}

	/**
	 * @return array
	 */
	private function getWordPressSummary() {
		$WP = Services::WpGeneral();
		$data = [
			'URL - Home'  => $WP->getHomeUrl(),
			'URL - Site'  => $WP->getWpUrl(),
			'WP'          => $WP->getVersion( true ),
			'Locale'      => $WP->getLocale(),
			'Multisite'   => $WP->isMultisite() ? 'Yes' : 'No',
			'ABSPATH'     => ABSPATH,
			'Debug Is On' => $WP->isDebug() ? 'Yes' : 'No',
			'Database'    => [
				sprintf( 'Name: %s', DB_NAME ),
				sprintf( 'User: %s', DB_USER ),
				sprintf( 'Prefix: %s', Services::WpDb()->getPrefix() ),
			],
		];
		if ( $WP->isClassicPress() ) {
			$data[ 'ClassicPress' ] = $WP->getVersion();
		}

		return $data;
	}
}
