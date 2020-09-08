<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\UI;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class BootstrapIcons {

	use PluginControllerConsumer;

	public function getUrl( string $icon ) :string {
		return $this->getCon()->getPluginUrl_Image(
			'icons/bootstrap/'.Services::Data()->addExtensionToFilePath( $icon, 'svg' ) );
	}
}