<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Route;

class PostureOverview extends PostureBase {

	public function getRoutePath() :string {
		return '/overview';
	}

	protected function getRequestProcessorClass() :string {
		return \FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process\PostureOverview::class;
	}
}
