<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

/**
 * @phpstan-import-type AttentionItem from \FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildAttentionItems
 * @phpstan-import-type AssessmentRow from ActionsQueueLandingAssessmentBuilder
 */
class ActionsQueueSecurityCheckSource {

	/**
	 * @return list<AttentionItem>
	 */
	public function attentionItems() :array {
		$items = [];
		foreach ( $this->providers() as $provider ) {
			$items = \array_merge( $items, $provider->attentionItems() );
		}
		return $items;
	}

	/**
	 * @return list<AssessmentRow>
	 */
	public function assessmentRows() :array {
		$rows = [];
		foreach ( $this->providers() as $provider ) {
			$rows = \array_merge( $rows, $provider->assessmentRows() );
		}
		return $rows;
	}

	/**
	 * @return list<ActionsQueueSecurityCheckProvider>
	 */
	protected function providers() :array {
		return [
			new HiddenPluginsQueueIssueProvider(),
		];
	}
}
