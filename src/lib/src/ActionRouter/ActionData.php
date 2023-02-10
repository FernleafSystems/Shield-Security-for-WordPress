<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ActionData {

	public const FIELD_ACTION = 'action';
	public const FIELD_AJAXURL = 'ajaxurl';
	public const FIELD_SHIELD = 'shield_action';
	public const FIELD_EXECUTE = 'ex';
	public const FIELD_NONCE = 'exnonce';
	public const FIELD_WRAP_RESPONSE = 'apto_wrap_response';

	public static function Build( string $actionClass, bool $isAjax = true, array $aux = [] ) :array {
		$WP = Services::WpGeneral();
		/** @var Shield\ActionRouter\Actions\BaseAction $actionClass */

		$data = array_merge( [
			self::FIELD_ACTION  => self::FIELD_SHIELD,
			self::FIELD_EXECUTE => $actionClass::SLUG,
			self::FIELD_NONCE   => wp_create_nonce( self::FIELD_SHIELD.'-'.$actionClass::SLUG ),
		], $aux );
		if ( $isAjax ) {
			$data[ self::FIELD_AJAXURL ] = method_exists( $WP, 'ajaxURL' ) ? $WP->ajaxURL() : admin_url( 'admin-ajax.php' );
		}
		return $data;
	}

	public static function BuildJson( string $actionClass, bool $isAjax = true, array $aux = [] ) :string {
		return json_encode( (object)self::Build( $actionClass, $isAjax, $aux ) );
	}
}