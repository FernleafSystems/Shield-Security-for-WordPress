<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\InstantAlerts\Handlers;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email\InstantAlerts\EmailInstantAlertCloakedPlugins;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins\{
	CloakedPluginFinding,
	CloakedPluginState
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\ReportEmailHold;

class AlertHandlerCloakedPlugins extends AlertHandlerBase {

	public function alertAction() :string {
		return EmailInstantAlertCloakedPlugins::class;
	}

	public function alertTitle() :string {
		return __( 'Cloaked Plugin Detected', 'wp-simple-firewall' );
	}

	public function alertDataKeys() :array {
		return [
			'hidden_plugins',
		];
	}

	public function isImmediateAlert() :bool {
		return true;
	}

	/**
	 * A plugin the user has ignored since its alert was queued (while alerts were held) isn't sent, and a plugin
	 * queued more than once while held is listed once, as it was last found. This reads only the saved ignores,
	 * rather than detecting afresh in the middle of sending. A plugin ignored until it changed, and since changed,
	 * loses its ignore at the next detection and is alerted then.
	 */
	public function alertDataStillToSend( array $alertGroupData ) :array {
		$plugins = $alertGroupData[ 'hidden_plugins' ] ?? null;
		if ( !\is_array( $plugins ) || $plugins === [] ) {
			return [];
		}

		$latest = \array_diff_key( $this->latestByIdentity( $plugins ), ( new CloakedPluginState() )->ignoreRecords() );
		return $latest === [] ? [] : [ 'hidden_plugins' => \array_values( $latest ) ];
	}

	/**
	 * @param array<int|string,mixed> $plugins
	 * @return array<string,array{type:string,file:string,...}>
	 */
	private function latestByIdentity( array $plugins ) :array {
		$latest = [];
		foreach ( $plugins as $plugin ) {
			if ( \is_array( $plugin ) && \is_string( $plugin[ 'type' ] ?? null ) && \is_string( $plugin[ 'file' ] ?? null ) ) {
				$latest[ CloakedPluginFinding::IdentityFor( $plugin[ 'type' ], $plugin[ 'file' ] ) ] = $plugin;
			}
		}
		return $latest;
	}

	public function isSendHeld() :bool {
		return ( new ReportEmailHold() )->isActive();
	}
}
