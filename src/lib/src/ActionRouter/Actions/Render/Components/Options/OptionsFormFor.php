<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options\BuildOptionsForDisplay;

class OptionsFormFor extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	public const SLUG = 'render_options_form_for';
	public const TEMPLATE = '/components/config/options_form_for.twig';

	protected function getRenderData() :array {
		$options = $this->action_data[ 'options' ];
		return [
			'strings' => [
				'inner_page_title'    => __( 'Configuration' ),
			],
			'flags'   => [
			],
			'vars'    => [
				'all_opts_keys' => $options,
				'all_options'   => ( new BuildOptionsForDisplay( $options ) )->standard(),
				'form_context'  => $this->action_data[ 'form_context' ] ?? 'normal',
			],
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'options',
		];
	}
}