<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs;

class ResultsSet extends \FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultsSet {

	public function getForPlugin( string $slug ) :ResultsSet {
		return $this->getPluginGuard()->filterByFieldEquals( 'ptg_slug', $slug );
	}

	public function getForTheme( string $slug ) :ResultsSet {
		return $this->getThemeGuard()->filterByFieldEquals( 'ptg_slug', $slug );
	}

	public function getMalware() :ResultsSet {
		return $this->filterByFieldEquals( 'is_mal', true );
	}

	public function getPluginGuard() :ResultsSet {
		return $this->filterByFieldEquals( 'is_in_plugin', true );
	}

	public function getThemeGuard() :ResultsSet {
		return $this->filterByFieldEquals( 'is_in_theme', true );
	}

	public function getWordpressCore() :ResultsSet {
		return $this->filterByFieldEquals( 'is_in_core', true );
	}

	private function filterByFieldEquals( string $field, $equals ) :ResultsSet {
		$res = new ResultsSet();
		/** @var ResultItem $item */
		foreach ( $this->getItems() as $item ) {
			if ( $item->{$field} == $equals ) {
				$res->addItem( $item );
			}
		}
		return $res;
	}
}