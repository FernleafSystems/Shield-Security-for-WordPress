<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\DynamicLoad\Config;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\DynamicPageLoad;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;

class ModConfig extends OffCanvasBase {

	public const SLUG = 'offcanvas_modconfig';

	protected function getRenderData() :array {
		$con = self::con();

		$module = null;
		$itemType = null;

		$configItem = $this->action_data[ 'config_item' ] ?? null;

		// Determine what the config item is. We can link to an option, a section, or a whole module.
		if ( isset( $con->modules[ $configItem ] ) ) {
			$module = $con->modules[ $configItem ];
			$itemType = 'module';
		}
		else {
			foreach ( $con->modules as $maybe ) {
				if ( \in_array( $configItem, $maybe->opts()->getVisibleOptionsKeys() ) ) {
					$module = $maybe;
					$itemType = 'option';
					break;
				}
				if ( \in_array( $configItem, \array_keys( $maybe->opts()->getSections() ) ) ) {
					$module = $maybe;
					$itemType = 'section';
					break;
				}
			}
		}

		if ( empty( $module ) ) {
			throw new ActionException( "Couldn't determine the module config to load." );
		}

		$content = $con->action_router->action( DynamicPageLoad::class, [
			'dynamic_load_params' => [
				'dynamic_load_slug' => Config::SLUG,
				'dynamic_load_data' => [
					'mod_slug'        => $module->cfg->slug,
					'focus_item'      => $configItem,
					'focus_item_type' => $itemType,
					'form_context'    => 'offcanvas',
				],
			]
		] )->action_response_data;

		return [
			'content' => [
				'canvas_title' => '',
				'canvas_body'  => $content[ 'html' ],
			]
		];
	}
}