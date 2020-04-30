<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Debug;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options;
use FernleafSystems\Wordpress\Services\Services;
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
	public function run() {
		return [
			[
				'WordPress'   => $this->getWordPress(),
				'Shield'      => $this->getShield(),
				'PHP'         => $this->getPHP(),
				'Environment' => $this->getEnv(),
			],
			[
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
		$oReq = Services::Request();

		$sSig = $oReq->server( 'SERVER_SIGNATURE' );
		return [
			'Host OS'          => PHP_OS,
			'Server Hostname'  => gethostname(),
			'Server IPs'       => implode( ', ', Services::IP()->getServerPublicIPs() ),
			'Server Signature' => empty( $sSig ) ? '-' : $sSig,
			'Server Name'      => $oReq->server( 'SERVER_NAME' ),
		];
	}

	/**
	 * @return array
	 */
	private function getPHP() {
		$oDP = Services::Data();

		$sPHP = $oDP->getPhpVersionCleaned();
		if ( $sPHP !== $oDP->getPhpVersion() ) {
			$sPHP .= sprintf( ' (%s)', $oDP->getPhpVersion() );
		}
		return [
			'PHP'           => $sPHP,
			'Memory Limit'  => ini_get( 'memory_limit' ),
			'32/64-bit'     => ( PHP_INT_SIZE === 4 ) ? 32 : 64,
			'Time Limit'    => ini_get( 'max_execution_time' ),
			'Dir Separator' => DIRECTORY_SEPARATOR,
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
	private function getShield() {
		$oCon = $this->getCon();
		$oModPlugin = $oCon->getModule_Plugin();

		$aD = [
			'Version'                 => $oCon->getVersion(),
			'PRO'                     => $oCon->isPremiumActive() ? 'Yes' : 'No',
			'Security Admin Enabled'  => $oCon->getModule_SecAdmin()->isEnabledSecurityAdmin() ? 'Yes' : 'No',
			'Can Handshake ShieldNET' => $oModPlugin->getShieldNetApiController()->canHandshake() ? 'Yes' : 'No',
		];

		$oPing = new Licenses\Keyless\Ping();
		$oPing->lookup_url_stub = $this->getOptions()->getDef( 'license_store_url_api' );
		$aD[ 'Ping License Server' ] = $oPing->ping() ? 'Yes' : 'No';

		/** @var Options $oOptsIP */
		$oOptsPlugin = $oModPlugin->getOptions();
		$sSource = $oOptsPlugin->getSelectOptionValueText( 'visitor_address_source' );
		$aD[ 'Visitor IP Source' ] = $sSource.' - '.Services::Request()->server( $sSource );

		$sTmpPath = $oCon->getPluginCachePath();
		$aD[ 'Can Write TMP DIR' ] = empty( $sTmpPath ) ? 'No' : 'Yes: '.$sTmpPath;

		return $aD;
	}

	/**
	 * @return array
	 */
	private function getWordPress() {
		$oWP = Services::WpGeneral();
		$aD = [
			'URL - Home' => $oWP->getHomeUrl(),
			'URL - Site' => $oWP->getWpUrl(),
			'WP'         => $oWP->getVersion( true ),
			'Locale'     => $oWP->getLocale(),
			'Multisite'  => $oWP->isMultisite() ? 'Yes' : 'No',
			'ABSPATH'    => ABSPATH,
		];
		if ( $oWP->isClassicPress() ) {
			$aD[ 'ClassicPress' ] = $oWP->getVersion();
		}

		return $aD;
	}
}
