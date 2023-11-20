<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\AjaxRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Adhoc\Nonce;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\PasswordGenerator;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class ActionData {

	public const FIELD_ACTION = 'action';
	public const FIELD_AJAXURL = 'ajaxurl';
	public const FIELD_SHIELD = 'shield_action';
	public const FIELD_EXECUTE = 'ex';
	public const FIELD_NONCE = 'exnonce';
	public const FIELD_REST_NONCE = '_wpnonce';
	public const FIELD_REST_URL = '_rest_url';
	public const FIELD_WRAP_RESPONSE = 'apto_wrap_response';

	public static function Build( string $actionClass, bool $isAjax = true, array $aux = [], bool $uniq = false ) :array {
		if ( \method_exists( ActionData::class, 'BuildVO' ) ) {
			$vo = new ActionDataVO();
			$vo->action = $actionClass;
			$vo->is_ajax = $isAjax;
			$vo->aux = $aux;
			$vo->unique = $uniq;
			$data = self::BuildVO( $vo );
		}
		else {
			/**
			 * @deprecated 18.5
			 */
			/** @var BaseAction $actionClass */
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
		}

		return $data;
	}

	public static function BuildVO( ActionDataVO $VO ) :array {
		$data = \array_merge( [
			self::FIELD_ACTION  => self::FIELD_SHIELD,
			self::FIELD_EXECUTE => $VO->action::SLUG,
			self::FIELD_NONCE   => Nonce::Create( self::FIELD_SHIELD.'-'.$VO->action::SLUG, $VO->ip_in_nonce ),
		], $VO->aux );

		if ( $VO->unique ) {
			$data[ 'uniq' ] = PasswordGenerator::Gen( 4, true, true, false );
		}

		if ( \count( $VO->excluded_fields ) > 0 ) {
			$data = \array_diff_key( $data, \array_flip( $VO->excluded_fields ) );
		}

		if ( $VO->is_ajax ) {
			$data[ self::FIELD_AJAXURL ] = Services::WpGeneral()->ajaxURL();

			$data[ self::FIELD_REST_NONCE ] = wp_create_nonce( 'wp_rest' );
			$data[ self::FIELD_REST_URL ] = URL::Build(
				get_rest_url( null, sprintf( 'shield/v1/action/%s', $VO->action::SLUG ) ),
				\array_diff_key(
					$data,
					\array_flip( [
						self::FIELD_ACTION,
						self::FIELD_EXECUTE,
						self::FIELD_AJAXURL,
					] )
				)
			);
		}

		return $data;
	}

	/**
	 * @param BaseAction|string $actionClass
	 */
	public static function BuildAjaxRender( string $actionClass = '', array $aux = [] ) :array {
		$aux[ 'render_slug' ] = empty( $actionClass ) ? '' : $actionClass::SLUG;
		return self::Build( AjaxRender::class, true, $aux );
	}

	public static function BuildJson( string $actionClass, bool $isAjax = true, array $aux = [] ) :string {
		return \json_encode( (object)self::Build( $actionClass, $isAjax, $aux ) );
	}
}