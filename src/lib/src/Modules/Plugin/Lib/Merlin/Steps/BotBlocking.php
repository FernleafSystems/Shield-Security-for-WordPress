<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield;

class BotBlocking extends Base {

	public const SLUG = 'ip_blocking';

	public function processStepFormSubmit( array $form ) :Shield\Utilities\Response {
		$mod = self::con()->getModule_IPs();
		$opts = $mod->opts();

		$offenses = $form[ 'offenses' ] ?? '';
		if ( empty( $offenses ) ) {
			throw new \Exception( 'Please provide an offense limit' );
		}
		$offenses = (int)$offenses;
		if ( $offenses <= 1 ) {
			throw new \Exception( 'The offense limit should be at least 2' );
		}
		if ( $offenses > 20 ) {
			throw new \Exception( 'The offense limit should be less than 20' );
		}

		$blockLength = $form[ 'block_length' ] ?? '';
		if ( empty( $blockLength ) ) {
			throw new \Exception( 'Please provide a block length' );
		}
		if ( !\in_array( $blockLength, [ 'day', 'week', 'month' ], true ) ) {
			throw new \Exception( 'Invalid request.' );
		}

		$csBlock = $form[ 'cs_block' ] ?? '';
		if ( !\in_array( $csBlock, [ '', 'Y' ], true ) ) {
			throw new \Exception( 'Invalid request.' );
		}

		$mod->setIsMainFeatureEnabled( true );
		$opts->setOpt( 'transgression_limit', $offenses );
		$opts->setOpt( 'auto_expire', $blockLength );
		$opts->setOpt( 'cs_block', $csBlock === 'Y' ? 'block_with_unblock' : 'disabled' );

		$resp = parent::processStepFormSubmit( $form );
		$resp->success = true;
		$resp->message = __( 'IP blocking options have been applied', 'wp-simple-firewall' );
		return $resp;
	}

	public function getName() :string {
		return __( 'Bot Blocking', 'wp-simple-firewall' );
	}

	protected function getStepRenderData() :array {
		/** @var Shield\Modules\IPs\Options $opts */
		$opts = self::con()->getModule_IPs()->opts();
		return [
			'strings' => [
				'step_title' => __( 'Automatically Block Malicious IP Addresses', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'offense_limit' => $opts->getOffenseLimit()
			]
		];
	}
}