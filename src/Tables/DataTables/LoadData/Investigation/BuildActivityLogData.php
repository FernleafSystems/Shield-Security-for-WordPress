<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation;

class BuildActivityLogData extends BaseDelegatingLogData {

	private ?InvestigationActivityLogTableData $source = null;

	protected function getSource() :InvestigationActivityLogTableData {
		if ( $this->source === null ) {
			$this->source = new InvestigationActivityLogTableData();
		}
		$this->source->table_data = \is_array( $this->table_data ?? null ) ? $this->table_data : [];
		return $this->source;
	}
}
