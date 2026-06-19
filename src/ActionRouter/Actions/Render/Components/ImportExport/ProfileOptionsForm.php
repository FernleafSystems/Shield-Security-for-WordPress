<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsFormFor;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportProfiles\Ops\Record as ProfileRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Profiles\{
	ProfileOptionsCatalog,
	ProfileRepository
};

class ProfileOptionsForm extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	public const SLUG = 'render_importexport_profile_options_form';
	public const TEMPLATE = OptionsFormFor::TEMPLATE;

	protected function getRenderData() :array {
		return [];
	}

	protected function buildRenderOutput( array $renderData = [] ) :string {
		$repo = new ProfileRepository();
		$profile = $repo->ensureDefaultProfile();
		if ( !( $profile instanceof ProfileRecord ) ) {
			return '';
		}

		$config = $repo->configForProfile( $profile );
		$catalog = new ProfileOptionsCatalog();
		$keys = $catalog->profileableKeys();

		return self::con()->action_router->render( OptionsFormFor::class, [
			'options'              => $keys,
			'values'               => $config[ 'options' ],
			'form_context'         => 'import_export_profile',
			'show_transfer_switch' => true,
			'options_save_action'  => 'profile_form_save',
			'transfer_action'      => 'profile_xfer_include_toggle',
			'xferable_opts'        => $keys,
			'xfer_excluded_opts'   => $config[ 'excluded' ],
		] );
	}
}
