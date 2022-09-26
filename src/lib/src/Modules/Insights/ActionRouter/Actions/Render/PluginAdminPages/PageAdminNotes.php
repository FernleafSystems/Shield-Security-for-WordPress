<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\AdminNoteBulkAction;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\AdminNoteDelete;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\AdminNoteInsert;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\AdminNotesRender;

class PageAdminNotes extends BasePluginAdminPage {

	const SLUG = 'admin_plugin_page_admin_notes';
	const PRIMARY_MOD = 'plugin';
	const TEMPLATE = '/wpadmin_pages/insights/plugin_admin/notes.twig';

	protected function getRenderData() :array {
		return [
			'ajax'    => [
				'render_table_adminnotes' => ActionData::BuildJson( AdminNotesRender::SLUG ),
				'item_delete'             => ActionData::BuildJson( AdminNoteDelete::SLUG ),
				'item_insert'             => ActionData::BuildJson( AdminNoteInsert::SLUG ),
				'bulk_action'             => ActionData::BuildJson( AdminNoteBulkAction::SLUG ),
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