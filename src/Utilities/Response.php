<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * @property bool   $success
 * @property int    $error_code
 * @property string $message
 * @property string $error
 * @property string $debug
 * @property array  $data
 */
class Response extends DynPropertiesClass {

	public function __get( string $key ) {
		$value = parent::__get( $key );
		switch ( $key ) {

			case 'data':
			case 'aux_data':
				$value = \is_array( $value ) ? $value : [];
				break;

			case 'message':
			case 'error':
			case 'debug':
				$value = (string)$value;
				break;

			case 'error_code':
				$value = (int)$value;
				break;

			case 'success':
				$value = (bool)$value;
				break;

			default:
				break;
		}
		return $value;
	}

	public function getRelevantMsg() :string {
		return $this->success ? $this->message : $this->error;
	}

	public function addData( string $key, $value ) :self {
		$arr = \is_array( $this->data ) ? $this->data : [];
		$arr[ $key ] = $value;
		$this->data = $arr;
		return $this;
	}
}