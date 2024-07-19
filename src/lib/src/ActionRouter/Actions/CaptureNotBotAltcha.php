<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class CaptureNotBotAltcha extends BaseAction {

	use Traits\AuthNotRequired;

	public const SLUG = 'capture_not_bot_altcha';

	protected function exec() {
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

			$this->response()->success = true;
		}
		catch ( \Exception $e ) {
			error_log( $e->getMessage() );
			$this->response()->success = false;
		}
	}

	private function verifyAltChaSolution( array $data ) :bool {
		$verified = false;
		$keys = [
			'algorithm',
			'salt',
			'challenge',
			'signature',
			'number',
			'expires',
		];
		if ( \count( \array_intersect_key( $data, \array_flip( $keys ) ) ) === \count( $keys ) ) {
			try {
				$verified = self::con()->comps->altcha->verifySolution(
					$data[ 'algorithm' ],
					$data[ 'salt' ],
					$data[ 'challenge' ],
					$data[ 'signature' ],
					$data[ 'number' ],
					(int)$data[ 'expires' ]
				);
			}
			catch ( \Exception $e ) {
			}
		}

		return $verified;
	}
}