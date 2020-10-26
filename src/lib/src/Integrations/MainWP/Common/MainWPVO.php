<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Common;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;
use MainWP\Dashboard\MainWP_Extensions_Handler;

/**
 * Class MainwpVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Common
 * @property string $child_key
 * @property string $child_file
 * @property bool   $is_client
 * @property bool   $is_server
 * @property array  $official_extension_data
 */
class MainWPVO {

	use StdClassAdapter {
		__get as __adapterGet;
	}

	/**
	 * @param string $property
	 * @return mixed
	 */
	public function __get( $property ) {

		$mValue = $this->__adapterGet( $property );

		switch ( $property ) {
			case 'official_extension_data':
				$mValue = $this->findOfficialExtensionData();
				break;
			default:
				break;
		}

		return $mValue;
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