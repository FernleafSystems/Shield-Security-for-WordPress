<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\Requests;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class OffCanvas extends DynPropertiesClass {

	use ModConsumer;

	private $params = [];

	/**
	 * @throws \Exception
	 */
	public function modConfig( string $mod ) :string {
		$content = ( new DynamicContentLoader() )
			->setMod( $this->getMod() )
			->build( [
				'load_params' => [
					'load_type'    => 'configuration',
					'load_variant' => $mod,
				]
			] );

		return $this->getMod()
					->getRenderer()
					->setTemplate( '/components/html/offcanvas_content.twig' )
					->setRenderData( [
						'content' => [
							'canvas_title' => $content[ 'page_title' ],
							'canvas_body'  => $content[ 'html' ],
						]
					] )
					->render();
	}
}