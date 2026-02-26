<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FullPageDisplay;

abstract class FullPageDisplayStatic extends BaseFullPageDisplay {

	public const SLUG = 'display_full_page_static';

	protected function exec() {
		$response = $this->response();
		$responseData = $response->payload();
		$responseData[ 'success' ] = true;
		$responseData[ 'render_output' ] = $this->retrieveContent();

		$this->response()->success = true;
		$response->setPayload( $responseData );
	}

	abstract protected function retrieveContent() :string;
}
