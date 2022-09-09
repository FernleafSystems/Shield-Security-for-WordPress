<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield;

class LoginProtection extends Base {

	const SLUG = 'login_protection';

	public function getName() :string {
		return 'Login Protection';
	}

	protected function getStepRenderData() :array {
		return [
			'strings' => [
				'step_title' => __( "Brute Force Login Protection", 'wp-simple-firewall' ),
			],
			'vars'    => [
				'video_id' => '269191603'
			],
		];
	}

	public function processStepFormSubmit( array $form ) :bool {
		$value = $form[ 'LoginProtectOption' ] ?? '';
		if ( empty( $value ) ) {
			throw new \Exception( 'No option setting provided.' );
		}

		$mod = $this->getCon()->getModule_LoginGuard();

		$toEnable = $value === 'Y';
		if ( $toEnable ) { // we don't disable the whole module
			$mod->setIsMainFeatureEnabled( true );
		}
		$mod->getOptions()->setOpt( 'enable_antibot_check', $toEnable ? 'Y' : 'N' );

		$mod->saveModOptions();
		return true;
	}
}