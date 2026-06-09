<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginImportFromFileUpload;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\ImportExportController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\NetworkInviteRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\SiteRepository;

/**
 * @phpstan-type SyncState 'unavailable'|'disabled'|'enabled'
 * @phpstan-type ImportExportTab array{
 *   key:'network_sync'|'file',
 *   label:string,
 *   icon_class:string,
 *   panel_id:string,
 *   tab_id:string,
 *   is_active:bool,
 *   is_available:bool
 * }
 * @phpstan-type FileTransferContract array{
 *   section_id:string,
 *   export:array{title:string,summary:string,action_label:string,href:string,icon_class:string},
 *   import:array{
 *     title:string,
 *     summary:string,
 *     form_id:string,
 *     form_action:string,
 *     action_data:array<string,scalar|null>,
 *     file_input:array{id:string,name:string,accept:string,label:string},
 *     confirmation:array{id:string,name:string,value:string,label:string,help:string},
 *     submit:array{id:string,label:string,icon_class:string},
 *     icon_class:string
 *   }
 * }
 * @phpstan-type ChoiceOption array{
 *   id:string,
 *   value:string,
 *   label:string,
 *   summary:string,
 *   is_checked:bool
 * }
 * @phpstan-type NetworkSyncContract array{
 *   section_id:string,
 *   sync_state:SyncState,
 *   is_disabled:bool,
 *   is_enabled:bool,
 *   current_master_url:string,
 *   status:array{
 *     title:string,
 *     summary:string,
 *     connection_label:string,
 *     rail_summary:string
 *   },
 *   toggle:array{id:string,label:string,is_checked:bool},
 *   disabled:array{title:string,summary:string},
 *   tasks:list<array{key:'connect'|'clients',title:string,summary:string,icon_class:string,is_active:bool}>,
 *   connect:array{
 *     title:string,
 *     summary:string,
 *     form_id:string,
 *     master_site_url_id:string,
 *     master_site_url_name:string,
 *     master_site_url_label:string,
 *     master_site_url_placeholder:string,
 *     import_mode_label:string,
 *     import_mode_options:list<ChoiceOption>,
 *     verification_label:string,
 *     verification_options:list<ChoiceOption>,
 *     secret_key_id:string,
 *     secret_key_name:string,
 *     secret_key_label:string,
 *     secret_key_placeholder:string,
 *     confirm_id:string,
 *     confirm_name:string,
 *     confirm_value:string,
 *     confirm_label:string,
 *     confirm_help:string,
 *     submit_label_once:string,
 *     submit_label_network:string,
 *     submit_icon_class:string,
 *     disconnect:array{is_available:bool,label:string}
 *   },
 *   clients:array{
 *     title:string,
 *     summary:string,
 *     add_label:string,
 *     count_label:string,
 *     table_id:string
 *   }
 * }
 * @phpstan-type NetworkInviteReviewContract array{
 *   invite:array{id:string,master_url:string,created_at:int,updated_at:int,review_url:string},
 *   strings:array<string,string>
 * }
 * @phpstan-type ImportExportRenderData array{
 *   flags:array{
 *     can_importexport:bool,
 *     can_importexport_file:bool,
 *     can_importexport_sync:bool,
 *     has_network_invite_review:bool,
 *     network_sync_state:SyncState
 *   },
 *   imgs:array{inner_page_title_icon:string},
 *   vars:array{
 *     import_export_tabs:list<ImportExportTab>,
 *     file_transfer:FileTransferContract,
 *     network_sync:NetworkSyncContract,
 *     network_invite_review:NetworkInviteReviewContract|null
 *   },
 *   strings:array{inner_page_title:string,inner_page_subtitle:string}
 * }
 */
