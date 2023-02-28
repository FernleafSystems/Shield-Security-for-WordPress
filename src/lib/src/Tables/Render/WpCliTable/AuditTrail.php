<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render\WpCliTable;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogTable\BuildAuditTableData;

class AuditTrail {

	use ModConsumer;

	public function render() {
		\WP_CLI\Utils\format_items(
			'table',
			( new BuildAuditTableData() )->loadForRecords(),
			[
				'ip',
				'user_id',
				'message',
				'created_at',
			]
		);
	}
}