<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Wpv;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

class ResultsSet extends Base\ResultsSet {

	public function countUniqueSlugsForPluginsContext() :int {
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
	public function getAllResultsSetsForPluginsContext() :array {
		return $this->getAllResultsSetsForContext( 'plugins' );
	}

	/**
	 * Provides a collection of ResultsSets for Themes.
	 * @return ResultsSet[]
	 */
	public function getAllResultsSetsForThemesContext() :array {
		return $this->getAllResultsSetsForContext( 'themes' );
	}

	/**
	 * @param string $sContext
	 * @return ResultsSet[]
	 */
	public function getAllResultsSetsForContext( $sContext ) :array {
		$aCollection = [];
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
		$collection = [];
		foreach ( $this->getUniqueSlugs() as $slug ) {
			$results = $this->getResultsSetForSlug( $slug );
			if ( $results->hasItems() ) {
				$collection[ $slug ] = $results;
			}
		}
		ksort( $collection, SORT_NATURAL );
		return $collection;
	}

	/**
	 * @param string $slug
	 * @return ResultItem[]
	 */
	public function getItemsForSlug( $slug ) {
		return array_values( array_filter(
			$this->getItems(),
			function ( $item ) use ( $slug ) {
				/** @var ResultItem $item */
				return $item->slug == $slug;
			}
		) );
	}

	/**
	 * @param string $slug
	 * @return ResultsSet
	 */
	public function getResultsSetForSlug( $slug ) {
		$results = new ResultsSet();
		array_map(
			function ( $item ) use ( $results ) {
				/** @var ResultItem $item */
				$results->addItem( $item );
			},
			$this->getItemsForSlug( $slug )
		);
		return $results;
	}

	/**
	 * @return string[]
	 */
	public function getUniqueSlugs() {
		return array_unique( array_map(
			function ( $item ) {
				/** @var ResultItem $item */
				return $item->slug;
			},
			$this->getItems()
		) );
	}
}