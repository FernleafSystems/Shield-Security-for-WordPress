<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class ScansCheck extends ScansBase {

	public const SLUG = 'scans_check';

	protected function exec() {
		$this->response()
			 ->setPayload( $this->buildScanProgressPayload( $this->startedScanIdsFromActionData() ) )
			 ->setPayloadSuccess( true );
	}
}
