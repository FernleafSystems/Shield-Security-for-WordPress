<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Investigation;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForSessions as FullSessionsTable;

class ForSessions extends BaseInvestigationTable {

	protected function getSourceBuilderClass() :string {
		return FullSessionsTable::class;
	}

	protected function getSubjectFilterColumns() :array {
		return $this->subjectType === 'user' ? [ 'uid' ] : [];
	}
}
