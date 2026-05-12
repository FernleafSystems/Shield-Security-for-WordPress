<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Reports\Ops as ReportDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\{
	BaseBuildSearchPanesData,
	BaseBuildTableData
};
use FernleafSystems\Wordpress\Services\Services;

class BuildReportsTableData extends BaseBuildTableData {

	protected function getTotalCountCacheKey() :string {
		return '';
	}

	protected function getSearchPanesDataBuilder() :BaseBuildSearchPanesData {
		return new BaseBuildSearchPanesData();
	}

	protected function loadRecordsWithSearch() :array {
		return $this->loadRecordsWithDirectQuery();
	}

	/**
	 * @param ReportDB\Record[] $records
	 */
	protected function buildTableRowsFromRawRecords( array $records ) :array {
		return \array_values( \array_map(
			function ( ReportDB\Record $report ) {
				return [
					'rid'                => $report->id,
					'type'               => $this->colType( $report ),
					'title'              => $this->colTitle( $report ),
					'created_at'         => $report->created_at,
					'created_at_display' => Services::WpGeneral()->getTimeStringForDisplay( $report->created_at ),
					'actions'            => $this->colActions( $report ),
				];
			},
			$records
		) );
	}

	protected function countTotalRecords() :int {
		return $this->baseSelector()->count();
	}

	protected function countTotalRecordsFiltered() :int {
		return $this->selectorFromSearchContext()->count();
	}

	protected function buildWheresFromSearchParams() :array {
		$search = $this->parseSearchText()[ 'remaining' ] ?? '';
		return empty( $search ) ? [] : [
			[ 'title_like' => $search ],
		];
	}

	protected function getSearchableColumns() :array {
		return [
			'title',
		];
	}

	/**
	 * @return ReportDB\Record[]
	 */
	protected function getRecords( array $wheres = [], int $offset = 0, int $limit = 0 ) :array {
		$selector = $this->selectorFromSearchContext();
		$orderBy = $this->getOrderBy();
		if ( !empty( $orderBy ) ) {
			$selector->setOrderBy( $orderBy, $this->getOrderDirection(), true );
		}
		else {
			$selector->setOrderBy( 'created_at', 'DESC', true );
		}

		if ( $limit > 0 ) {
			$page = 1 + (int)\floor( $offset/$limit );
			$selector->setLimit( $limit )
					 ->setPage( $page );
		}

		return $selector->queryWithResult();
	}

	private function colType( ReportDB\Record $report ) :string {
		$class = $report->type === Constants::REPORT_TYPE_INFO ? 'info' :
			( $report->type === Constants::REPORT_TYPE_ALERT ? 'warning' : 'dark' );

		return sprintf(
			'<span class="badge bg-%s">%s</span>',
			$class,
			esc_html( self::con()->comps->reports->getReportTypeName( $report->type ) )
		);
	}

	private function colTitle( ReportDB\Record $report ) :string {
		$viewReport = CommonDisplayStrings::get( 'view_report_label' );
		return sprintf(
			'<a href="%s" target="_blank" title="%s">%s</a>',
			esc_url( self::con()->comps->reports->getReportURL( $report->unique_id ) ),
			esc_attr( $viewReport ),
			esc_html( $report->title )
		);
	}

	private function colActions( ReportDB\Record $report ) :string {
		$viewReport = CommonDisplayStrings::get( 'view_report_label' );
		$view = sprintf(
			'<a href="%s" target="_blank" class="btn btn-dark svg-container p-1 me-1" title="%s"><i class="%s" aria-hidden="true"></i></a>',
			esc_url( self::con()->comps->reports->getReportURL( $report->unique_id ) ),
			esc_attr( $viewReport ),
			self::con()->svgs->iconClass( 'box-arrow-up-right.svg' )
		);
		$delete = sprintf(
			'<button type="button" class="btn btn-danger delete svg-container p-1" title="%s" data-rid="%s"><i class="%s" aria-hidden="true"></i></button>',
			esc_attr__( 'Delete', 'wp-simple-firewall' ),
			$report->id,
			self::con()->svgs->iconClass( 'trash3-fill.svg' )
		);

		return $view.$delete;
	}

	private function baseSelector() :ReportDB\Select {
		/** @var ReportDB\Select $selector */
		$selector = self::con()->db_con->reports->getQuerySelector();
		return $selector
			->addWhere( 'unique_id', '', '!=' )
			->addWhere( 'content', '', '!=' );
	}

	private function selectorFromSearchContext() :ReportDB\Select {
		$selector = $this->baseSelector();
		$search = $this->parseSearchText()[ 'remaining' ] ?? '';
		if ( !empty( $search ) ) {
			$selector->addWhereLike( 'title', $search );
		}
		return $selector;
	}
}
