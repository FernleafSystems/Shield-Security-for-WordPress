<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Sessions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Session\LoadSessions;

class BuildSessionsTableData extends \FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildTableData {

	protected function loadRecordsWithSearch() :array {
		return $this->loadRecordsWithDirectQuery();
	}

	protected function getSearchPanesData() :array {
		return ( new BuildSearchPanesData() )->build();
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
				$data[ 'uid' ] = $shield[ 'user_id' ] ?? '';
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
		return new LoadSessions( $this->getFilteredUserID() );
	}

	private function getFilteredUserID() :?int {
		$id = \current( $this->table_data[ 'searchPanes' ][ 'uid' ] ?? [] );
		return empty( $id ) ? null : (int)$id;
	}

	/**
	 * @return array[]
	 */
	protected function getRecords( array $wheres = [], int $offset = 0, int $limit = 0 ) :array {
		return \array_slice( $this->getRecordsLoader()->allOrderedByLastActivityAt(), $offset, $limit );
	}

	private function getColumnContent_Details( array $session ) :string {
		$ua = esc_html( $session[ 'shield' ][ 'useragent' ] ?? '' );
		return sprintf( '%s<br />%s%s<br />%s',
			$this->getUserHref( $session[ 'shield' ][ 'user_id' ] ),
			$this->getIpAnalysisLink( $session[ 'ip' ] ),
			empty( $ua ) ? '' : sprintf( '<br/><code style="font-size: small">%s</code>', $ua ),
			sprintf( '%s: %s', __( 'Expires' ), $this->getColumnContent_Date( $session[ 'expiration' ], false ) )
		);
	}
}