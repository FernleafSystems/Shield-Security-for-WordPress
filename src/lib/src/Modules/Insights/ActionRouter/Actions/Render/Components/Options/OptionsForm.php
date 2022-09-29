<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Options;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Options\BuildTransferableOptions;

class OptionsForm extends BaseRender {

	const SLUG = 'render_options_form';
	const TEMPLATE = '/components/options_form/main.twig';

	public function __get( string $key ) {
		$value = parent::__get( $key );

		switch ( $key ) {

			case 'primary_mod_slug':
				$value = $this->action_data[ 'primary_mod_slug' ];
				break;

			default:
				break;
		}

		return $value;
	}

	protected function getRenderData() :array {
		$mod = $this->primary_mod;
		return [
			'hrefs' => [
				'form_action' => 'admin.php?page='.$mod->getModSlug(),
			],
			'vars'  => [
				'working_mod'   => $mod->getSlug(),
				'all_options'   => $this->action_data[ 'all_options' ],
				'xferable_opts' => ( new BuildTransferableOptions() )
					->setMod( $mod )
					->build(),
				'focus_option'  => $this->action_data[ 'focus_option' ],
				'focus_section' => $this->action_data[ 'focus_section' ],
				'form_context'  => $this->action_data[ 'form_context' ],
			],
			'flags' => [
			],
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'all_options',
			'primary_mod_slug',
			'focus_option',
			'focus_section',
		];
	}
}