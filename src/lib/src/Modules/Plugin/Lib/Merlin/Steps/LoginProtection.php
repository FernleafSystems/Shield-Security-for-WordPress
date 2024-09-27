<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Response;

class LoginProtection extends Base {

	public const SLUG = 'login_protection';

	public function getName() :string {
		return __( 'Login' );
	}

	protected function getStepRenderData() :array {
		return [
			'strings' => [
				'step_title' => __( 'Brute Force Login Protection with silentCAPTCHA', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'video_id' => '269191603'
			],
		];
	}

	public function processStepFormSubmit( array $form ) :Response {
		$value = $form[ 'LoginProtectOption' ] ?? '';
		if ( empty( $value ) ) {
			throw new \Exception( 'Please select one of the options, or proceed to the next step.' );
		}
		$opts = self::con()->opts;

		$locs = $opts->optGet( 'bot_protection_locations' );
		$locs = $value === 'Y' ? \array_unshift( $locs, 'login' ) : \array_diff( $locs, [ 'login' ] );
		$opts->optSet( 'bot_protection_locations', \array_unique( $locs ) )
			 ->store();

		$resp = parent::processStepFormSubmit( $form );
		$resp->success = true;
		$resp->message = $value === 'Y' ? __( 'Bot comment SPAM will now be blocked', 'wp-simple-firewall' )
			: __( 'Bot comment SPAM will not be blocked', 'wp-simple-firewall' );
		return $resp;
	}
}