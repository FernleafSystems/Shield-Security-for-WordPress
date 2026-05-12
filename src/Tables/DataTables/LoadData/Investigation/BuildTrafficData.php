<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation;

class BuildTrafficData extends BaseDelegatingLogData {

	private ?InvestigationTrafficTableData $source = null;

	protected function getSource() :InvestigationTrafficTableData {
		if ( $this->source === null ) {
			$this->source = new InvestigationTrafficTableData();
		}
		$this->source->table_data = \is_array( $this->table_data ?? null ) ? $this->table_data : [];
		return $this->source;
	}
}
