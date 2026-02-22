<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Traffic\BuildTrafficTableData;

class BuildTrafficData extends BaseDelegatingLogData {

	private ?BuildTrafficTableData $source = null;

	protected function getSource() :BuildTrafficTableData {
		if ( $this->source === null ) {
			$this->source = new BuildTrafficTableData();
		}
		$this->source->table_data = \is_array( $this->table_data ?? null ) ? $this->table_data : [];
		return $this->source;
	}
}
