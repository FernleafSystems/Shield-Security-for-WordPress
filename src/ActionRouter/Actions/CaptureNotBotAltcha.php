<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot\AltChaV2Pbkdf2;

class CaptureNotBotAltcha extends BaseAction {

	use Traits\AuthNotRequired;

	public const SLUG = 'capture_not_bot_altcha';

	protected function exec() {
		$response = $this->response();
		try {
			self::con()->comps->events->fireEvent( 'bottrack_multiple', [
				'data' => [
					'events' => \array_keys( \array_filter( [
						'bottrack_notbot' => true,
						'bottrack_altcha' => $this->verifyAltChaSolution( $this->action_data ),
					] ) ),
				]
			] );

			self::con()->comps->not_bot->sendNotBotFlagCookie();

			$response->setPayloadSuccess( true );
		}
		catch ( \Exception $e ) {
			error_log( $e->getMessage() );
			$response->setPayloadSuccess( false );
		}
	}

	private function verifyAltChaSolution( array $data ) :bool {
		$verified = false;
		$keys = [
			'altcha_version',
			'altcha_challenge',
			'altcha_solution',
		];
		if ( \count( \array_intersect_key( $data, \array_flip( $keys ) ) ) === \count( $keys )
			 && (string)$data[ 'altcha_version' ] === AltChaV2Pbkdf2::VERSION ) {
			try {
				$verified = self::con()->comps->altcha->verifySolution(
					(string)$data[ 'altcha_challenge' ],
					(string)$data[ 'altcha_solution' ]
				);
			}
			catch ( \Exception $e ) {
			}
		}

		return $verified;
	}
}
