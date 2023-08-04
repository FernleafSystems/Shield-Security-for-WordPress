<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\AdminNoteBulkAction;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\AdminNoteDelete;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\AdminNoteInsert;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\AdminNotes;

class PageAdminNotes extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_admin_notes';
	public const TEMPLATE = '/wpadmin_pages/plugin_admin/notes.twig';

	protected function getRenderData() :array {
		return [
			'ajax'    => [
				'render_adminnotes' => ActionData::BuildJson( AdminNotes::class ),
				'item_delete'       => ActionData::BuildJson( AdminNoteDelete::class ),
				'item_insert'       => ActionData::BuildJson( AdminNoteInsert::class ),
				'bulk_action'       => ActionData::BuildJson( AdminNoteBulkAction::class ),
			],
			'imgs'    => [
				'inner_page_title_icon' => self::con()->svgs->raw( 'pencil-square' ),
			],
			'strings' => [
				'inner_page_title'    => __( 'Administrator Notes', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'Make notes for your future reference.', 'wp-simple-firewall' ),

				'note_add'   => __( 'Add Note', 'wp-simple-firewall' ),
				'note_new'   => __( 'New Note', 'wp-simple-firewall' ),
				'note_enter' => __( 'Enter new note here', 'wp-simple-firewall' ),
			],
		];
	}
}