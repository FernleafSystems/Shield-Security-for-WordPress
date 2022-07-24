<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render\WpCliTable;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogTable\BuildAuditTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class AuditTrail {

	use ModConsumer;

	public function render() {
		$rows = ( new BuildAuditTableData() )
			->setMod( $this->getMod() )
			->loadForRecords();

		\WP_CLI\Utils\format_items(
			'table',
			$rows,
			[
				'ip',
				'user_id',
				'message',
				'created_at',
			]
		);
	}
}