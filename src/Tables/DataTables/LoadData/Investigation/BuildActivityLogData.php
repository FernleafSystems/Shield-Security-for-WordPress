<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\ActivityLog\BuildActivityLogTableData;

class BuildActivityLogData extends BaseDelegatingLogData {

	private ?BuildActivityLogTableData $source = null;

	protected function getSource() :BuildActivityLogTableData {
		if ( $this->source === null ) {
			$this->source = new BuildActivityLogTableData();
		}
		$this->source->table_data = \is_array( $this->table_data ?? null ) ? $this->table_data : [];
		return $this->source;
	}
}
