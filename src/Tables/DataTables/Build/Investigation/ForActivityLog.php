<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Investigation;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForActivityLog as FullActivityLogTable;

class ForActivityLog extends BaseInvestigationTable {

	protected function getSourceBuilderClass() :string {
		return FullActivityLogTable::class;
	}
}
