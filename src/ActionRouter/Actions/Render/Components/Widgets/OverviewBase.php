<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

abstract class OverviewBase extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	protected function truncate( string $item, int $length = 100 ) :string {
		return \strlen( $item ) > $length ? \substr( $item, 0, $length ).' (...truncated)' : $item;
	}
}