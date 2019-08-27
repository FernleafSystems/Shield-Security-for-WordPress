<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv\WpVulnDb\RetrieveForItem;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv\WpVulnDb\WpVulnVO;

/**
 * Class ItemScanner
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv
 */
abstract class ItemScanner {

	use Shield\Scans\Common\ScanActionConsumer;

	/**
	 * @param string $sFile
	 * @return ResultsSet|null
	 */
	abstract public function scan( $sFile );

	/**
	 * @param WpVulnVO[] $aVos
	 * @param string     $sCurrentVersion
	 * @return WpVulnVO[]
	 */
	protected function filterAgainstVersion( $aVos, $sCurrentVersion ) {
		$sCurrentVersion = trim( $sCurrentVersion, 'v' );
		return array_filter(
			$aVos,
			function ( $oVo ) use ( $sCurrentVersion ) {
				/** @var WpVulnVO $oVo */
				$sFixed = $oVo->fixed_in;
				return ( empty ( $sFixed ) || version_compare( $sCurrentVersion, $sFixed, '<' ) );
			}
		);
	}

	/**
	 * @param string $sSlug
	 * @param string $sContext
	 * @return WpVulnVO[]
	 */
	protected function retrieveForSlug( $sSlug, $sContext = 'plugins' ) {
		return ( new RetrieveForItem() )
			->setContext( $sContext )
			->setSlug( $sSlug )
			->retrieve();
	}
}