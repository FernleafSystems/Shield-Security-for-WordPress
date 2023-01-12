<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\MainWP;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

abstract class BaseMainwpPage extends Actions\Render\FullPage\BaseFullPageRender {

	public const PRIMARY_MOD = 'integrations';
	public const TEMPLATE = '/pages/mainwp/mainwp_default.twig';

	abstract protected function renderMainBodyContent() :string;
}