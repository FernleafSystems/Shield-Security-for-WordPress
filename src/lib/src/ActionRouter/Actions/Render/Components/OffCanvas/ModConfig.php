<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\DynamicLoad\Config;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\DynamicPageLoad;

class ModConfig extends OffCanvasBase {

	public const SLUG = 'offcanvas_modconfig';

	protected function getRenderData() :array {
		$con = self::con();
		$config = $con->cfg->configuration;

		$configItem = $this->action_data[ 'config_item' ] ?? null;

		// Determine what the config item is. We can link to an option, a section, or a whole module.
		if ( isset( $config->modules[ $configItem ] ) ) {
			$itemType = 'module';
			$module = $configItem;
		}
		else {
			$optDef = $config->options[ $configItem ] ?? null;
			if ( empty( $optDef ) ) {
				$itemType = 'section';
				$module = $config->sections[ $configItem ][ 'module' ];
			}
			else {
				$itemType = 'option';
				$module = $config->sections[ $optDef[ 'section' ] ][ 'module' ];
			}
		}

		$content = $con->action_router->action( DynamicPageLoad::class, [
			'dynamic_load_params' => [
				'dynamic_load_slug' => Config::SLUG,
				'dynamic_load_data' => [
					'mod_slug'        => $module,
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