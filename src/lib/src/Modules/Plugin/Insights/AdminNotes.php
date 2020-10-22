<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Insights;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class AdminNotes {

	use ModConsumer;

	public function build() :string {
		return $this->getMod()
					->renderTemplate(
						'/wpadmin_pages/insights/notes/admin_notes.twig',
						$this->buildData(),
						true
					);
	}

	private function buildData() :array {
		$con = $this->getCon();
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $mod */
		$mod = $this->getMod();

		return [
			'ajax'    => [
				'render_table_adminnotes' => $mod->getAjaxActionData( 'render_table_adminnotes', true ),
				'item_action_notes'       => $mod->getAjaxActionData( 'item_action_notes', true ),
				'item_delete'             => $mod->getAjaxActionData( 'note_delete', true ),
				'item_insert'             => $mod->getAjaxActionData( 'note_insert', true ),
				'bulk_action'             => $mod->getAjaxActionData( 'bulk_action', true ),
			],
			'flags'   => [
				'can_adminnotes' => $con->isPremiumActive(),
			],
			'strings' => [
				'note_title'    => __( 'Administrator Notes', 'wp-simple-firewall' ),
				'use_this_area' => __( 'Use this feature to make ongoing notes and to-dos', 'wp-simple-firewall' ),
				'note_add'      => __( 'Add Note', 'wp-simple-firewall' ),
				'note_new'      => __( 'New Note', 'wp-simple-firewall' ),
				'note_enter'    => __( 'Enter new note here', 'wp-simple-firewall' ),
			],
		];
	}
}