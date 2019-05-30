<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv\WpVulnDb\RetrieveForItem;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv\WpVulnDb\WpVulnVO;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Scanner
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Apc
 */
class Scanner {

	/**
	 * @var
	 */
	private $nAbandonedLimit;

	/**
	 * @return ResultsSet
	 */
	public function run() {
		$oResultSet = new ResultsSet();

		foreach ( $this->getAllAbandonedPlugins() as $sFile => $nLastUpdatedAt ) {
			$oItem = new ResultItem();
			$oItem->slug = $sFile;
			$oItem->context = 'plugins';
			$oItem->last_updated_at = $nLastUpdatedAt;
			$oResultSet->addItem( $oItem );
		}

		return $oResultSet;
	}

	/**
	 * @return array - keys are plugin base files, values are last_updated timestamp
	 */
	private function getAllAbandonedPlugins() {
		$aAbandoned = [];

		$oWpPlugins = Services::WpPlugins();
		foreach ( $oWpPlugins->getInstalledPluginFiles() as $sFile ) {
			if ( $oWpPlugins->isWpOrg( $sFile ) ) {
				$aAbandoned[ $sFile ] = $this->getAbandonedTime( $sFile );
			}
		}
		return array_filter( $aAbandoned );
	}

	/**
	 * @return array - keys are plugin base files, values are last_updated timestamp
	 */
	private function getAllAbandonedThemes() {
		$aAbandoned = [];

		$oWp = Services::WpThemes();
		foreach ( $oWp->getThemes() as $oTheme ) {
			if ( $oWp->isWpOrg( $oTheme ) ) {
				$aAbandoned[ $oTheme->get_stylesheet() ] = $this->getAbandonedTime( $oTheme );
			}
		}
		return array_filter( $aAbandoned );
	}

	/**
	 * @param string $sFile
	 * @return bool
	 */
	private function getAbandonedTime( $sFile ) {
		$nTime = 0;
		$oWpPlugins = Services::WpPlugins();

		$sSlug = $oWpPlugins->getSlug( $sFile );
		if ( empty( $sSlug ) ) {
			$sSlug = dirname( $sFile );
		}

		if ( !function_exists( 'plugins_api' ) ) {
			require_once ABSPATH.'/wp-admin/includes/plugin-install.php';
		}
		$oApi = plugins_api( 'plugin_information', [
			'slug'   => $sSlug,
			'fields' => [
				'sections' => false,
			],
		] );
		if ( isset( $oApi->last_updated ) ) {
			$nLastUpdateAt = strtotime( $oApi->last_updated );
			if ( Services::Request()->ts() - $nLastUpdateAt > $this->getAbandonedLimit() ) {
				$nTime = $nLastUpdateAt;
			}
		}

		return $nTime;
	}

	/**
	 * @param string $sSlug
	 * @return WpVulnVO[]
	 */
	private function getThemeVulnerabilities( $sSlug ) {
		$aVulns = [];
		$oWpThemes = Services::WpThemes();

		try {
			$aVos = ( new RetrieveForItem() )->setContext( 'themes' )
											 ->setSlug( $sSlug )
											 ->retrieve();
			$oTheme = $oWpThemes->getTheme( $sSlug );
			$aVulns = array_filter(
				$aVos,
				function ( $oVo ) use ( $oTheme ) {
					/** @var WpVulnVO $oVo */
					$sFixed = $oVo->fixed_in;
					return ( empty ( $sFixed ) || version_compare( $oTheme->get( 'Version' ), $sFixed, '<' ) );
				}
			);
		}
		catch ( \Exception $oE ) {
		}

		return $aVulns;
	}

	/**
	 * @return int
	 */
	private function getAbandonedLimit() {
		return isset( $this->nAbandonedLimit ) ? $this->nAbandonedLimit : YEAR_IN_SECONDS*2;
	}
}