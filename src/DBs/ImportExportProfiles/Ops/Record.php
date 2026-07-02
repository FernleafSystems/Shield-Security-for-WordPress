<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportProfiles\Ops;

/**
 * @property string $slug
 * @property string $label
 * @property bool   $is_default
 * @property string $config
 * @property int    $created_at
 * @property int    $updated_at
 */
class Record extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Record {

	public function __get( string $key ) {
		$value = parent::__get( $key );

		if ( \in_array( $key, [
			'slug',
			'label',
			'config',
		], true ) ) {
			$value = (string)$value;
		}
		elseif ( \in_array( $key, [
			'created_at',
			'updated_at',
		], true ) ) {
			$value = (int)$value;
		}
		elseif ( $key === 'is_default' ) {
			$value = (bool)$value;
		}

		return $value;
	}
}
