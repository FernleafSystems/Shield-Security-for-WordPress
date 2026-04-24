<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve;

use FernleafSystems\Wordpress\Services\Services;

class RetrieveCount extends RetrieveBase {

	public const CONTEXT_ACTIVE_PROBLEMS = 0;
	public const CONTEXT_NOT_YET_NOTIFIED = 1;
	public const CONTEXT_RESULTS_DISPLAY = 2;

	public function buildQuery( array $selectFields = [] ) :string {
		$wheres = $this->getWheres();
		return sprintf(
			$this->getBaseQuery( $this->wheresNeedResultMetaJoin( $wheres ) ),
			\implode( ',', $selectFields ),
			\implode( ' AND ', $wheres )
		);
	}

	public function count( int $context = self::CONTEXT_ACTIVE_PROBLEMS ) :int {
		$wheresBuilder = new LatestScanResultWheresBuilder();
		$scanSlug = $this->getScanController()->getSlug();
		switch ( $context ) {
			case self::CONTEXT_NOT_YET_NOTIFIED:
				$specificWheres = $wheresBuilder->forNotYetNotified( $scanSlug );
				break;

			case self::CONTEXT_RESULTS_DISPLAY:
				$specificWheres = $wheresBuilder->forResultsDisplay( $scanSlug );
				break;

			case self::CONTEXT_ACTIVE_PROBLEMS:
			default:
				$specificWheres = $wheresBuilder->forActiveProblems( $scanSlug );
				break;
		}

		return $this->countForSpecificWheres( $specificWheres );
	}

	/**
	 * @param array<string,mixed> $options
	 */
	public function countForResultsDisplay( array $options = [] ) :int {
		return $this->countForSpecificWheres(
			( new LatestScanResultWheresBuilder() )->forResultsDisplayWithOptions( $this->getScanController()->getSlug(), $options )
		);
	}

	protected function getBaseQuery( bool $joinWithResultMeta = false ) :string {
		$dbCon = self::con()->db_con;
		return sprintf( "SELECT %%s
						FROM `%s` as `ri`
						%s
						WHERE %%s;",
			$dbCon->scan_result_items->getTable(),
			$joinWithResultMeta ? sprintf(
				'INNER JOIN `%s` as %s
							ON %s.`ri_ref` = `ri`.id',
				$dbCon->scan_result_item_meta->getTable(),
				self::ABBR_RESULTITEMMETA,
				self::ABBR_RESULTITEMMETA
			) : ''
		);
	}

	/**
	 * @param list<string> $wheres
	 */
	private function wheresNeedResultMetaJoin( array $wheres ) :bool {
		foreach ( $wheres as $where ) {
			if ( \strpos( $where, self::ABBR_RESULTITEMMETA ) !== false || \strpos( $where, 'rim.' ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param list<string> $specificWheres
	 */
	private function countForSpecificWheres( array $specificWheres ) :int {
		return (int)$this->withMergedWheres(
			$specificWheres,
			fn() :int => (int)Services::WpDb()->getVar( $this->buildQuery( [ 'COUNT(*)' ] ) )
		);
	}
}
