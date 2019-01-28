<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv\WpVulnDb\RetrieveForItem;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv\WpVulnDb\WpVulnVO;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class Scanner
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv
 */
class Scanner {

	/**
	 * @return ResultsSet
	 */
	public function run() {
		$oResultSet = new ResultsSet();

		foreach ( $this->getAllPluginVulnerabilities() as $sFile => $aVulnerabilities ) {
			foreach ( $aVulnerabilities as $oVo ) {
				$oItem = new ResultItem();
				$oItem->slug = $sFile;
				$oItem->context = 'plugins';
				$oItem->wpvuln_id = $oVo->id;
				$oItem->wpvuln_vo = $oVo->getRawDataAsArray();
				$oResultSet->addItem( $oItem );
			}
		}

		foreach ( $this->getAllThemeVulnerabilities() as $sFile => $aVulnerabilities ) {
			foreach ( $aVulnerabilities as $oVo ) {
				$oItem = new ResultItem();
				$oItem->slug = $sFile;
				$oItem->context = 'themes';
				$oItem->wpvuln_id = $oVo->id;
				$oItem->wpvuln_vo = $oVo->getRawDataAsArray();
				$oResultSet->addItem( $oItem );
			}
		}

		return $oResultSet;
	}

	/**
	 * @return WpVulnVO[][]
	 */
	protected function getAllPluginVulnerabilities() {
		$aVulns = array();
		foreach ( Services::WpPlugins()->getInstalledPluginFiles() as $sFile ) {
			$aVulns[ $sFile ] = $this->getPluginVulnerabilities( $sFile );
		}
		return array_filter( $aVulns );
	}

	/**
	 * @return WpVulnVO[][]
	 */
	protected function getAllThemeVulnerabilities() {
		$aVulns = array();
		$oWpThemes = Services::WpThemes();

		$oActiveTheme = $oWpThemes->getCurrent();
		$aThemes = array(
			$oActiveTheme->get_stylesheet() => $oActiveTheme->get_stylesheet()
		);
		if ( $oWpThemes->isActiveThemeAChild() ) { // is child theme
			$oParent = $oWpThemes->getCurrentParent();
			$aThemes[ $oParent->get_stylesheet() ] = $oParent->get_stylesheet();
		}

		foreach ( $aThemes as $sBaseFile => $sSlug ) {
			$aVulns[ $sBaseFile ] = $this->getThemeVulnerabilities( $sBaseFile );
		}
		return array_filter( $aVulns );
	}

	/**
	 * @param string $sFile
	 * @return WpVulnVO[]
	 */
	public function getPluginVulnerabilities( $sFile ) {
		$aVulns = array();
		$oWpPlugins = Services::WpPlugins();

		$sSlug = $oWpPlugins->getSlug( $sFile );
		if ( empty( $sSlug ) ) {
			$sSlug = dirname( $sFile );
		}
		try {
			$aVos = ( new RetrieveForItem() )->setContext( 'plugins' )
											 ->setSlug( $sSlug )
											 ->retrieve();
			$oPlugin = $oWpPlugins->getPluginAsVo( $sFile );
			$aVulns = array_filter(
				$aVos,
				function ( $oVo ) use ( $oPlugin ) {
					/** @var WpVulnVO $oVo */
					$sFixed = $oVo->fixed_in;
					return ( empty ( $sFixed ) || version_compare( $oPlugin->Version, $sFixed, '<' ) );
				}
			);
		}
		catch ( \Exception $oE ) {
		}
		return $aVulns;
	}

	/**
	 * @param string $sSlug
	 * @return WpVulnVO[]
	 */
	public function getThemeVulnerabilities( $sSlug ) {
		$aVulns = array();
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
}