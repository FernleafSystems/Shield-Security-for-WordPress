<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginImportExport_Enable;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginImportFromFileUpload;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsFormFor;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\NetworkInviteRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\ImportExportController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\SiteRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\GetOptionsForZoneComponents;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\ImportExport;

/**
 * @phpstan-type SyncState 'unavailable'|'disabled'|'enabled'
 * @phpstan-type ImportExportTab array{
 *   key:'file'|'network_setup'|'sync_sites',
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
 * @phpstan-type NetworkStatusCard array{
 *   status:'good'|'info'|'neutral',
 *   icon_class:string,
 *   title:string,
 *   status_label:string,
 *   oneliner:string,
 *   is_active:bool
 * }
 * @phpstan-type NetworkOptionContract array{id:string,value:'Y'|'N'|'NC',label:string,is_checked:bool}
 * @phpstan-type NetworkSetupContract array{
 *   title:string,
 *   summary:string,
 *   current_master_url:string,
 *   has_master_url:bool,
 *   master_url_label:string,
 *   authorised_url_count:int,
 *   status_cards:list<NetworkStatusCard>,
 *   setup_form:array{
 *     title:string,
 *     summary:string,
 *     master_site_url:string,
 *     master_site_url_id:string,
 *     master_site_url_name:string,
 *     master_site_placeholder:string,
 *     master_site_help_id:string,
 *     master_site_help:string,
 *     secret_key:string,
 *     secret_key_id:string,
 *     secret_key_name:string,
 *     secret_key_placeholder:string,
 *     secret_key_help_id:string,
 *     secret_key_help:string,
 *     network_label:string,
 *     network_help_id:string,
 *     network_help:string,
 *     network_options:list<NetworkOptionContract>,
 *     confirm_id:string,
 *     confirm_name:string,
 *     confirm_value:string,
 *     confirm_label:string,
 *     confirm_help:string,
 *     submit_label:string,
 *     submit_icon_class:string
 *   },
 *   advanced_settings_title:string,
 *   advanced_settings_summary:string
 * }
 * @phpstan-type SyncSitesDisabledAction array{
 *   is_action:true,
 *   type:'navigate',
 *   label:string,
 *   href:string,
 *   icon_class:string,
 *   class_name:string,
 *   tooltip_attr:string,
 *   target:string,
 *   rel:string,
 *   attributes:array<string,string>
 * }
 * @phpstan-type SyncSitesDisabledPane array{message:string,actions:list<SyncSitesDisabledAction>}
 * @phpstan-type SyncSitesContract array{
 *   sync_state:SyncState,
 *   table_id:string,
 *   authorised_url_count:int,
 *   authorised_url_count_label:string,
 *   title:string,
 *   summary:string,
 *   enabled_title:string,
 *   current_summary:string,
 *   disabled_pane:SyncSitesDisabledPane,
 *   is_unavailable:bool,
 *   is_disabled:bool,
 *   is_enabled:bool
 * }
 * @phpstan-type NetworkInviteReviewContract array{
 *   invite:array{id:string,master_url:string,created_at:int,updated_at:int,review_url:string},
 *   strings:array<string,string>
 * }
 * @phpstan-type ImportExportRenderData array{
 *   content:array{import_export_config:string},
 *   flags:array{
 *     can_importexport:bool,
 *     can_importexport_file:bool,
 *     can_importexport_sync:bool,
 *     has_master_url:bool,
 *     has_network_invite_review:bool,
 *     sync_sites_state:SyncState
 *   },
 *   imgs:array{inner_page_title_icon:string},
 *   vars:array{
 *     import_export_tabs:list<ImportExportTab>,
 *     file_transfer:FileTransferContract,
 *     network_setup:NetworkSetupContract,
 *     sync_sites:SyncSitesContract,
 *     network_invite_review:NetworkInviteReviewContract|array{}
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
		$syncSitesState = $importExport->syncSitesState();
		$authorisedURLCount = ( new SiteRepository() )->countActiveRows();
		$networkInviteReview = $this->buildNetworkInviteReview();
		$activeTab = $canImportExportFile ? 'file' : ( $canImportExportSync ? 'network_setup' : 'file' );
		return [
			'content' => [
				'import_export_config' => $con->action_router->render( OptionsFormFor::class, [
					'options' => ( new GetOptionsForZoneComponents() )->run( [ ImportExport::Slug() ] )
				] ),
			],
			'flags'   => [
				'can_importexport'       => $canImportExportFile || $canImportExportSync,
				'can_importexport_file'  => $canImportExportFile,
				'can_importexport_sync'  => $canImportExportSync,
				'has_master_url'         => !empty( $importMasterURL ),
				'has_network_invite_review' => !empty( $networkInviteReview ),
				'sync_sites_state'       => $syncSitesState,
			],
			'imgs'    => [
				'inner_page_title_icon' => $con->svgs->iconClass( 'arrow-down-up' ),
			],
			'vars'    => [
				'import_export_tabs' => $this->buildImportExportTabs( $activeTab, $canImportExportFile, $canImportExportSync ),
				'file_transfer'      => $this->buildFileTransfer(),
				'network_setup'      => $this->buildNetworkSetup( $importMasterURL, $authorisedURLCount ),
				'sync_sites'         => $this->buildSyncSites( $syncSitesState, $authorisedURLCount ),
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
			$this->buildTab( 'file', __( 'Import/Export', 'wp-simple-firewall' ), 'bi bi-arrow-down-up', $activeTab === 'file', $canImportExportFile ),
			$this->buildTab( 'network_setup', __( 'Network Setup', 'wp-simple-firewall' ), 'bi bi-diagram-3', $activeTab === 'network_setup', $canImportExportSync ),
			$this->buildTab( 'sync_sites', __( 'Sync Sites', 'wp-simple-firewall' ), 'bi bi-hdd-network', $activeTab === 'sync_sites', $canImportExportSync ),
		];
	}

	/**
	 * @param 'file'|'network_setup'|'sync_sites' $key
	 * @return ImportExportTab
	 */
	private function buildTab( string $key, string $label, string $iconClass, bool $active, bool $available ) :array {
		return [
			'key'         => $key,
			'label'       => $label,
			'icon_class'  => $iconClass,
			'panel_id'    => 'ImportExportPanel-'.\str_replace( '_', '-', $key ),
			'tab_id'      => 'ImportExportTab-'.\str_replace( '_', '-', $key ),
			'is_active'   => $active,
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
				'title'        => __( 'Download Export File', 'wp-simple-firewall' ),
				'summary'      => __( 'Create a Shield options export file from this site.', 'wp-simple-firewall' ),
				'action_label' => __( 'Download Export File', 'wp-simple-firewall' ),
				'href'         => $con->plugin_urls->fileDownload( 'plugin_export' ),
				'icon_class'   => 'bi bi-download',
			],
			'import'     => [
				'title'        => __( 'Import From File', 'wp-simple-firewall' ),
				'summary'      => __( 'Upload a Shield export file and replace this site\'s current transferable options.', 'wp-simple-firewall' ),
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
	 * @return NetworkSetupContract
	 */
	private function buildNetworkSetup( string $importMasterURL, int $authorisedURLCount ) :array {
		$con = self::con();
		$hasMasterURL = !empty( $importMasterURL );
		return [
			'title'                 => __( 'Network Setup', 'wp-simple-firewall' ),
			'summary'               => __( 'Connect this site to a master site, or prepare this site to serve settings to other Shield sites.', 'wp-simple-firewall' ),
			'current_master_url'    => $importMasterURL,
			'has_master_url'        => $hasMasterURL,
			'master_url_label'      => __( 'Current Master Site URL', 'wp-simple-firewall' ),
			'authorised_url_count'  => $authorisedURLCount,
			'status_cards'          => $this->buildNetworkStatusCards( $hasMasterURL, $importMasterURL, $authorisedURLCount ),
			'setup_form'            => [
				'title'                  => __( 'Import From A Master Site', 'wp-simple-firewall' ),
				'summary'                => __( 'Run a one-time import from another Shield site and choose whether this site should remember that site as its master.', 'wp-simple-firewall' ),
				'master_site_url'        => __( 'Master Site URL', 'wp-simple-firewall' ),
				'master_site_url_id'     => 'MasterSiteUrl',
				'master_site_url_name'   => 'MasterSiteUrl',
				'master_site_placeholder' => $hasMasterURL ? $importMasterURL : 'https://www...',
				'master_site_help_id'    => 'ImportExportMasterSiteUrlHelp',
				'master_site_help'       => sprintf(
				/* translators: %1$s: https protocol, %2$s: http protocol */
					__( 'Remember to include %1$s or %2$s.', 'wp-simple-firewall' ),
					'https://',
					'http://'
				),
				'secret_key'             => __( 'Master Site Secret Key', 'wp-simple-firewall' ),
				'secret_key_id'          => 'MasterSiteSecretKey',
				'secret_key_name'        => 'MasterSiteSecretKey',
				'secret_key_placeholder' => __( 'Secret Key', 'wp-simple-firewall' ),
				'secret_key_help_id'     => 'ImportExportMasterSiteSecretKeyHelp',
				'secret_key_help'        => sprintf( __( 'The secret key is found in: %s', 'wp-simple-firewall' ),
					/* translators: %1$s: top-level, %2$s: 2nd-level; %3$s: 3rd level */
					\ucwords( sprintf( __( '%1$s > %2$s > %3$s', 'wp-simple-firewall' ), __( 'General Settings', 'wp-simple-firewall' ), __( 'Import/Export', 'wp-simple-firewall' ), __( 'Secret Key', 'wp-simple-firewall' ) ) )
				),
				'network_label'          => sprintf( __( 'Create %s Network?', 'wp-simple-firewall' ), $con->labels->Name ),
				'network_help_id'        => 'ImportExportNetworkHelp',
				'network_help'           => \implode( ' ', [
					__( 'Turn this on to link this site to the master site.', 'wp-simple-firewall' ),
					__( 'Options will be automatically imported from the master site each night.', 'wp-simple-firewall' ),
					__( 'Choose No Change to run the import without changing the saved network setup.', 'wp-simple-firewall' ),
				] ),
				'network_options'        => [
					[ 'id' => 'ShieldNetworkOn', 'value' => 'Y', 'label' => __( 'Turn On', 'wp-simple-firewall' ), 'is_checked' => false ],
					[ 'id' => 'ShieldNetworkOff', 'value' => 'N', 'label' => __( 'Turn Off', 'wp-simple-firewall' ), 'is_checked' => false ],
					[ 'id' => 'ShieldNetworkNoChange', 'value' => 'NC', 'label' => __( 'No Change', 'wp-simple-firewall' ), 'is_checked' => true ],
				],
				'confirm_id'             => '_confirm_site',
				'confirm_name'           => 'confirm',
				'confirm_value'          => 'Y',
				'confirm_label'          => __( 'I understand existing options will be overwritten.', 'wp-simple-firewall' ),
				'confirm_help'           => __( 'This action cannot be undone.', 'wp-simple-firewall' ),
				'submit_label'           => __( 'Import Options', 'wp-simple-firewall' ),
				'submit_icon_class'      => 'bi bi-cloud-download',
			],
			'advanced_settings_title'   => __( 'Advanced Settings', 'wp-simple-firewall' ),
			'advanced_settings_summary' => __( 'Manage automatic import/export options and the secret key.', 'wp-simple-firewall' ),
		];
	}

	/**
	 * @return list<NetworkStatusCard>
	 */
	private function buildNetworkStatusCards( bool $hasMasterURL, string $importMasterURL, int $authorisedURLCount ) :array {
		$isStandalone = !$hasMasterURL && $authorisedURLCount === 0;
		return [
			[
				'status'       => $isStandalone ? 'good' : 'neutral',
				'icon_class'   => 'bi bi-pc-display-horizontal',
				'title'        => __( 'Standalone', 'wp-simple-firewall' ),
				'status_label' => $isStandalone ? __( 'Active', 'wp-simple-firewall' ) : __( 'Inactive', 'wp-simple-firewall' ),
				'oneliner'     => $isStandalone ? __( 'No master site or sync sites are configured.', 'wp-simple-firewall' ) : __( 'Network settings are configured for this site.', 'wp-simple-firewall' ),
				'is_active'    => $isStandalone,
			],
			[
				'status'       => $hasMasterURL ? 'info' : 'neutral',
				'icon_class'   => 'bi bi-link-45deg',
				'title'        => __( 'Connected To Master', 'wp-simple-firewall' ),
				'status_label' => $hasMasterURL ? __( 'Active', 'wp-simple-firewall' ) : __( 'Not Configured', 'wp-simple-firewall' ),
				'oneliner'     => $hasMasterURL ? sprintf( __( 'Imports from %s.', 'wp-simple-firewall' ), $importMasterURL ) : __( 'No master site URL is saved.', 'wp-simple-firewall' ),
				'is_active'    => $hasMasterURL,
			],
			[
				'status'       => $authorisedURLCount > 0 ? 'good' : 'neutral',
				'icon_class'   => 'bi bi-broadcast',
				'title'        => __( 'Master For Other Sites', 'wp-simple-firewall' ),
				'status_label' => $authorisedURLCount > 0 ? __( 'Active', 'wp-simple-firewall' ) : __( 'Not Configured', 'wp-simple-firewall' ),
				'oneliner'     => sprintf(
					_n(
						'%s sync site may export settings from this site.',
						'%s sync sites may export settings from this site.',
						$authorisedURLCount,
						'wp-simple-firewall'
					),
					$authorisedURLCount
				),
				'is_active'    => $authorisedURLCount > 0,
			],
		];
	}

	/**
	 * @param SyncState $syncSitesState
	 * @return SyncSitesContract
	 */
	private function buildSyncSites( string $syncSitesState, int $authorisedURLCount ) :array {
		$enabledSummary = __( 'Use the table controls to authorise URLs, queue selected sites, and review sync status.', 'wp-simple-firewall' );
		$emptySummary = __( 'No authorised sites are configured yet. Use Add Authorised URLs in the table toolbar to add them.', 'wp-simple-firewall' );
		return [
			'sync_state'                  => $syncSitesState,
			'table_id'                    => 'ShieldTable-ImportExportSites',
			'authorised_url_count'        => $authorisedURLCount,
			'authorised_url_count_label'  => sprintf(
				_n(
					'%s authorised site',
					'%s authorised sites',
					$authorisedURLCount,
					'wp-simple-firewall'
				),
				$authorisedURLCount
			),
			'title'                       => __( 'Sync Sites', 'wp-simple-firewall' ),
			'summary'                     => __( 'Manage sites that may export settings from this master/source site after normal sync verification.', 'wp-simple-firewall' ),
			'enabled_title'               => __( 'Master Site Queue', 'wp-simple-firewall' ),
			'current_summary'             => $authorisedURLCount > 0 ? $enabledSummary : $emptySummary,
			'disabled_pane'               => $this->buildSyncSitesDisabledPane(),
			'is_unavailable'              => $syncSitesState === ImportExportController::SYNC_STATE_UNAVAILABLE,
			'is_disabled'                 => $syncSitesState === ImportExportController::SYNC_STATE_DISABLED,
			'is_enabled'                  => $syncSitesState === ImportExportController::SYNC_STATE_ENABLED,
		];
	}

	/**
	 * @return NetworkInviteReviewContract|array{}
	 */
	private function buildNetworkInviteReview() :array {
		$invite = ( new NetworkInviteRepository() )->find(
			(string)( $this->action_data[ NetworkInviteRepository::REVIEW_QUERY_KEY ] ?? '' )
		);
		if ( empty( $invite ) ) {
			return [];
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

	/**
	 * @return SyncSitesDisabledPane
	 */
	private function buildSyncSitesDisabledPane() :array {
		return [
			'message' => __( 'Import and export is not enabled. Click to enable it.', 'wp-simple-firewall' ),
			'actions' => [
				[
					'is_action'    => true,
					'type'         => 'navigate',
					'label'        => __( 'Enable Import/Export', 'wp-simple-firewall' ),
					'href'         => '',
					'icon_class'   => 'bi bi-power',
					'class_name'   => 'shield_dynamic_action_button',
					'tooltip_attr' => '',
					'target'       => '',
					'rel'          => '',
					'attributes'   => $this->buildDataAttributes( ActionData::Build( PluginImportExport_Enable::class, true, [
						'notification_type' => 'wp_admin_notice',
					] ) ),
				],
			],
		];
	}

	/**
	 * @param array<string, scalar|null> $data
	 * @return array<string, string>
	 */
	private function buildDataAttributes( array $data ) :array {
		$attributes = [];
		foreach ( $data as $key => $value ) {
			$attributes[ 'data-'.$key ] = (string)$value;
		}
		return $attributes;
	}
}
