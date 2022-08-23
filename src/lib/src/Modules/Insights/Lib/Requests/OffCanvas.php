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
	public function modConfig( string $configItem ) :string {
		$con = $this->getCon();

		$module = null;
		$itemType = null;

		// Determine what the config item is. We can link to an option, a section, or a whole module.
		if ( isset( $con->modules[ $configItem ] ) ) {
			$module = $con->modules[ $configItem ];
			$itemType = 'module';
		}
		else {
			foreach ( $con->modules as $maybe ) {
				if ( in_array( $configItem, $maybe->getOptions()->getVisibleOptionsKeys() ) ) {
					$module = $maybe;
					$itemType = 'option';
					break;
				}
				if ( in_array( $configItem, array_keys( $maybe->getOptions()->getSections() ) ) ) {
					$module = $maybe;
					$itemType = 'section';
					break;
				}
			}
		}

		if ( empty( $module ) ) {
			throw new \Exception( "Couldn't determine the module config to load." );
		}

		$content = ( new DynamicContentLoader() )
			->setMod( $this->getMod() )
			->build( [
				'load_params' => [
					'load_type'    => 'configuration',
					'load_variant' => $module->getSlug(),
					'aux_params'   => [
						'focus_item'      => $configItem,
						'focus_item_type' => $itemType,
					],
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