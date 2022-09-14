<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\{
	Afs,
	Apc,
	Wpv
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveCount;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class Counts {

	use ModConsumer;

	private $counts;

	public function all() :array {
		array_map( function ( string $type ) {
			error_log( var_export( $this->getCount( $type ), true ) );
		}, [
			'malware_files',
			'abandoned',
			'plugin_files',
			'theme_files',
			'assets_vulnerable',
			'wp_files',
		] );
		return $this->getCounts();
	}

	public function countMalware() :int {
		return $this->getCount( 'malware_files' );
	}

	public function countAbandoned() :int {
		return $this->getCount( 'abandoned' );
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
			$resultsCount = ( new RetrieveCount() )
				->setMod( $this->getMod() )
				->setScanController( $mod->getScanCon( Afs::SCAN_SLUG ) );

			switch ( $resultType ) {

				case 'malware_files':
					$count = $resultsCount->addWheres( [ "`rim`.`meta_key`='is_mal'", ] )->count();
					break;
				case 'wp_files':
					$count = $resultsCount->addWheres( [ "`rim`.`meta_key`='is_in_core'", ] )->count();
					break;
				case 'plugin_files':
					$count = $resultsCount->addWheres( [ "`rim`.`meta_key`='is_in_plugin'", ] )->count();
					break;
				case 'theme_files':
					$count = $resultsCount->addWheres( [ "`rim`.`meta_key`='is_in_theme'", ] )->count();
					break;
				case 'abandoned':
					$count = $resultsCount
						->setScanController( $mod->getScanCon( Apc::SCAN_SLUG ) )
						->addWheres( [ "`rim`.`meta_key`='is_abandoned'", ] )
						->count();
					break;
				case 'assets_vulnerable':
					$count = $resultsCount
						->setScanController( $mod->getScanCon( Wpv::SCAN_SLUG ) )
						->addWheres( [ "`rim`.`meta_key`='is_vulnerable'", ] )
						->count();
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