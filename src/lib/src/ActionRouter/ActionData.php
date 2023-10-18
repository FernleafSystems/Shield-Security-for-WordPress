<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Adhoc\Nonce;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\PasswordGenerator;
use FernleafSystems\Wordpress\Services\Services;

class ActionData {

	public const FIELD_ACTION = 'action';
	public const FIELD_AJAXURL = 'ajaxurl';
	public const FIELD_SHIELD = 'shield_action';
	public const FIELD_EXECUTE = 'ex';
	public const FIELD_NONCE = 'exnonce';
	public const FIELD_WRAP_RESPONSE = 'apto_wrap_response';

	public static function Build( string $actionClass, bool $isAjax = true, array $aux = [], bool $uniq = false ) :array {
		/** @var \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction $actionClass */
		$data = \array_merge( [
			self::FIELD_ACTION  => self::FIELD_SHIELD,
			self::FIELD_EXECUTE => $actionClass::SLUG,
			self::FIELD_NONCE   => Nonce::Create( self::FIELD_SHIELD.'-'.$actionClass::SLUG ),
		], $aux );
		if ( $isAjax ) {
			$data[ self::FIELD_AJAXURL ] = Services::WpGeneral()->ajaxURL();
		}

		if ( $uniq ) {
			$data[ 'uniq' ] = wp_generate_password( 4, false );
		}

		return $data;
	}

	public static function BuildVO( ActionDataVO $VO ) :array {
		$data = \array_merge( [
			self::FIELD_ACTION  => self::FIELD_SHIELD,
			self::FIELD_EXECUTE => $VO->action::SLUG,
			self::FIELD_NONCE   => Nonce::Create( self::FIELD_SHIELD.'-'.$VO->action::SLUG, $VO->ip_in_nonce ),
		], $VO->aux );

		if ( $VO->is_ajax ) {
			$data[ self::FIELD_AJAXURL ] = Services::WpGeneral()->ajaxURL();
		}

		if ( $VO->unique ) {
			$data[ 'uniq' ] = PasswordGenerator::Gen( 4, true, true, false );
		}

		if ( \count( $VO->excluded_fields ) > 0 ) {
			$data = \array_diff_key( $data, \array_flip( $VO->excluded_fields ) );
		}

		return $data;
	}

	public static function BuildJson( string $actionClass, bool $isAjax = true, array $aux = [] ) :string {
		return \json_encode( (object)self::Build( $actionClass, $isAjax, $aux ) );
	}
}