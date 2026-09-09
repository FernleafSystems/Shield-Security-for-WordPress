<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

/**
 * @phpstan-import-type AttentionItem from \FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SiteQuery\BuildAttentionItems
 * @phpstan-import-type AssessmentRow from ActionsQueueLandingAssessmentBuilder
 */
interface ActionsQueueSecurityCheckProvider {

	/**
	 * @return list<AttentionItem>
	 */
	public function attentionItems() :array;

	/**
	 * @return list<AssessmentRow>
	 */
	public function assessmentRows() :array;
}