class PageImportExport extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_importexport';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/import.twig';

	protected function getPageContextualHrefs_Help() :array {
		return [
			'title'      => sprintf( '%s: %s/%s', CommonDisplayStrings::get( 'help_label' ), __( 'Import', 'wp-simple-firewall' ), __( 'Export', 'wp-simple-firewall' ) ),
			'href'       => 'https://help.getshieldsecurity.com/article/129-how-to-create-shield-security-network-with-automatic-import-export-feature',
			'new_window' => true,
		];
	}

	/**
	 * @return ImportExportRenderData
	 */
	protected function getRenderData() :array {
		$con = self::con();
		$importExport = $con->comps->import_export;
		$importMasterURL = $importExport->getImportExportMasterImportUrl();
		$canImportExportFile = $con->caps->canImportExportFile();
		$canImportExportSync = $importExport->isSyncAvailable();
		$networkSyncState = $importExport->networkSyncState();
		$authorisedURLCount = ( new SiteRepository() )->countActiveRows();
		$networkInviteReview = $this->buildNetworkInviteReview();
		$activeTab = $canImportExportSync ? 'network_sync' : 'file';

		return [
			'flags'   => [
				'can_importexport'          => $canImportExportFile || $canImportExportSync,
				'can_importexport_file'     => $canImportExportFile,
				'can_importexport_sync'     => $canImportExportSync,
				'has_network_invite_review' => $networkInviteReview !== null,
				'network_sync_state'        => $networkSyncState,
			],
			'imgs'    => [
				'inner_page_title_icon' => $con->svgs->iconClass( 'arrow-down-up' ),
			],
			'vars'    => [
				'import_export_tabs'    => $this->buildImportExportTabs( $activeTab, $canImportExportFile, $canImportExportSync ),
				'file_transfer'         => $this->buildFileTransfer(),
				'network_sync'          => $this->buildNetworkSync( $networkSyncState, $importMasterURL, $authorisedURLCount ),
				'network_invite_review' => $networkInviteReview,
			],
			'strings' => [
				'inner_page_title'    => __( 'Import/Export', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'Import, export, and network sync settings between Shield sites.', 'wp-simple-firewall' ),
			]
		];
	}

	/**
	 * @return list<ImportExportTab>
	 */
	private function buildImportExportTabs( string $activeTab, bool $canImportExportFile, bool $canImportExportSync ) :array {
		return [
			$this->buildTab( 'network_sync', __( 'Network Sync', 'wp-simple-firewall' ), 'bi bi-diagram-3', $activeTab === 'network_sync', $canImportExportSync ),
			$this->buildTab( 'file', __( 'Import/Export File', 'wp-simple-firewall' ), 'bi bi-arrow-down-up', $activeTab === 'file', $canImportExportFile ),
		];
	}

	/**
	 * @param 'network_sync'|'file' $key
	 * @return ImportExportTab
	 */
	private function buildTab( string $key, string $label, string $iconClass, bool $active, bool $available ) :array {
		return [
			'key'          => $key,
			'label'        => $label,
			'icon_class'   => $iconClass,
			'panel_id'     => 'ImportExportPanel-'.\str_replace( '_', '-', $key ),
			'tab_id'       => 'ImportExportTab-'.\str_replace( '_', '-', $key ),
			'is_active'    => $active,
			'is_available' => $available,
		];
	}

	/**
	 * @return FileTransferContract
	 */
	private function buildFileTransfer() :array {
		$con = self::con();
		return [
			'section_id' => 'SectionImportExportFile',
			'export'     => [
				'title'        => __( 'Export settings', 'wp-simple-firewall' ),
				'summary'      => __( 'Download current Shield configuration.', 'wp-simple-firewall' ),
				'action_label' => __( 'Download', 'wp-simple-firewall' ),
				'href'         => $con->plugin_urls->fileDownload( 'plugin_export' ),
				'icon_class'   => 'bi bi-download',
			],
			'import'     => [
				'title'        => __( 'Import settings', 'wp-simple-firewall' ),
				'summary'      => __( 'Upload a Shield export file.', 'wp-simple-firewall' ),
				'form_id'      => 'ImportExportFileForm',
				'form_action'  => '#',
				'action_data'  => ActionData::Build( PluginImportFromFileUpload::class, true, [
					'notification_type' => 'wp_admin_notice'
				] ),
				'file_input'   => [
					'id'     => 'ImportFile',
					'name'   => 'import_file',
					'accept' => '.json',
					'label'  => __( 'Select export file', 'wp-simple-firewall' ),
				],
				'confirmation' => [
					'id'    => '_confirm_file',
					'name'  => 'confirm',
					'value' => 'Y',
					'label' => __( 'I understand existing options will be overwritten.', 'wp-simple-firewall' ),
					'help'  => __( 'This action cannot be undone.', 'wp-simple-firewall' ),
				],
				'submit'       => [
					'id'         => 'SubmitForm',
					'label'      => __( 'Import Options', 'wp-simple-firewall' ),
					'icon_class' => 'bi bi-upload',
				],
				'icon_class'   => 'bi bi-upload',
			],
		];
	}

	/**
	 * @param SyncState $networkSyncState
	 * @return NetworkSyncContract
	 */
	private function buildNetworkSync( string $networkSyncState, string $importMasterURL, int $authorisedURLCount ) :array {
		$hasMasterURL = !empty( $importMasterURL );
		$isEnabled = $networkSyncState === ImportExportController::SYNC_STATE_ENABLED;
		$isDisabled = $networkSyncState === ImportExportController::SYNC_STATE_DISABLED;

		return [
			'section_id'           => 'SectionImportExportNetworkSync',
			'sync_state'           => $networkSyncState,
			'is_disabled'          => $isDisabled,
			'is_enabled'           => $isEnabled,
			'current_master_url'   => $importMasterURL,
			'status'               => [
				'title'            => $isEnabled ? __( 'Network sync enabled', 'wp-simple-firewall' ) : __( 'Network sync disabled', 'wp-simple-firewall' ),
				'summary'          => $this->buildNetworkSummary( $isEnabled, $hasMasterURL, $importMasterURL, $authorisedURLCount ),
				'connection_label' => $hasMasterURL ? __( 'Connected to master', 'wp-simple-firewall' ) : __( 'Not connected to master', 'wp-simple-firewall' ),
				'rail_summary'     => $authorisedURLCount > 0
					? sprintf(
						_n( '%s client site can import from here.', '%s client sites can import from here.', $authorisedURLCount, 'wp-simple-firewall' ),
						$authorisedURLCount
					)
					: __( 'No client sites currently import from here.', 'wp-simple-firewall' ),
			],
			'toggle'               => [
				'id'          => 'ImportExportNetworkToggle',
				'label'       => __( 'Automatic Network Import/Export', 'wp-simple-firewall' ),
				'is_checked'  => $isEnabled,
			],
			'disabled'             => [
				'title'   => __( 'Network import/export is off', 'wp-simple-firewall' ),
				'summary' => __( 'Turn it on to connect this site or manage client sites.', 'wp-simple-firewall' ),
			],
			'tasks'                => [
				[
					'key'        => 'connect',
					'title'      => __( 'Connect to network', 'wp-simple-firewall' ),
					'summary'    => __( 'Import settings from a master site.', 'wp-simple-firewall' ),
					'icon_class' => 'bi bi-link-45deg',
					'is_active'  => true,
				],
				[
					'key'        => 'clients',
					'title'      => __( 'Manage client sites', 'wp-simple-firewall' ),
					'summary'    => __( 'Let other Shield sites import from here.', 'wp-simple-firewall' ),
					'icon_class' => 'bi bi-display',
					'is_active'  => false,
				],
			],
			'connect'              => [
				'title'                       => __( 'Connect to network', 'wp-simple-firewall' ),
				'summary'                     => __( 'Use master site URL. Choose import type and trust method.', 'wp-simple-firewall' ),
				'form_id'                     => 'ImportSiteForm',
				'master_site_url_id'          => 'MasterSiteUrl',
				'master_site_url_name'        => 'MasterSiteUrl',
				'master_site_url_label'       => __( 'Master site URL', 'wp-simple-firewall' ),
				'master_site_url_placeholder' => $hasMasterURL ? $importMasterURL : 'https://www...',
				'import_mode_label'           => __( 'Import type', 'wp-simple-firewall' ),
				'import_mode_options'         => [
					[
						'id'         => 'ShieldNetworkImportOnce',
						'value'      => 'NC',
						'label'      => __( 'Import once', 'wp-simple-firewall' ),
						'summary'    => __( 'Do not stay linked.', 'wp-simple-firewall' ),
						'is_checked' => true,
					],
					[
						'id'         => 'ShieldNetworkJoin',
						'value'      => 'Y',
						'label'      => __( 'Join network', 'wp-simple-firewall' ),
						'summary'    => __( 'Keep automatic imports linked.', 'wp-simple-firewall' ),
						'is_checked' => false,
					],
				],
				'verification_label'          => __( 'Master site verification', 'wp-simple-firewall' ),
				'verification_options'        => [
					[
						'id'         => 'MasterSiteTrusted',
						'value'      => 'trusted',
						'label'      => __( 'Master site already trusts this site', 'wp-simple-firewall' ),
						'summary'    => __( 'No secret key needed.', 'wp-simple-firewall' ),
						'is_checked' => true,
					],
					[
						'id'         => 'MasterSiteUseKey',
						'value'      => 'key',
						'label'      => __( 'Use master site secret key', 'wp-simple-firewall' ),
						'summary'    => __( 'Paste key from master site.', 'wp-simple-firewall' ),
						'is_checked' => false,
					],
				],
				'secret_key_id'               => 'MasterSiteSecretKey',
				'secret_key_name'             => 'MasterSiteSecretKey',
				'secret_key_label'            => __( 'Master site secret key', 'wp-simple-firewall' ),
				'secret_key_placeholder'      => __( 'Paste secret key from master site', 'wp-simple-firewall' ),
				'confirm_id'                  => '_confirm_site',
				'confirm_name'                => 'confirm',
				'confirm_value'               => 'Y',
				'confirm_label'               => __( 'I understand existing options will be overwritten.', 'wp-simple-firewall' ),
				'confirm_help'                => __( 'This action cannot be undone.', 'wp-simple-firewall' ),
				'submit_label_once'           => __( 'Import settings', 'wp-simple-firewall' ),
				'submit_label_network'        => __( 'Join network', 'wp-simple-firewall' ),
				'submit_icon_class'           => 'bi bi-cloud-download',
				'disconnect'                  => [
					'is_available' => $hasMasterURL,
					'label'        => __( 'Disconnect', 'wp-simple-firewall' ),
				],
			],
			'clients'              => [
				'title'              => __( 'Manage client sites', 'wp-simple-firewall' ),
				'summary'            => __( 'Share this site\'s settings with approved Shield sites.', 'wp-simple-firewall' ),
				'add_label'          => __( 'Add client sites', 'wp-simple-firewall' ),
				'count_label'        => sprintf(
					_n( '%s client site', '%s client sites', $authorisedURLCount, 'wp-simple-firewall' ),
					$authorisedURLCount
				),
				'table_id'           => 'ShieldTable-ImportExportSites',
			],
		];
	}

	private function buildNetworkSummary( bool $isEnabled, bool $hasMasterURL, string $importMasterURL, int $authorisedURLCount ) :string {
		if ( !$isEnabled ) {
			return __( 'This site stays fully local.', 'wp-simple-firewall' );
		}
		if ( $hasMasterURL ) {
			return sprintf( __( 'Importing settings from %s.', 'wp-simple-firewall' ), $importMasterURL );
		}
		return $authorisedURLCount > 0
			? __( 'Not importing from a master site. Client sites may import from here.', 'wp-simple-firewall' )
			: __( 'Not importing from a master site. Connect to a network, or manage client sites.', 'wp-simple-firewall' );
	}

	/**
	 * @return NetworkInviteReviewContract|null
	 */
	private function buildNetworkInviteReview() :?array {
		$invite = ( new NetworkInviteRepository() )->find(
			(string)( $this->action_data[ NetworkInviteRepository::REVIEW_QUERY_KEY ] ?? '' )
		);
		if ( $invite === null ) {
			return null;
		}

		return [
			'invite'  => $invite,
			'strings' => [
				'title'          => __( 'Review Network Invite', 'wp-simple-firewall' ),
				'summary'        => __( 'A Shield site has invited this site to join its import/export network.', 'wp-simple-firewall' ),
				'master_url'     => __( 'Master Site URL', 'wp-simple-firewall' ),
				'implications'   => __( 'Accepting will import transferable Shield settings from the master site and set this site to import from that master during normal sync.', 'wp-simple-firewall' ),
				'confirm_label'  => __( 'I understand this will import Shield settings from the master site and set it as this site\'s master.', 'wp-simple-firewall' ),
				'accept_button'  => __( 'Accept Invite', 'wp-simple-firewall' ),
				'reject_button'  => __( 'Reject Invite', 'wp-simple-firewall' ),
			],
		];
	}
}
