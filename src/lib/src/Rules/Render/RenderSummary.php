<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Render;

class RenderSummary extends RenderBase {

	protected function getTemplateStub() :string {
		return 'summary/summary';
	}
}