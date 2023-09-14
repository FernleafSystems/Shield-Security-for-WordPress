<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FullPageDisplay;

abstract class FullPageDisplayStatic extends BaseFullPageDisplay {

	public const SLUG = 'display_full_page_static';

	protected function exec() {
		$this->response()->success = true;
		$responseData = $this->response()->action_response_data;
		$responseData[ 'render_output' ] = $this->retrieveContent();
		$this->response()->action_response_data = $responseData;
	}

	abstract protected function retrieveContent() :string;
}