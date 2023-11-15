<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Sessions\Table;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\LoadSessions;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildTableData;

class BuildSessionsTableData extends BaseBuildTableData {

	use ModConsumer;

	protected function loadRecordsWithSearch() :array {
		return $this->loadRecordsWithDirectQuery();
	}

	protected function getSearchPanesData() :array {
		return [];
	}

	/**
	 * @param array[] $records
	 */
	protected function buildTableRowsFromRawRecords( array $records ) :array {
		return \array_values( \array_filter( \array_map(
			function ( $s ) {
				$shield = $s[ 'shield' ] ?? [];
				$data = [];
				$data[ 'rid' ] = $shield[ 'unique' ] ?? '';
				$data[ 'details' ] = $this->getColumnContent_Details( $s );
				$data[ 'is_secadmin' ] = ( $shield[ 'secadmin_at' ] ?? 0 ) > 0 ? $this->getColumnContent_Date( $shield[ 'secadmin_at' ] ) : 'no';
				$data[ 'last_activity_at' ] = $this->getColumnContent_Date( $shield[ 'last_activity_at' ] ?? $s[ 'login' ] );
				$data[ 'logged_in_at' ] = $this->getColumnContent_Date( $s[ 'login' ] );
				return $data;
			},
			$records
		) ) );
	}

	protected function countTotalRecords() :int {
		return $this->getRecordsLoader()->count();
	}

	protected function countTotalRecordsFiltered() :int {
		return $this->getRecordsLoader()->count();
	}

	protected function getRecordsLoader() :LoadSessions {
		return new LoadSessions();
	}

	/**
	 * @return array[]
	 */
	protected function getRecords( array $wheres = [], int $offset = 0, int $limit = 0 ) :array {
		return \array_slice( $this->getRecordsLoader()->allOrderedByLastActivityAt(), $offset, $limit );
	}

	private function getColumnContent_Details( array $session ) :string {
		return sprintf( '%s<br />%s<br />%s',
			$this->getUserHref( $session[ 'shield' ][ 'user_id' ] ),
			$this->getIpAnalysisLink( $session[ 'ip' ] ),
			sprintf( '%s: %s', __( 'Expires' ), $this->getColumnContent_Date( $session[ 'expiration' ], false ) )
		);
	}
}