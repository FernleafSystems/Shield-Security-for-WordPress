<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield;

class Import extends Base {

	const SLUG = 'import';

	public function processStepFormSubmit( array $form ) :Shield\Utilities\Response {
		$mod = $this->getCon()->getModule_IPs();
		$opts = $mod->getOptions();

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
		if ( !in_array( $blockLength, [ 'day', 'week', 'month' ], true ) ) {
			throw new \Exception( 'Invalid request.' );
		}

		$csBlock = $form[ 'cs_block' ] ?? '';
		if ( !in_array( $csBlock, [ '', 'Y' ], true ) ) {
			throw new \Exception( 'Invalid request.' );
		}

		$mod->setIsMainFeatureEnabled( true );
		$opts->setOpt( 'transgression_limit', $offenses );
		$opts->setOpt( 'auto_expire', $blockLength );
		$opts->setOpt( 'cs_block', $csBlock === 'Y' ? 'block_with_unblock' : 'disabled' );
		$mod->saveModOptions();

		$resp = parent::processStepFormSubmit( $form );
		$resp->success = true;
		$resp->msg = __( 'IP blocking options have been applied', 'wp-simple-firewall' );
		return $resp;
	}

	public function getName() :string {
		return 'Import';
	}

	protected function getStepRenderData() :array {
		return [
			'strings' => [
				'step_title' => __( 'Import Settings From Another Site', 'wp-simple-firewall' ),
			],
		];
	}

	public function skipStep() :bool {
		return !$this->getCon()->isPremiumActive();
	}
}