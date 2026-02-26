<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\MainWP;

class MainwpExtensionTableSites extends MainwpBase {

	public const SLUG = 'mainwp_ext_table';

	protected function exec() {
		$this->response()->setPayload( [
			'success' => true,
		] );
	}
}