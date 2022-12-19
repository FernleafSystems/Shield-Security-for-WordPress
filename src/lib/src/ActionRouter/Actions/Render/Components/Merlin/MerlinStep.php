<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Merlin;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Services\Services;

class MerlinStep extends BaseRender {

	public const SLUG = 'render_merlin_step';
	public const TEMPLATE = '/components/merlin/steps/%s.twig';

	protected function getRenderData() :array {
		return [
			'hrefs' => [
				'dashboard' => $this->getCon()->plugin_urls->adminHome(),
				'gopro'     => 'https://shsec.io/ap',
			],
			'imgs'  => [
				'play_button' => $this->getCon()->urls->forImage( 'bootstrap/play-circle.svg' ),
				'video_thumb' => $this->getVideoThumbnailUrl( $step[ 'vars' ][ 'video_id' ] ?? '' )
			],
		];
	}

	protected function getRenderTemplate() :string {
		return sprintf( parent::getRenderTemplate(), $this->action_data[ 'vars' ][ 'step_slug' ] );
	}

	/**
	 * @see https://stackoverflow.com/questions/1361149/get-img-thumbnails-from-vimeo
	 */
	private function getVideoThumbnailUrl( string $videoID ) :string {
		$thumbnail = '';
		if ( !empty( $videoID ) ) {
			$raw = Services::HttpRequest()->getContent( sprintf( 'https://vimeo.com/api/v2/video/%s.json', $videoID ) );
			if ( !empty( $raw ) ) {
				$thumbnail = json_decode( $raw, true )[ 0 ][ 'thumbnail_large' ] ?? '';
			}
		}
		return $thumbnail;
	}
}