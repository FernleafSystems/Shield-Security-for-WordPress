<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Request\FormParams;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Import;

class PluginImportFromSite extends PluginBase {

	const SLUG = 'import_from_site';

	/**
	 * @inheritDoc
	 */
	protected function exec() {
		$success = false;
		$formParams = array_merge( [
			'confirm'             => 'N',
			'MasterSiteUrl'       => '',
			'MasterSiteSecretKey' => '',
		], FormParams::Retrieve() );

		// TODO: align with wizard AND combine with file upload errors
		if ( $formParams[ 'confirm' ] !== 'Y' ) {
			$msg = __( 'Please check the box to confirm your intent to overwrite settings', 'wp-simple-firewall' );
		}
		else {
			$doNetwork = ( $formParams[ 'ShieldNetwork' ] === 'Y' ) ? true : ( ( $formParams[ 'ShieldNetwork' ] === 'N' ) ? false : null );
			try {
				$code = ( new Import() )
					->setMod( $this->primary_mod )
					->fromSite( (string)$formParams[ 'MasterSiteUrl' ], (string)$formParams[ 'MasterSiteSecretKey' ], $doNetwork );
			}
			catch ( \Exception $e ) {
				$code = $e->getCode();
			}
			$success = $code == 0;
			$msg = $success ? __( 'Options imported successfully', 'wp-simple-firewall' ) : __( 'Options failed to import', 'wp-simple-firewall' );
		}

		$this->response()->action_response_data = [
			'success' => $success,
			'message' => $msg
		];
	}
}