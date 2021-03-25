<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use MainWP\Dashboard\MainWP_Extensions_Handler;

/**
 * Class MWPExtensionVO
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common
 * @property string $page - e.g. Extensions-Wp-Simple-Firewall
 */
class MWPExtensionVO extends DynPropertiesClass {

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