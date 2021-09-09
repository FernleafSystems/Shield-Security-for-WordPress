<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render\WpCliTable;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogTable\LoadRawTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class AuditTrail {

	use ModConsumer;

	public function render() {
		$rows = ( new LoadRawTableData() )
			->setMod( $this->getMod() )
			->loadForLogs();

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