<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Import;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Forms\FormParams;

class PluginImportFromSite extends BaseAction {

	public const SLUG = 'import_from_site';

	protected function exec() {
		$formParams = \array_merge( [
			'confirm'             => 'N',
			'MasterSiteUrl'       => '',
			'MasterSiteSecretKey' => '',
		], FormParams::Retrieve() );

		if ( $formParams[ 'confirm' ] !== 'Y' ) {
			$success = false;
			$msg = __( 'Please check the box to confirm.', 'wp-simple-firewall' );
		}
		else {
			$doNetwork = ( $formParams[ 'ShieldNetwork' ] === 'Y' ) ? true : ( ( $formParams[ 'ShieldNetwork' ] === 'N' ) ? false : null );
			try {
				( new Import() )->fromSite(
					(string)$formParams[ 'MasterSiteUrl' ],
					(string)$formParams[ 'MasterSiteSecretKey' ],
					$doNetwork
				);
				self::con()->opts->optSet( 'importexport_enable', 'Y' );
				$success = true;
				$msg = __( 'Options imported successfully', 'wp-simple-firewall' );
			}
			catch ( \Exception $e ) {
				$success = false;
				$msg = $e->getMessage();
			}
		}

		$this->response()->action_response_data = [
			'success'     => $success,
			'message'     => $msg,
			'page_reload' => $success,
		];
	}
}