<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

/**
 * Class ResultsSet
 * @property ResultItem[] $aItems
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv
 */
class ResultsSet extends Base\BaseResultsSet {

	/**
	 * @return int
	 */
	public function countUniqueSlugs() {
		return count( $this->getAllResultsSetsForUniqueSlugs() );
	}

	/**
	 * @return int
	 */
	public function countUniqueSlugsForPluginsContext() {
		return count( $this->getAllResultsSetsForPluginsContext() );
	}

	/**
	 * @return int
	 */
	public function countUniqueSlugsForThemesContext() {
		return count( $this->getAllResultsSetsForThemesContext() );
	}

	/**
	 * Provides a collection of ResultsSets for Plugins.
	 * @return ResultsSet[]
	 */
	public function getAllResultsSetsForPluginsContext() {
		return $this->getAllResultsSetsForContext( 'plugins' );
	}

	/**
	 * Provides a collection of ResultsSets for Themes.
	 * @return ResultsSet[]
	 */
	public function getAllResultsSetsForThemesContext() {
		return $this->getAllResultsSetsForContext( 'themes' );
	}

	/**
	 * @param string $sContext
	 * @return ResultsSet[]
	 */
	public function getAllResultsSetsForContext( $sContext ) {
		$aCollection = array();
		foreach ( $this->getAllResultsSetsForUniqueSlugs() as $sSlug => $oRS ) {
			if ( $oRS->getItems()[ 0 ]->context == $sContext ) {
				$aCollection[ $sSlug ] = $oRS;
			}
		}
		return $aCollection;
	}

	/**
	 * @return ResultsSet[]
	 */
	public function getAllResultsSetsForUniqueSlugs() {
		$aCollection = array();
		foreach ( $this->getUniqueSlugs() as $sSlug ) {
			$oRS = $this->getResultsSetForSlug( $sSlug );
			if ( $oRS->hasItems() ) {
				$aCollection[ $sSlug ] = $oRS;
			}
		}
		ksort( $aCollection, SORT_NATURAL );
		return $aCollection;
	}

	/**
	 * @param string $sSlug
	 * @return ResultItem[]
	 */
	public function getItemsForSlug( $sSlug ) {
		return array_values( array_filter(
			$this->getItems(),
			function ( $oItem ) use ( $sSlug ) {
				/** @var ResultItem $oItem */
				return $oItem->slug == $sSlug;
			}
		) );
	}

	/**
	 * @param string $sSlug
	 * @return ResultsSet
	 */
	public function getResultsSetForSlug( $sSlug ) {
		$oRes = new ResultsSet();
		array_map(
			function ( $oItem ) use ( $oRes ) {
				/** @var ResultItem $oItem */
				$oRes->addItem( $oItem );
			},
			$this->getItemsForSlug( $sSlug )
		);
		return $oRes;
	}

	/**
	 * @return string[]
	 */
	public function getUniqueSlugs() {
		return array_unique( array_map(
			function ( $oItem ) {
				/** @var ResultItem $oItem */
				return $oItem->slug;
			},
			$this->getItems()
		) );
	}
}