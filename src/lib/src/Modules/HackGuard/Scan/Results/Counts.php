<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveCount;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class Counts {

	use PluginControllerConsumer;

	private $counts = [];

	private $context;

	public function __construct( int $context = RetrieveCount::CONTEXT_ACTIVE_PROBLEMS ) {
		$this->context = $context;
	}

	public function all() :array {
		\array_map(
			function ( string $type ) {
				$this->getCount( $type );
			},
			[
				'malware_files',
				'abandoned',
				'plugin_files',
				'theme_files',
				'assets_vulnerable',
				'wp_files',
			]
		);
		return $this->counts;
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

		if ( !isset( $this->counts[ $resultType ] ) ) {
			$scansCon = self::con()->comps->scans;
			$resultsCount = new RetrieveCount();

			switch ( $resultType ) {

				case 'malware_files':
					$resultsCount->setScanController( $scansCon->AFS() )
								 ->addWheres( [ "`rim`.`meta_key`='is_mal'", ] );
					break;
				case 'wp_files':
					$resultsCount->setScanController( $scansCon->AFS() )
								 ->addWheres( [ "`rim`.`meta_key`='is_in_core'", ] );
					break;
				case 'plugin_files':
					$resultsCount->setScanController( $scansCon->AFS() )
								 ->addWheres( [ "`rim`.`meta_key`='is_in_plugin'", ] );
					break;
				case 'theme_files':
					$resultsCount->setScanController( $scansCon->AFS() )
								 ->addWheres( [ "`rim`.`meta_key`='is_in_theme'", ] );
					break;
				case 'abandoned':
					$resultsCount->setScanController( $scansCon->APC() )
								 ->addWheres( [ "`rim`.`meta_key`='is_abandoned'", ] );
					break;
				case 'assets_vulnerable':
					$resultsCount->setScanController( $scansCon->WPV() )
								 ->addWheres( [ "`rim`.`meta_key`='is_vulnerable'", ] );
					break;

				default:
					die( 'unsupported result type' );
			}
			$this->counts[ $resultType ] = $resultsCount->count( $this->context );
		}

		return $this->counts[ $resultType ];
	}
}