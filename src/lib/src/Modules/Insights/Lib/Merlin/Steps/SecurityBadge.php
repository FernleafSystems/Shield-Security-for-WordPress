<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield;

class SecurityBadge extends Base {

	const SLUG = 'security_badge';

	public function getName() :string {
		return 'Security Badge';
	}

	protected function getStepRenderData() :array {
		return [
			'strings' => [
				'step_title' => __( "Show Your Visitors That You Take Security Seriously!", 'wp-simple-firewall' ),
			],
			'vars'    => [
				'video_id' => '552430272'
			],
		];
	}

	public function processStepFormSubmit( array $form ) :bool {
		$value = $form[ 'SecurityPluginBadge' ] ?? '';
		if ( empty( $value ) ) {
			throw new \Exception( 'No option setting provided.' );
		}
		$mod = $this->getCon()->getModule_Plugin();

		$toEnable = $value === 'Y';
		if ( $toEnable ) { // we don't disable the whole module
			$mod->setIsMainFeatureEnabled( true );
		}
		$mod->getOptions()->setOpt( 'display_plugin_badge', $toEnable ? 'Y' : 'N' );

		$mod->saveModOptions();
		return true;
	}
}