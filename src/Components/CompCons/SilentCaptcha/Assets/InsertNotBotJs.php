<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha\Assets;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionDataVO,
	Actions\CaptureNotBot
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

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
						$notBotVO = new ActionDataVO();
						$notBotVO->action = CaptureNotBot::class;
						$notBotVO->ip_in_nonce = false;

						return [
							'ajax'  => [
								'silentcaptcha' => ActionData::BuildVO( $notBotVO ),
							],
							'config' => [
								'mode' => self::con()->comps->opts_lookup->silentCaptchaMode(),
								'refresh_seconds' => self::con()->cfg->configuration->def( 'silentcaptcha_refresh_seconds' ),
								'storage_key' => 'icwp-wpsf-notbot-freshness:v1:'.home_url( '/' ),
								'is_login' => doing_action( 'login_enqueue_scripts' ),
							]
						];
					},
				];
				return $components;
			} );

			return $assets;
		} );
	}
}
