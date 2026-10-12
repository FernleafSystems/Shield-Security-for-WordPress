<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha\Assets;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\CaptureNotBot
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class InsertNotBotJs {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function canRun() :bool {
		return (bool)apply_filters( 'shield/notbot_js_insert', true );
	}

	protected function run() {
		add_filter( 'shield/custom_enqueue_assets', function ( $assets ) {
			$assets = \is_array( $assets ) ? $assets : [];
			$assets[] = 'silentcaptcha';

			add_filter( 'shield/custom_localisations/components', function ( $components ) {
				$components = \is_array( $components ) ? $components : [];
				$components[ 'silentcaptcha' ] = [
					'key'     => 'silentcaptcha',
					'handles' => [
						'silentcaptcha',
					],
					'data'    => function () {
						$con = self::con();
						$config = [
							'mode' => $con->comps->opts_lookup->silentCaptchaMode(),
							'refresh_seconds' => $con->cfg->configuration->def( 'silentcaptcha_refresh_seconds' ),
							'storage_key' => 'icwp-wpsf-notbot-freshness:v1:'.home_url( '/' ),
							'is_login' => doing_action( 'login_enqueue_scripts' ),
						];
						if ( $config[ 'mode' ] === 'cookie_free' ) {
							// Cookie mode relays this through the cookie. Cookie-free browsers use it to
							// re-check early when this request's IP, e.g. a changed one, lacks fresh signals.
							$config[ 'hint' ] = [
								'required'    => !empty( $con->comps->not_bot->getRequiredSignals() ),
								'rendered_at' => Services::Request()->ts(),
							];
						}
						return [
							'ajax'  => [
								'silentcaptcha' => ActionData::Build( CaptureNotBot::class ),
							],
							'config' => $config,
						];
					},
				];
				return $components;
			} );

			return $assets;
		} );
	}
}
