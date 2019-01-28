<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

/**
 * Class ResultsSet
 * @property ResultItem[] $aItems
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg
 */
class ResultsSet extends Base\BaseResultsSet {

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
	 * Tried using array_map() but this DID NOT work
	 * Provides an array of Results Sets for each unique slug. Array keys are the slugs.
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
	 * Provides a collection of ResultsSets for Plugins.
	 * @return ResultsSet[]
	 */
	public function getAllResultsSetsForPluginsContext() {
		return $this->getAllResultsSetsForContext( ScannerPlugins::CONTEXT );
	}

	/**
	 * @param string $sContext
	 * @return ResultsSet
	 */
	public function getResultsForContext( $sContext ) {
		$oRs = new ResultsSet();
		foreach ( $this->getAllItems() as $oItem ) {
			/** @var ResultItem $oItem */
			if ( $oItem->context == $sContext ) {
				$oRs->addItem( $oItem );
			}
		}
		return $oRs;
	}

	/**
	 * @return ResultsSet
	 */
	public function getResultsForPluginsContext() {
		return $this->getResultsForContext( ScannerPlugins::CONTEXT );
	}

	/**
	 * @return ResultsSet
	 */
	public function getResultsForThemesContext() {
		return $this->getResultsForContext( ScannerThemes::CONTEXT );
	}

	/**
	 * Provides a collection of ResultsSets for Themes.
	 * @return ResultsSet[]
	 */
	public function getAllResultsSetsForThemesContext() {
		return $this->getAllResultsSetsForContext( ScannerThemes::CONTEXT );
	}

	/**
	 * Tried using array_filter() but this DID NOT work
	 * Provides a collection of ResultsSets for a particular context.
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