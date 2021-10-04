<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Ptg;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

class ResultsSet extends Base\ResultsSet {

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
}