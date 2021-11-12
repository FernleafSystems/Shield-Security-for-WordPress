<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\{
	DB\ResultItemMeta\Ops as ResultItemMetaDB,
	DB\ResultItems\Ops as ResultItemsDB,
	ModCon,
	Options,
	Scan\Controller\Afs,
	Scan\Controller\Apc,
	Scan\Controller\Mal,
	Scan\Controller\Ptg,
	Scan\Controller\Ufc,
	Scan\Controller\Wcf,
	Scan\Controller\Wpv
};
use FernleafSystems\Wordpress\Services\Services;

class ConvertLegacyResults extends ExecOnceModConsumer {

	protected function canRun() :bool {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return (int)$opts->getOpt( 'legacy_db_conversion_at' ) === 0;
	}

	protected function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();
		/** @var ResultItemsDB\Select $selectResItem */
		$dbhResItems = $mod->getDbH_ResultItems();
		/** @var ResultItemsDB\Select $selectResItem */
		$selectResItem = $dbhResItems->getQuerySelector();

		$legacyResults = $this->getLegacyResults();
		foreach ( $legacyResults as $e ) {

			if ( in_array( $e->scan, [ Mal::SCAN_SLUG, Ptg::SCAN_SLUG, Ufc::SCAN_SLUG, Wcf::SCAN_SLUG ] ) ) {
				$selectResItem->filterByItemID( $e->meta[ 'path_fragment' ] )
							  ->filterByTypeFile();
			}
			else {
				// Apc::SCAN_SLUG, Wpv::SCAN_SLUG
				$selectResItem->filterByItemID( $e->meta[ 'slug' ] );
				if ( strpos( $e->meta[ 'slug' ], '/' ) ) {
					$selectResItem->filterByTypePlugin();
				}
				else {
					$selectResItem->filterByTypeTheme();
				}
			}

			$resultItem = $selectResItem->first();

			if ( empty( $resultItem ) ) {

				$scanResult = $this->convertResult( $e );

				if ( !empty( $scanResult ) ) {
					/** @var ResultItemsDB\Insert $selectScans */
					$insertResItem = $dbhResItems->getQueryInserter();
					$insertResItem->insert( $scanResult );
					$resultRecord = $selectResItem->byId( Services::WpDb()->getVar( 'SELECT LAST_INSERT_ID()' ) );

					/** @var ResultItemMetaDB\Delete $metaDeleter */
					$metaDeleter = $mod->getDbH_ResultItemMeta()->getQueryDeleter();
					$metaDeleter->filterByResultItemRef( $resultRecord->id )->query();

					foreach ( $scanResult->meta as $metaKey => $metaValue ) {
						/** @var ResultItemMetaDB\Insert $metaInserter */
						$metaInserter = $mod->getDbH_ResultItemMeta()->getQueryInserter();
						$metaInserter->setInsertData( [
							'ri_ref'     => $resultRecord->id,
							'meta_key'   => $metaKey,
							'meta_value' => is_scalar( $metaValue ) ? $metaValue : json_encode( $metaValue ),
						] )->query();
					}
				}
			}

			$mod->getDbHandler_ScanResults()
				->getQueryDeleter()
				->deleteById( $e->id );
		}

		if ( !empty( $legacyResults ) ) {
			$mod->getScansCon()->startNewScans( $opts->getScanSlugs() );
		}

		$mod->getDbHandler_ScanQueue()->tableDelete();
		if ( $mod->getDbHandler_ScanResults()->getQuerySelector()->count() === 0 ) {
			$mod->getDbHandler_ScanResults()->tableDelete();
			$opts->setOpt( 'legacy_db_conversion_at', Services::Request()->ts() );
		}
	}

	/**
	 * @return ResultItemsDB\Record|null
	 */
	private function convertResult( EntryVO $e ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$raw = $e->meta;

		switch ( $e->scan ) {

			case Apc::SCAN_SLUG:
				$raw[ 'is_abandoned' ] = 1;
				$scanResult = $mod->getScanCon( Apc::SCAN_SLUG )->buildScanResult( $raw );
				break;

			case Wpv::SCAN_SLUG:
				$raw[ 'is_vulnerable' ] = 1;
				$scanResult = $mod->getScanCon( Wpv::SCAN_SLUG )->buildScanResult( $raw );
				unset( $raw[ 'wpvuln_id' ], $raw[ 'wpvuln_vo' ] );
				break;

			case Mal::SCAN_SLUG:
				$raw[ 'mal_fp_confidence' ] = $raw[ 'fp_confidence' ];
				unset( $raw[ 'fp_confidence' ] );
				$raw[ 'mal_file_lines' ] = base64_encode( json_encode( array_fill_keys( $raw[ 'file_lines' ], '' ) ) );
				$raw[ 'mal_fp_lines' ] = json_encode( array_fill_keys( $raw[ 'file_lines' ], 0 ) );
				unset( $raw[ 'file_lines' ] );
				$scanResult = $mod->getScanCon( Afs::SCAN_SLUG )->buildScanResult( $raw );
				break;

			case Ptg::SCAN_SLUG:
				if ( strpos( $raw[ 'slug' ], '/' ) ) {
					$raw[ 'is_in_plugin' ] = true;
				}
				else {
					$raw[ 'is_in_theme' ] = true;
				}

				if ( $raw[ 'is_different' ] ?? false ) {
					$raw[ 'is_checksumfail' ] = true;
				}
				unset( $raw[ 'is_different' ] );

				if ( empty( $raw[ 'is_unrecognised' ] ) ) {
					unset( $raw[ 'is_unrecognised' ] );
				}

				if ( empty( $raw[ 'is_missing' ] ) ) {
					unset( $raw[ 'is_missing' ] );
				}

				unset( $raw[ 'context' ] );
				$scanResult = $mod->getScanCon( Afs::SCAN_SLUG )->buildScanResult( $raw );
				break;

			case Ufc::SCAN_SLUG:
				$raw[ 'is_in_core' ] = true;
				$raw[ 'is_unrecognised' ] = true;
				$scanResult = $mod->getScanCon( Afs::SCAN_SLUG )->buildScanResult( $raw );
				break;

			case Wcf::SCAN_SLUG:
			default:
				$raw[ 'is_in_core' ] = true;

				if ( empty( $raw[ 'is_missing' ] ) ) {
					unset( $raw[ 'is_missing' ] );
				}

				if ( empty( $raw[ 'is_checksumfail' ] ) ) {
					unset( $raw[ 'is_checksumfail' ] );
				}

				unset( $raw[ 'is_excluded' ], $raw[ 'md5_file_wp' ] );

				$scanResult = $mod->getScanCon( Afs::SCAN_SLUG )->buildScanResult( $raw );
				break;
		}

		$scanResult->created_at = $e->created_at;
		$scanResult->ignored_at = $e->ignored_at;
		$scanResult->notified_at = $e->notified_at;
		$scanResult->attempt_repair_at = $e->attempt_repair_at;

		return $scanResult;
	}

	/**
	 * @return EntryVO[]
	 */
	private function getLegacyResults() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$res = $mod->getDbHandler_ScanResults()
				   ->getQuerySelector()
				   ->all();
		return empty( $res ) ? [] : $res;
	}
}