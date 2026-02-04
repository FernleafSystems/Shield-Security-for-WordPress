<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;

class CaptureNotBot extends BaseAction {

	use Traits\AuthNotRequired;
	use Traits\NonceVerifyNotRequired;

	public const SLUG = 'capture_not_bot';

	protected function exec() {
		$con = self::con();
		try {
			$con->fireEvent( 'bottrack_multiple', [
				'data' => [
					'events' => [
						'bottrack_notbot',
					],
				]
			] );

			$notBotCon = $con->comps->not_bot;
			$notBotCon->sendNotBotFlagCookie();

			$this->response()->success = true;
			$this->response()->action_response_data = [
				'success'     => true,
				'altcha_data' => $notBotCon->getRequiredSignals() ?
					ActionData::Build( CaptureNotBotAltcha::class, true, $con->comps->altcha->generateChallenge() ) : [],
			];
		}
		catch ( \Exception $e ) {
//			error_log( $e->getMessage() );
			$this->response()->success = false;
		}
	}

	public static function NonceCfg() :array {
		return [
			'ip'  => false,
			'ttl' => 24,
		];
	}
}