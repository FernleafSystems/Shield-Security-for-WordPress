<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;

use \FernleafSystems\Wordpress\Plugin\Shield\Utilities\Response;

class License extends Base {

	public const SLUG = 'license';

	public function processStepFormSubmit( array $form ) :Response {
		$resp = parent::processStepFormSubmit( $form );
		if ( $resp->success = self::con()->comps->license->verify( true )->hasValidWorkingLicense() ) {
			$resp->message = 'License found and installed successfully';
		}
		else {
			$resp->error = sprintf( "There doesn't appear to be a active %s license available for this site.", self::con()->labels->Name );
		}

		$resp->addData( 'page_reload', $resp->success );

		return $resp;
	}

	public function getName() :string {
		return __( 'Activate Pro License', 'wp-simple-firewall' );
	}

	protected function getStepRenderData() :array {
		return [
			'strings' => [
				'step_title' => sprintf( __( "Activate Your %s License", 'wp-simple-firewall' ), self::con()->labels->Name ),
			],
		];
	}

	public function skipStep() :bool {
		return self::con()->isPremiumActive();
	}
}