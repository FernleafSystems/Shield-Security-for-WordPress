<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Render;

class RenderSummary extends RenderBase {

	protected function getTemplateStub() :string {
		return 'summary/summary';
	}

	protected function getData() :array {
		$rulesCon = $this->getRulesCon();
		$rules = $rulesCon->getRules();

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