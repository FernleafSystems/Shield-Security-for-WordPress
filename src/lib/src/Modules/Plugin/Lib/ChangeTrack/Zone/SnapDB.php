<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Zone;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Hasher;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Report\ZoneReportDB;
use FernleafSystems\Wordpress\Services\Services;

class SnapDB extends BaseZone {

	public const SLUG = 'db';

	public function getZoneReporterClass() :string {
		return ZoneReportDB::class;
	}

	public function snap() :array {
		return [
			'connection' => \array_map(
				function ( $item ) {
					return Hasher::Item( (string)$item );
				},
				[
					'host'   => DB_HOST,
					'pass'   => DB_PASSWORD,
					'prefix' => Services::WpDb()->getPrefix(),
					'user'   => DB_USER,
				]
			),
			'tables'     => $this->tables(),
		];
	}

	private function tables() :array {
		$tables = \array_values( \array_filter( \array_map(
			function ( $table ) {
				$prefix = Services::WpDb()->getPrefix();
				if ( \strpos( $table, $prefix ) === 0 ) {
					$table = \preg_replace( "#^$prefix#", '', (string)$table );
				}
				else {
					$table = null;
				}
				return $table;
			},
			Services::WpDb()->showTables()
		) ) );
		\natsort( $tables );
		return $tables;
	}
}
