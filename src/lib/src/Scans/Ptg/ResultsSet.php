<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

/**
 * Class ResultsSet
 * @property ResultItem[] $items
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg
 */
class ResultsSet extends Base\ResultsSet {

	/**
	 * @var string
	 */
	protected $sContext;

	/**
	 * @return int
	 */
	public function countDifferent() {
		return count( $this->getDifferentItems() );
	}

	/**
	 * @return int
	 */
	public function countMissing() {
		return count( $this->getMissingItems() );
	}

	/**
	 * @return int
	 */
	public function countUnrecognised() {
		return count( $this->getUnrecognisedItems() );
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
	 * Tried using array_map() but this DID NOT work
	 * Provides an array of Results Sets for each unique slug. Array keys are the slugs.
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
	 * Provides a collection of ResultsSets for Plugins.
	 * @return ResultsSet[]
	 */
	public function getAllResultsSetsForPluginsContext() {
		return $this->getAllResultsSetsForContext( ScanActionVO::CONTEXT_PLUGINS );
	}

	/**
	 * @param string $context
	 * @return ResultsSet
	 */
	public function getResultsForContext( $context ) {
		$results = new ResultsSet();
		foreach ( $this->getAllItems() as $item ) {
			/** @var ResultItem $item */
			if ( $item->context == $context ) {
				$results->addItem( $item );
			}
		}
		return $results;
	}

	/**
	 * @return ResultsSet
	 */
	public function getResultsForPluginsContext() {
		return $this->getResultsForContext( ScanActionVO::CONTEXT_PLUGINS );
	}

	/**
	 * @return ResultsSet
	 */
	public function getResultsForThemesContext() {
		return $this->getResultsForContext( ScanActionVO::CONTEXT_THEMES );
	}

	/**
	 * Provides a collection of ResultsSets for Themes.
	 * @return ResultsSet[]
	 */
	public function getAllResultsSetsForThemesContext() {
		return $this->getAllResultsSetsForContext( ScanActionVO::CONTEXT_THEMES );
	}

	/**
	 * Tried using array_filter() but this DID NOT work
	 * Provides a collection of ResultsSets for a particular context.
	 * @param string $sContext
	 * @return ResultsSet[]
	 */
	public function getAllResultsSetsForContext( $sContext ) {
		$aCollection = [];
		foreach ( $this->getAllResultsSetsForUniqueSlugs() as $sSlug => $oRS ) {
			if ( $oRS->getItems()[ 0 ]->context == $sContext ) {
				$aCollection[ $sSlug ] = $oRS;
			}
		}
		return $aCollection;
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

	/**
	 * @param ResultItem[] $aItems
	 * @return string[]
	 */
	public function filterItemsForPaths( $aItems ) {
		return array_map(
			function ( $oItem ) {
				/** @var ResultItem $oItem */
				return $oItem->path_full;
			},
			$aItems
		);
	}

	/**
	 * @return ResultItem[]
	 */
	public function getDifferentItems() {
		return array_values( array_filter(
			$this->getItems(),
			function ( $oItem ) {
				/** @var ResultItem $oItem */
				return $oItem->is_different;
			}
		) );
	}

	/**
	 * @return ResultItem[]
	 */
	public function getMissingItems() {
		return array_values( array_filter(
			$this->getItems(),
			function ( $oItem ) {
				/** @var ResultItem $oItem */
				return $oItem->is_missing;
			}
		) );
	}

	/**
	 * @return ResultItem[]
	 */
	public function getUnrecognisedItems() {
		return array_values( array_filter(
			$this->getItems(),
			function ( $oItem ) {
				/** @var ResultItem $oItem */
				return $oItem->is_unrecognised;
			}
		) );
	}

	/**
	 * @return string[]
	 */
	public function getItemsPathsFull() {
		return array_map(
			function ( $oItem ) {
				/** @var ResultItem $oItem */
				return $oItem->path_full;
			},
			$this->getItems()
		);
	}

	/**
	 * @param string $sProperty
	 * @param string $mValue
	 * @return $this
	 */
	public function setPropertyOnAllItems( $sProperty, $mValue ) {
		array_map(
			function ( $oItem ) use ( $sProperty, $mValue ) {
				/** @var ResultItem $oItem */
				$oItem->{$sProperty} = $mValue;
			},
			$this->getAllItems()
		);
		return $this;
	}

	/**
	 * @param string $sContext
	 * @return $this
	 */
	public function setContextOnAllItems( $sContext ) {
		return $this->setPropertyOnAllItems( 'context', $sContext );
	}

	/**
	 * @param string $sSlug
	 * @return $this
	 */
	public function setSlugOnAllItems( $sSlug ) {
		return $this->setPropertyOnAllItems( 'slug', $sSlug );
	}
}