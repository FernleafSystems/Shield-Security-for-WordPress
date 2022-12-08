<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class ActionData {

	public const FIELD_ACTION = 'action';
	public const FIELD_AJAXURL = 'ajaxurl';
	public const FIELD_SHIELD = 'shield_action';
	public const FIELD_EXECUTE = 'ex';
	public const FIELD_NONCE = 'exnonce';
	public const FIELD_WRAP_RESPONSE = 'apto_wrap_response';

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
		return URL::Build(
			empty( $url ) ? Services::WpGeneral()->getHomeUrl() : $url,
			self::Build( $action, false, $aux )
		);
	}

	public static function FileDownloadHref( string $downloadType, array $params = [] ) :string {
		return ActionData::BuildURL(
			Actions\FileDownload::SLUG,
			Services::WpGeneral()->getAdminUrl(),
			array_merge( $params, [ 'download_category' => $downloadType ] )
		);
	}
}