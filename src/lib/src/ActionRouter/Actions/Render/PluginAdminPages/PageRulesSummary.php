<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\ExtractSubConditions;

class PageRulesSummary extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_rules_summary';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/rules_summary.twig';

	protected function getRenderData() :array {
		$components = [
			'hooks' => [
				'immediate'
			],
		];

		$simpleID = 0;

		$rules = [];
		foreach ( self::con()->rules->getRules() as $idx => $rule ) {

			if ( empty( $rule->wp_hook ) ) {
				$rule->wp_hook = 'immediate';
			}

			$components[ 'hooks' ][] = $rule->wp_hook;

			try {
				$data = $rule->getRawData();
				$data[ 'simple_id' ] = $simpleID++;
				$data[ 'sub_conditions' ] = \array_map(
					function ( string $conditionClass ) {
						$cond = new $conditionClass();
						return [
							'name' => $cond->getName(),
						];
					},
					( new ExtractSubConditions() )->fromRule( $rule )[ 'classes' ]
				);

				$rules[ $data[ 'simple_id' ] ] = $data;
			}
			catch ( \Exception $e ) {
			}
		}

		$components[ 'hooks' ] = \array_unique( $components[ 'hooks' ] );

		return [
			'vars' => [
				'components' => $components,
				'rules'      => $rules,
			]
		];
	}
}