<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class Base extends Shield\Utilities\Render\BaseTemplateRenderer {

	use Shield\Modules\ModConsumer;

	const SLUG = '';

	public function getName() :string {
		return 'Title Unset';
	}

	public function skipStep() :bool {
		return false;
	}

	protected function getTemplateBaseDir() :string {
		return '/components/merlin/steps/';
	}

	protected function getTemplateStub() :string {
		return static::SLUG;
	}

	protected function getRenderData() :array {
		$step = $this->getStepRenderData();

		if ( !empty( $step[ 'vars' ][ 'video_id' ] ) ) {
			$step[ 'imgs' ][ 'video_thumb' ] = $this->getVideoThumbnailUrl( $step[ 'vars' ][ 'video_id' ] );
		}

		return Services::DataManipulation()->mergeArraysRecursive(
			$this->getCon()->getModule_Plugin()->getUIHandler()->getBaseDisplayData(),
			$this->getCommonStepRenderData(),
			$step
		);
	}

	/**
	 * @throws \Exception
	 */
	public function processStepFormSubmit( array $form ) :Shield\Utilities\Response {
		$resp = new Shield\Utilities\Response();
		$resp->success = false;
		$resp->error = 'No form processing has been configured for this step';
		$resp->addData( 'page_reload', false );
		return $resp;
	}

	protected function getCommonStepRenderData() :array {
		return [
			'hrefs' => [
				'dashboard' => $this->getCon()->getPluginUrl_DashboardHome(),
				'gopro'     => 'https://shsec.io/ap',
			],
			'imgs'  => [
				'play_button' => $this->getCon()->urls->forImage( 'bootstrap/play-circle.svg' )
			],
			'vars'  => [
				'step_slug' => static::SLUG
			],
		];
	}

	protected function getStepRenderData() :array {
		return [];
	}

	/**
	 * @see https://stackoverflow.com/questions/1361149/get-img-thumbnails-from-vimeo
	 */
	private function getVideoThumbnailUrl( string $videoID ) :string {
		$raw = Services::HttpRequest()
					   ->getContent( sprintf( 'https://vimeo.com/api/v2/video/%s.json', $videoID ) );
		return empty( $raw ) ? '' : json_decode( $raw, true )[ 0 ][ 'thumbnail_large' ];
	}
}