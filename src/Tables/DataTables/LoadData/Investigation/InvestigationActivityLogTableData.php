<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation\InvestigationTableContract;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LogRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\ActivityLog\BuildActivityLogTableData;

class InvestigationActivityLogTableData extends BuildActivityLogTableData {

	use InvestigationContextLinks;

	/**
	 * @param LogRecord[] $records
	 */
	protected function buildTableRowsFromRawRecords( array $records ) :array {
		$rows = parent::buildTableRowsFromRawRecords( $records );

		$records = \array_values( $records );
		foreach ( $records as $index => $record ) {
			if ( !isset( $rows[ $index ] ) || !$record instanceof LogRecord ) {
				continue;
			}
			$rows[ $index ] = $this->appendAssetInvestigateLinks( $rows[ $index ], $record );
		}

		return $rows;
	}

	private function appendAssetInvestigateLinks( array $row, LogRecord $record ) :array {
		$linkSpecs = [];

		$plugin = \trim( (string)( $record->meta_data[ 'plugin' ] ?? '' ) );
		if ( !empty( $plugin ) ) {
			$linkSpecs[] = $this->buildInvestigateAssetLinkSpec( InvestigationTableContract::SUBJECT_TYPE_PLUGIN, $plugin );
		}

		$theme = \trim( (string)( $record->meta_data[ 'theme' ] ?? '' ) );
		if ( !empty( $theme ) ) {
			$linkSpecs[] = $this->buildInvestigateAssetLinkSpec( InvestigationTableContract::SUBJECT_TYPE_THEME, $theme );
		}

		$links = $this->renderAnchorSpecs( $linkSpecs );
		if ( !empty( $links ) ) {
			$row[ 'message' ] = \sprintf(
				'%s<div class="small mt-1">%s</div>',
				(string)( $row[ 'message' ] ?? '' ),
				$links
			);
		}

		return $row;
	}
}
