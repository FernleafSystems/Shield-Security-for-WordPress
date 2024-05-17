<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot\AltChaHandler;

class CaptureNotBot extends BaseAction {

	use Traits\AuthNotRequired;
	use Traits\NonceVerifyNotRequired;

	public const SLUG = 'capture_not_bot';

	protected function exec() {

		try {
			self::con()->fireEvent( 'bottrack_multiple', [
				'data' => [
					'events' => \array_keys( \array_filter( [
						'bottrack_notbot' => true,
					] ) ),
				]
			] );

			self::con()->comps->not_bot->sendNotBotFlagCookie();

			$this->response()->success = true;
			$this->response()->action_response_data = [
				'success'     => true,
				'altcha_data' => ActionData::Build( CaptureNotBotAltcha::class, true, $this->getAltChaChallenge() ),
			];
		}
		catch ( \Exception $e ) {
			error_log( $e->getMessage() );
			$this->response()->success = false;
		}
	}

	public static function NonceCfg() :array {
		return [
			'ip'  => false,
			'ttl' => 24,
		];
	}

	/**
	 * @throws \Exception
	 */
	private function getAltChaChallenge() :array {
		return ( new AltChaHandler() )->generateChallenge();
	}
}