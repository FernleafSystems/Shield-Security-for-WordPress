<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Merlin;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;

/**
 * Draws one wizard step from its own template. Everything the template shows comes from the step
 * (Merlin\Steps\Base::render()), so each step's text lives with the step.
 */
class MerlinStep extends BaseRender {

	public const SLUG = 'render_merlin_step';
	public const TEMPLATE = '/components/merlin/steps/%s.twig';

	/**
	 * @throws ActionException
	 */
	protected function checkAvailableData() {
		parent::checkAvailableData();

		$slug = $this->action_data[ 'vars' ][ 'step_slug' ] ?? null;
		if ( !\preg_match( '#^[a-z0-9_]+$#', (string)$slug ) ) {
			throw new ActionException( __( 'Invalid Step Slug', 'wp-simple-firewall' ) );
		}
	}

	protected function getRenderTemplate() :string {
		return sprintf( parent::getRenderTemplate(), $this->action_data[ 'vars' ][ 'step_slug' ] );
	}
}
