<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules\StringsOptions;
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

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$opts = self::con()->opts;
		$status = parent::status();

		$keys = [
			'track_logininvalid',
			'track_loginfailed',
			'track_xmlrpc',
			'track_fakewebcrawler',
			'track_404',
			'track_invalidscript',
			'track_useragent',
		];
		$signals = [];
		foreach ( $keys as $key ) {
			$signals[ $key ] = !\in_array( $opts->optGet( $key ), [ 'disabled', 'log' ] );
		}
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

		$optStrings = new StringsOptions();
		foreach ( \array_diff( $keys, $enabledSignals ) as $key ) {
			$status[ 'exp' ][] = sprintf( "Visitors that repeatedly trigger the signal '%s' aren't penalised", $optStrings->getFor( $key )[ 'name' ] );
		}

		return $status;
	}
}