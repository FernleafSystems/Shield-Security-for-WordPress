<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\{
	Functions,
	Modules
};

/**
 * @deprecated 19.1
 */
class OptUtils {

	/**
	 * @return Modules\Base\ModCon|mixed
	 */
	public static function ModFromOpt( string $optKey ) {
		$con = Functions\get_plugin()->getController();
		foreach ( $con->modules as $maybe ) {
			if ( \in_array( $optKey, $maybe->opts()->getOptionsKeys() ) ) {
				$mod = $maybe;
				break;
			}
		}
		if ( empty( $mod ) ) {
			$mod = $con->getModule_Plugin();
		}
		return $mod;
	}
}