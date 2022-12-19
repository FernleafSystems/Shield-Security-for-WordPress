<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

class PageRulesSummary extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_rules_summary';
	public const PRIMARY_MOD = 'plugin';
	public const TEMPLATE = '/wpadmin_pages/insights/plugin_admin/rules_summary.twig';

	protected function getRenderData() :array {
		$rules = $this->getCon()->rules->getRules();

		$components = [
			'hooks' => [
				'immediate'
			],
		];

		$simpleID = 0;
		foreach ( $rules as $rule ) {
			if ( empty( $rule->wp_hook ) ) {
				$rule->wp_hook = 'immediate';
			}
			else {
				$components[ 'hooks' ][] = $rule->wp_hook;
			}
			$rule->simple_id = $simpleID++;
		}

		$components[ 'hooks' ] = array_unique( $components[ 'hooks' ] );

		$hooks = array_map(
			function ( $rule ) {
				return $rule->wp_hook;
			},
			$rules
		);

		return [
			'vars' => [
				'components' => $components,
				'rules'      => $rules,
			]
		];
	}
}