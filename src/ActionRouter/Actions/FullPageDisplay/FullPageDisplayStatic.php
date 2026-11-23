<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FullPageDisplay;

abstract class FullPageDisplayStatic extends BaseFullPageDisplay {

	public const SLUG = 'display_full_page_static';

	protected function exec() {
		$this->response()->mergePayload( [
			'render_output' => $this->retrieveContent(),
		] )->setPayloadSuccess( true );
	}

	/**
	 * A static display supplies its own content, so it has no render target to check. Nothing a request sends
	 * as one is looked at.
	 */
	protected function checkRenderTarget() :void {
	}

	abstract protected function retrieveContent() :string;
}
