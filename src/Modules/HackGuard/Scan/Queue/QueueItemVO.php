<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Queue;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * @property int    $scan_id
 * @property int    $qitem_id
 * @property string $scan
 * @property string $scope_type
 * @property string $scope_key
 * @property string $run_trigger
 * @property int    $scan_started_at
 * @property int    $attempts
 * @property array  $meta
 * @property array  $items
 */
class QueueItemVO extends DynPropertiesClass {

	public function applyFromArray( array $data, array $restrictedKeys = [] ) {
		foreach ( $data as $key => $value ) {
			$data[ $key ] = $this->normaliseValue( $key, $value );
		}
		return parent::applyFromArray( $data, $restrictedKeys );
	}

	public function __set( string $key, $value ) {
		parent::__set( $key, $this->normaliseValue( $key, $value ) );
	}

	private function normaliseValue( string $key, $value ) {
		switch ( $key ) {
			case 'scan_id':
			case 'qitem_id':
			case 'scan_started_at':
			case 'attempts':
				$value = (int)$value;
				break;
			case 'meta':
			case 'items':
				if ( !\is_array( $value ) ) {
					$value = $this->decodeArrayPayload( $value );
				}
				break;
			case 'scan':
			case 'scope_type':
			case 'scope_key':
			case 'run_trigger':
				$value = (string)$value;
				break;
			default:
				break;
		}
		return $value;
	}

	private function decodeArrayPayload( $value ) :array {
		if ( !\is_string( $value ) || $value === '' ) {
			return [];
		}

		$decoded = \base64_decode( $value, true );
		if ( !\is_string( $decoded ) ) {
			return [];
		}

		$payload = \json_decode( $decoded, true );
		return \is_array( $payload ) ? $payload : [];
	}
}
