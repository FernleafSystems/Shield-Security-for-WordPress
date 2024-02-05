<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render\WpCliTable;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\ActivityLog\BuildActivityLogTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModConsumer;

class ActivityLog {

	use ModConsumer;

	public function render() {
		\WP_CLI\Utils\format_items(
			'table',
			( new BuildActivityLogTableData() )->loadForRecords(),
			[
				'ip',
				'user_id',
				'message',
				'created_at',
			]
		);
	}
}