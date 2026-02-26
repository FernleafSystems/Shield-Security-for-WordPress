<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\FullPageDisplay;

abstract class FullPageDisplayStatic extends BaseFullPageDisplay {

	public const SLUG = 'display_full_page_static';

	protected function exec() {
		$this->response()->mergePayload( [
			'success'       => true,
			'render_output' => $this->retrieveContent(),
		] );
	}

	abstract protected function retrieveContent() :string;
}
