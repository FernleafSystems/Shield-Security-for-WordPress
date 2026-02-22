<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Investigation;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Scans\ForPluginTheme as FullFileScanResultsTable;

class ForFileScanResults extends BaseInvestigationTable {

	protected function getSourceBuilderClass() :string {
		return FullFileScanResultsTable::class;
	}
}
