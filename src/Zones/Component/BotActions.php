<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class BotActions extends Base {

	public function title() :string {
		return __( 'Bot Actions', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return sprintf( __( "Decide how %s should respond when a bot performs certain actions.", 'wp-simple-firewall' ),
			self::con()->labels->Name );
	}

	protected function tooltip() :string {
		return __( 'Control the response to specific bot requests', 'wp-simple-firewall' );
	}

	protected function configureStatus() :array {
		$signalStates = $this->botSignalStates();
		$status = parent::status();

		if ( \count( \array_filter( \array_intersect_key(
			$signalStates,
			\array_filter(
				$this->botSignalDefinitions(),
				static fn( array $definition ) :bool => !empty( $definition[ 'primary' ] )
			)
		) ) ) === 0 ) {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = __( 'No primary bot penalties are enabled for login abuse or XML-RPC attacks.', 'wp-simple-firewall' );
		}

		foreach ( \array_keys( \array_filter( $signalStates, static fn( bool $enabled ) :bool => !$enabled ) ) as $signalKey ) {
			$status[ 'exp' ][] = sprintf(
				__( "Visitors that repeatedly trigger the signal '%s' aren't penalised.", 'wp-simple-firewall' ),
				$this->botSignalName( $signalKey )
			);
		}

		if ( $status[ 'level' ] !== EnumEnabledStatus::BAD ) {
			$status[ 'level' ] = empty( $status[ 'exp' ] ) ? EnumEnabledStatus::GOOD : EnumEnabledStatus::OKAY;
		}

		return $status;
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$status = parent::status();
		$signals = $this->botSignalStates();
		$enabledSignals = \array_keys( \array_filter( $signals ) );

		if ( \count( $enabledSignals ) > 4 ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}
		elseif ( \count( $enabledSignals ) > 2 ) {
			$status[ 'level' ] = EnumEnabledStatus::OKAY;
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
		}

		foreach ( \array_diff( \array_keys( $signals ), $enabledSignals ) as $key ) {
			$status[ 'exp' ][] = sprintf( "Visitors that repeatedly trigger the signal '%s' aren't penalised", $this->botSignalName( $key ) );
		}

		return $status;
	}

	public function postureSignals() :array {
		$states = $this->botSignalStates();
		$signals = [];
		foreach ( $this->botSignalDefinitions() as $optKey => $definition ) {
			$enabled = !empty( $states[ $optKey ] );
			$signals[] = $this->buildPostureSignal(
				'bot_signal_'.$optKey,
				sprintf( __( 'Bot Signal: %s', 'wp-simple-firewall' ), $this->botSignalName( $optKey ) ),
				$definition[ 'weight' ],
				$enabled ? $definition[ 'weight' ] : 0,
				$enabled ? 'good' : 'critical',
				$enabled,
				[
					$enabled
						? sprintf( __( "Visitors that repeatedly trigger '%s' are penalised.", 'wp-simple-firewall' ), $this->botSignalName( $optKey ) )
						: sprintf( __( "Visitors that repeatedly trigger '%s' aren't penalised.", 'wp-simple-firewall' ), $this->botSignalName( $optKey ) ),
				]
			);
		}
		return $signals;
	}

	/**
	 * @return array<string,bool>
	 */
	private function botSignalStates() :array {
		$states = [];
		foreach ( \array_keys( $this->botSignalDefinitions() ) as $signalKey ) {
			$states[ $signalKey ] = !\in_array( self::con()->opts->optGet( $signalKey ), [ 'disabled', 'log' ], true );
		}
		return $states;
	}

	/**
	 * @return array<string,array{name:string,weight:int,primary:bool}>
	 */
	private function botSignalDefinitions() :array {
		return [
			'track_logininvalid'   => [ 'name' => __( 'Invalid Usernames', 'wp-simple-firewall' ), 'weight' => 6, 'primary' => true ],
			'track_loginfailed'    => [ 'name' => __( 'Failed Login', 'wp-simple-firewall' ), 'weight' => 5, 'primary' => true ],
			'track_xmlrpc'         => [ 'name' => __( 'XML-RPC Access', 'wp-simple-firewall' ), 'weight' => 6, 'primary' => true ],
			'track_fakewebcrawler' => [ 'name' => __( 'Fake Web Crawler', 'wp-simple-firewall' ), 'weight' => 3, 'primary' => false ],
			'track_404'            => [ 'name' => __( '404 Detect', 'wp-simple-firewall' ), 'weight' => 2, 'primary' => false ],
			'track_linkcheese'     => [ 'name' => __( 'Link Cheese', 'wp-simple-firewall' ), 'weight' => 2, 'primary' => false ],
			'track_invalidscript'  => [ 'name' => __( 'Invalid Script Load', 'wp-simple-firewall' ), 'weight' => 2, 'primary' => false ],
			'track_useragent'      => [ 'name' => __( 'Empty User Agents', 'wp-simple-firewall' ), 'weight' => 2, 'primary' => false ],
		];
	}

	private function botSignalName( string $key ): string {
		$name = $this->botSignalDefinitions()[ $key ][ 'name' ] ?? '';
		return empty( $name ) ? $key : $name;
	}
}
