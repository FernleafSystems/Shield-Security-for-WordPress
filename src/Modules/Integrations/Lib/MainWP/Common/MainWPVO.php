<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use MainWP\Dashboard\MainWP_Extensions_Handler;

/**
 * @property string         $child_key
 * @property string         $child_file
 * @property bool           $is_client
 * @property bool           $is_server
 * @property array          $official_extension_data
 * @property MWPExtensionVO $extension
 */
class MainWPVO extends DynPropertiesClass {

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function __get( string $key ) {

		$value = parent::__get( $key );

		switch ( $key ) {
			case 'official_extension_data':
				$value = $this->findOfficialExtensionData();
				break;
			case 'extension':
				$value = ( new MWPExtensionVO() )->applyFromArray( $this->official_extension_data );
				break;
			default:
				break;
		}

		return $value;
	}

	private function findOfficialExtensionData() :array {
		$data = [];
		foreach ( MainWP_Extensions_Handler::get_extensions() as $ext ) {
			if ( $ext[ 'plugin' ] === $this->child_file ) {
				$data = $ext;
				break;
			}
		}
		return $data;
	}
}