<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render\WpCliTable;

class AuditTrail extends Base {

	public function render() {
		$aRows = $this->getDataBuilder()
					  ->getEntriesFormatted();

		error_log( var_export( array_keys( array_shift( $aRows ) ), true ) );
		\WP_CLI\Utils\format_items(
			'table',
			$aRows,
			[
				'ip',
				'wp_username',
				'message',
				'created_at',
			]
		);
	}
}