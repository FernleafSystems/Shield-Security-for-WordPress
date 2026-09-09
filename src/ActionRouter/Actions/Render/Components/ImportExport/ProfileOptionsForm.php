<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\BuildOptionsForDisplay;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportProfiles\Ops\Record as ProfileRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Profiles\{
	ProfileOptionsCatalog,
	ProfileRepository
};

class ProfileOptionsForm extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	public const SLUG = 'render_importexport_profile_options_form';
	public const TEMPLATE = '/components/importexport/profile_options_form.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$repo = new ProfileRepository();
		$profile = $repo->ensureDefaultProfile();
		if ( !( $profile instanceof ProfileRecord ) ) {
			return [
				'flags' => [
					'profile_available' => false,
				],
				'vars'  => [
					'all_opts_keys' => [],
					'groups'        => [],
				],
			];
		}

		$config = $repo->configForProfile( $profile );
		$catalog = new ProfileOptionsCatalog();
		$keys = $catalog->profileableKeys();
		$options = ( new BuildOptionsForDisplay( $keys, [] ) )
			->setValues( $config[ 'options' ] )
			->standard();

		return [
			'strings' => [
				'profile_search_label'        => __( 'Search sync profile settings', 'wp-simple-firewall' ),
				'profile_search_placeholder'  => __( 'Search settings in this sync profile', 'wp-simple-firewall' ),
				'profile_empty_search'        => __( 'No sync profile settings match that search.', 'wp-simple-firewall' ),
				'profile_included_label'      => __( 'included', 'wp-simple-firewall' ),
				'sync_include_option_label'   => __( 'Click to include in sync', 'wp-simple-firewall' ),
				'sync_exclude_option_label'   => __( 'Click to exclude from sync', 'wp-simple-firewall' ),
				'sync_include_group_label'    => __( 'Click to include this group in sync', 'wp-simple-firewall' ),
				'sync_exclude_group_label'    => __( 'Click to exclude this group from sync', 'wp-simple-firewall' ),
				'sync_profile_settings_title' => __( 'Sync Profile Settings', 'wp-simple-firewall' ),
				'sync_profile_settings_meta'  => __( 'Choose which settings are stored in this profile and included during network sync.', 'wp-simple-firewall' ),
			],
			'flags'   => [
				'profile_available' => true,
			],
			'imgs'    => [
				'svgs' => [
					'sync' => $con->svgs->iconClass( 'arrow-repeat' ),
				],
			],
			'vars'    => [
				'all_opts_keys'          => $keys,
				'form_context'           => 'import_export_profile',
				'options_save_action'    => 'profile_form_save',
				'transfer_action'        => 'profile_xfer_include_toggle',
				'transfer_group_action'  => 'profile_xfer_group_include_toggle',
				'groups'                 => ( new ProfileOptionsFormViewBuilder() )->build( $options, $keys, $config[ 'excluded' ] ),
			],
		];
	}
}
