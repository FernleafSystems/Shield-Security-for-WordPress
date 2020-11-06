<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common;

use FernleafSystems\Utilities\Data\Adapter\StdClassAdapter;
use MainWP\Dashboard\MainWP_Extensions_Handler;

/**
 * Class MWPExtensionVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common
 * @property string $page - e.g. Extensions-Wp-Simple-Firewall
 */
class MWPExtensionVO {

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