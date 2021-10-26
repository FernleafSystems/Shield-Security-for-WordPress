<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\{
	ModCon,
	Scan\Controller\Afs
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class Counts {

	use ModConsumer;

	private $counts;

	public function countMalware() :int {
		return $this->getCount( 'malware_files' );
	}

	public function countPluginFiles() :int {
		return $this->getCount( 'plugin_files' );
	}

	public function countThemeFiles() :int {
		return $this->getCount( 'theme_files' );
	}

	public function countVulnerableAssets() :int {
		return $this->getCount( 'assets_vulnerable' );
	}

	public function countWPFiles() :int {
		return $this->getCount( 'wp_files' );
	}

	private function getCount( $resultType ) :int {
		$counts = $this->getCounts();

		if ( !isset( $counts[ $resultType ] ) ) {
			/** @var ModCon $mod */
			$mod = $this->getMod();
			$resultsRetrieve = ( new Retrieve() )
				->setMod( $this->getMod() )
				->setScanController( $mod->getScanCon( Afs::SCAN_SLUG ) );

			switch ( $resultType ) {

				case 'malware_files':
					$count = $resultsRetrieve->setAdditionalWheres( [ "`rim`.`meta_key`='is_mal'", ] )->count();
					break;
				case 'wp_files':
					$count = $resultsRetrieve->setAdditionalWheres( [ "`rim`.`meta_key`='is_in_core'", ] )->count();
					break;
				case 'plugin_files':
					$count = $resultsRetrieve->setAdditionalWheres( [ "`rim`.`meta_key`='is_in_plugin'", ] )->count();
					break;
				case 'theme_files':
					$count = $resultsRetrieve->setAdditionalWheres( [ "`rim`.`meta_key`='is_in_theme'", ] )->count();
					break;
				case 'assets_vulnerable':
					$count = $resultsRetrieve->setAdditionalWheres( [ "`rim`.`meta_key`='wpvuln_id'", ] )->count();
					break;

				default:
					die( 'unsupported result type' );
			}
			$counts[ $resultType ] = $count;
			$this->counts = $counts;
		}

		return $this->getCounts()[ $resultType ];
	}

	public function getCounts() :array {
		if ( !is_array( $this->counts ) ) {
			$this->counts = [];
		}
		return $this->counts;
	}
}