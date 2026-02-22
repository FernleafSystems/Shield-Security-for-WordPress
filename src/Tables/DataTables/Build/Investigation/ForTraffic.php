<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Investigation;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForTraffic as FullTrafficTable;

class ForTraffic extends BaseInvestigationTable {

	protected function getSourceBuilderClass() :string {
		return FullTrafficTable::class;
	}

	protected function getSubjectFilterColumns() :array {
		switch ( $this->subjectType ) {
			case 'user':
				$hidden = [ 'user', 'uid' ];
				break;
			case 'ip':
				$hidden = [ 'ip' ];
				break;
			default:
				$hidden = [];
				break;
		}
		return $hidden;
	}
}
