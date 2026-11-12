<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha\AltCha\AltChaV2Pbkdf2;

class CaptureNotBotAltcha extends BaseAction {
	use Traits\AuthNotRequired;

	public const SLUG = 'capture_not_bot_altcha';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$response = $this->response();
		try {
			$verified = $this->verifyAltChaSolution();
			self::con()->comps->events->fireEvent( 'bottrack_multiple', [
				'data' => [
					'events' => \array_keys( \array_filter( [
						'bottrack_notbot' => true,
						'bottrack_altcha' => $verified,
					] ) ),
				]
			] );

			self::con()->comps->not_bot->sendNotBotFlagCookie();

			$response->setPayload( [
				'notbot_state' => self::con()->comps->not_bot->buildResponseState( $verified ),
			] )->setPayloadSuccess( true );
		}
		catch ( \Exception $e ) {
			error_log( $e->getMessage() );
			$response->setPayloadSuccess( false );
		}
	}

	private function verifyAltChaSolution() :bool {
		$verified = false;
		$fields = $this->fields();
		try {
			// A missing or malformed field throws, which leaves the solution unverified.
			if ( $fields->string( 'altcha_version' ) === AltChaV2Pbkdf2::VERSION ) {
				$verified = self::con()->comps->altcha->verifySolution(
					$fields->string( 'altcha_challenge' ),
					$fields->string( 'altcha_solution' )
				);
			}
		}
		catch ( \Exception $e ) {
		}

		return $verified;
	}
}
