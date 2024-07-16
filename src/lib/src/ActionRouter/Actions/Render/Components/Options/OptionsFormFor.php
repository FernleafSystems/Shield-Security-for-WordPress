<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\BuildOptionsForDisplay;

class OptionsFormFor extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	public const SLUG = 'render_options_form_for';
	public const TEMPLATE = '/components/config/options_form_for.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$options = $this->action_data[ 'options' ];
		return [
			'strings' => [
				'inner_page_title'    => __( 'Configuration' ),
				'import_export'       => __( 'Import/Export' ),
				'is_opt_importexport' => __( 'Check to include this option in import/export' ),
			],
			'flags'   => [
				'show_transfer_switch' => $con->isPremiumActive(),
			],
			'imgs'    => [
				'svgs' => [
					'importexport' => $con->svgs->raw( 'arrow-down-up' )
				],
			],
			'vars'    => [
				'all_opts_keys'      => $options,
				'all_options'        => ( new BuildOptionsForDisplay( $options ) )->standard(),
				'form_context'       => $this->action_data[ 'form_context' ] ?? 'normal',
				'xferable_opts'      => \array_keys( $con->cfg->configuration->transferableOptions() ),
				'xfer_excluded_opts' => $con->comps->opts_lookup->getXferExcluded(),
			],
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'options',
		];
	}
}