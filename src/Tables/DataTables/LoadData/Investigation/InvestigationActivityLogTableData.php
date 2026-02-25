<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation;

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
		$links = [];

		$plugin = \trim( (string)( $record->meta_data[ 'plugin' ] ?? '' ) );
		if ( !empty( $plugin ) ) {
			$links[] = \sprintf(
				'<a href="%s">%s</a>',
				self::con()->plugin_urls->investigateByPlugin( $plugin ),
				__( 'Investigate Plugin', 'wp-simple-firewall' )
			);
		}

		$theme = \trim( (string)( $record->meta_data[ 'theme' ] ?? '' ) );
		if ( !empty( $theme ) ) {
			$links[] = \sprintf(
				'<a href="%s">%s</a>',
				self::con()->plugin_urls->investigateByTheme( $theme ),
				__( 'Investigate Theme', 'wp-simple-firewall' )
			);
		}

		if ( !empty( $links ) ) {
			$row[ 'message' ] = \sprintf(
				'%s<div class="small mt-1">%s</div>',
				(string)( $row[ 'message' ] ?? '' ),
				\implode( ' | ', $links )
			);
		}

		return $row;
	}
}
