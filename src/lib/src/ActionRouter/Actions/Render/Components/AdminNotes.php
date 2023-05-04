<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\Build;

class AdminNotes extends BaseRender {

	public const SLUG = 'render_adminnotes';
	public const TEMPLATE = '/snippets/prerendered.twig';

	protected function getRenderData() :array {
		$mod = $this->con()->getModule_Plugin();
		return [
			'content' => ( new Build\AdminNotes() )
				->setMod( $mod )
				->setDbHandler( $mod->getDbHandler_Notes() )
				->render()
		];
	}
}