<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Privacy;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class PrivacyPolicy {

	use PluginControllerConsumer;
	use ExecOnce;

	protected function canRun() :bool {
		return !empty( self::con()->modules_loaded ) && \function_exists( 'wp_add_privacy_policy_content' );
	}

	protected function run() {
		wp_add_privacy_policy_content( self::con()->labels->Name, $this->buildPrivacyPolicyContent() );
	}

	private function buildPrivacyPolicyContent() :string {
		return wp_kses_post( wpautop( self::con()->action_router->render( Components\PrivacyPolicy::class ), false ) );
	}
}