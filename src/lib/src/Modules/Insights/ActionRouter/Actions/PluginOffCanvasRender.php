<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\Requests\OffCanvas;
use FernleafSystems\Wordpress\Services\Services;

class PluginOffCanvasRender extends PluginBase {

	const SLUG = 'offcanvas_render';

	/**
	 * @inheritDoc
	 */
	protected function exec() {
		$resp = $this->response();
		$req = Services::Request();
		switch ( $req->post( 'offcanvas_type' ) ) {

			case 'mod_config':
				try {
					$html = ( new OffCanvas() )
						->setMod( $this->getCon()->getModule_Insights() )
						->modConfig( $req->post( 'config_item' ) );
					$success = true;
				}
				catch ( \Exception $e ) {
					$html = 'error rendering: '.$e->getMessage();
					$success = false;
				}
				break;

			case 'ip_analysis':
				try {
					$html = ( new OffCanvas() )
						->setMod( $this->getCon()->getModule_Insights() )
						->ipAnalysis( $req->post( 'ip' ) );
					$success = true;
				}
				catch ( \Exception $e ) {
					$html = $e->getMessage();
					$success = false;
				}
				break;

			case 'meter_analysis':
				try {
					$html = ( new Handler() )
						->setMod( $this->getMod() )
						->renderAnalysis( $req->post( 'meter' ) );
					$success = true;
				}
				catch ( \Exception $e ) {
					$html = $e->getMessage();
					$success = false;
				}
				break;
		}

		$resp->action_response_data = [
			'success' => $success ?? false,
			'html'    => $html ?? ''
		];
	}
}