<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve;

use FernleafSystems\Wordpress\Services\Services;

class RetrieveCount extends RetrieveBase {

	public const CONTEXT_ACTIVE_PROBLEMS = 0;
	public const CONTEXT_NOT_YET_NOTIFIED = 1;
	public const CONTEXT_RESULTS_DISPLAY = 2;

	public function buildQuery( array $selectFields = [] ) :string {
		return sprintf(
			$this->getBaseQuery(),
			\implode( ',', $selectFields ),
			\implode( ' AND ', $this->getWheres() )
		);
	}

	public function count( int $context = self::CONTEXT_ACTIVE_PROBLEMS ) :int {
		$count = 0;

		$latestID = $this->getLatestScanID();
		if ( $latestID >= 0 ) {

			$this->addWheres( [
				sprintf( "`sr`.`scan_ref`=%s", $latestID ),
				"`ri`.`deleted_at`=0",
			] );

			switch ( $context ) {

				case self::CONTEXT_NOT_YET_NOTIFIED:
					$specificWheres = [
						"`ri`.`auto_filtered_at`=0",
						"`ri`.`ignored_at`=0",
						"`ri`.`item_repaired_at`=0",
						"`ri`.`item_deleted_at`=0",
						"`ri`.`notified_at`=0",
					];
					break;

				case self::CONTEXT_RESULTS_DISPLAY:
					$specificWheres = [
						"`ri`.`auto_filtered_at`=0",
					];
					$includes = self::con()->opts->optGet( 'scan_results_table_display' );
					if ( !\in_array( 'include_ignored', $includes ) ) {
						$specificWheres[] = "`ri`.`ignored_at`=0";
					}
					if ( !\in_array( 'include_repaired', $includes ) ) {
						$specificWheres[] = "`ri`.`item_repaired_at`=0";
					}
					if ( !\in_array( 'include_deleted', $includes ) ) {
						$specificWheres[] = "`ri`.`item_deleted_at`=0";
					}
					break;

				case self::CONTEXT_ACTIVE_PROBLEMS:
				default:
					$specificWheres = [
						"`ri`.`auto_filtered_at`=0",
						"`ri`.`ignored_at`=0",
						"`ri`.`item_repaired_at`=0",
						"`ri`.`item_deleted_at`=0",
					];
					break;
			}

			$this->addWheres( $specificWheres );
			$count = (int)Services::WpDb()->getVar( $this->buildQuery( [ 'COUNT(*)' ] ) );
		}

		return $count;
	}

	protected function getBaseQuery( bool $joinWithResultMeta = false ) :string {
		$dbCon = self::con()->db_con;
		return sprintf( "SELECT %%s
						FROM `%s` as sr
						INNER JOIN `%s` as `scans`
							ON `sr`.scan_ref = `scans`.id
						INNER JOIN `%s` as `ri`
							ON `sr`.resultitem_ref = `ri`.id
						INNER JOIN `%s` as %s
							ON %s.`ri_ref` = `ri`.id
						WHERE %%s;",
			$dbCon->scan_results->getTable(),
			$dbCon->scans->getTable(),
			$dbCon->scan_result_items->getTable(),
			$dbCon->scan_result_item_meta->getTable(),
			self::ABBR_RESULTITEMMETA,
			self::ABBR_RESULTITEMMETA
		);
	}
}