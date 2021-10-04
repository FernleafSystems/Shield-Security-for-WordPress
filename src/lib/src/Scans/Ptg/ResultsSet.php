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
	 * @return ResultsSet
	 */
	public function getResultsForPluginsContext() {
		$results = new ResultsSet();
		foreach ( $this->getAllItems() as $item ) {
			/** @var ResultItem $item */
			if ( strpos( $item->slug, '/' ) ) {
				$results->addItem( $item );
			}
		}
		return $results;
	}

	/**
	 * @return ResultsSet
	 */
	public function getResultsForThemesContext() {
		$results = new ResultsSet();
		foreach ( $this->getAllItems() as $item ) {
			/** @var ResultItem $item */
			if ( strpos( $item->slug, '/' ) === false ) {
				$results->addItem( $item );
			}
		}
		return $results;
	}
}