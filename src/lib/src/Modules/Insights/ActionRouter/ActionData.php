<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class ActionData {

	const FIELD_ACTION = 'action';
	const FIELD_AJAXURL = 'ajaxurl';
	const FIELD_SHIELD = 'shield_action';
	const FIELD_EXECUTE = 'ex';
	const FIELD_NONCE = 'exnonce';
	const FIELD_WRAP_RESPONSE = 'apto_wrap_response';

	public static function Build( string $action, bool $isAjax = true, array $aux = [] ) :array {
		$data = array_merge( [
			self::FIELD_ACTION  => self::FIELD_SHIELD,
			self::FIELD_EXECUTE => $action,
			self::FIELD_NONCE   => wp_create_nonce( self::FIELD_SHIELD.'-'.$action ),
		], $aux );
		if ( $isAjax ) {
			$data[ self::FIELD_AJAXURL ] = admin_url( 'admin-ajax.php' );
		}
		return $data;
	}

	public static function BuildJson( string $action, bool $isAjax = true, array $aux = [] ) :string {
		return json_encode( (object)self::Build( $action, $isAjax, $aux ) );
	}

	public static function BuildURL( string $action, string $url = '', array $aux = [] ) :string {
		if ( empty( $url ) ) {
			$url = Services::WpGeneral()->getHomeUrl();
		}
		return add_query_arg( self::Build( $action, false, $aux ), $url );
	}

	public static function FileDownloadHref( string $downloadType, array $params = [] ) :string {
		return ActionData::BuildURL(
			Actions\FileDownload::SLUG,
			Services::WpGeneral()->getAdminUrl(),
			array_merge( $params, [ 'download_category' => $downloadType ] )
		);
	}
}