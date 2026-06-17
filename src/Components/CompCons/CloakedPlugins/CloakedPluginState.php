<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class CloakedPluginState {

	use PluginControllerConsumer;

	public const OPT_KEY = 'hidden_plugins_alert_state';

	/**
	 * @param list<CloakedPluginFinding> $findings
	 * @return list<CloakedPluginFinding>
	 */
	public function rememberNew( array $findings ) :array {
		$stored = $this->load();
		$next = [];
		$new = [];
		$now = \time();

		foreach ( $findings as $finding ) {
			$fingerprint = $finding->fingerprint();
			if ( !isset( $stored[ $fingerprint ] ) ) {
				$new[] = $finding;
			}
			$next[ $fingerprint ] = [
				'notified_at' => (int)( $stored[ $fingerprint ][ 'notified_at' ] ?? $now ),
				'last_seen_at' => $now,
			];
		}

		$this->store( $next );
		return $new;
	}

	private function load() :array {
		$state = self::con()->opts->optGet( self::OPT_KEY );
		if ( !\is_array( $state ) ) {
			return [];
		}

		return \array_filter(
			$state,
			static fn( $item ) :bool => \is_array( $item ) && isset( $item[ 'notified_at' ] )
		);
	}

	private function store( array $state ) :void {
		self::con()->opts->optSet( self::OPT_KEY, $state )->store();
	}
}
