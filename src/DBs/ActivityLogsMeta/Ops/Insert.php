<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogsMeta\Ops;

use FernleafSystems\Wordpress\Services\Services;

class Insert extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Insert {

	public function insertManyForLog( int $logRef, array $metas ) :bool {
		$metas = \array_filter(
			$metas,
			fn( $metaValue, $metaKey ) => \is_scalar( $metaKey ) && (string)$metaKey !== '',
			\ARRAY_FILTER_USE_BOTH
		);
		if ( empty( $metas ) ) {
			return true;
		}

		$wpdb = Services::WpDb()->loadWpdb();

		$values = [];
		foreach ( $metas as $metaKey => $metaValue ) {
			$values[] = $wpdb->prepare(
				'(%d,%s,%s)',
				$logRef,
				(string)$metaKey,
				$this->normaliseMetaValue( $metaValue )
			);
		}

		return (bool)$wpdb->query( \sprintf(
			'INSERT INTO `%s` (`log_ref`,`meta_key`,`meta_value`) VALUES %s',
			$this->getDbH()->getTable(),
			\implode( ',', $values )
		) );
	}

	private function normaliseMetaValue( $metaValue ) :string {
		if ( \is_scalar( $metaValue ) || $metaValue === null ) {
			return (string)$metaValue;
		}

		$encoded = \wp_json_encode( $metaValue );
		return \is_string( $encoded ) ? $encoded : '';
	}
}
