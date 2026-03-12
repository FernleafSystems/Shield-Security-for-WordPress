<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

/**
 * @phpstan-type MaintenanceItemCta array{
 *   href:string,
 *   label:string,
 *   target?:string
 * }
 * @phpstan-type QueueItem array{
 *   key:string,
 *   zone:string,
 *   label:string,
 *   count:int,
 *   severity:string,
 *   description:string,
 *   href:string,
 *   action:string,
 *   target:string
 * }
 * @phpstan-type MaintenanceQueueItem QueueItem&array{cta?:MaintenanceItemCta}
 */
class MaintenanceQueueItemDisplayNormalizer {

	/**
	 * @param list<QueueItem> $items
	 * @return list<MaintenanceQueueItem>
	 */
	public function normalizeAll( array $items ) :array {
		return \array_values( \array_map(
			fn( array $item ) :array => $this->normalize( $item ),
			$items
		) );
	}

	/**
	 * @param QueueItem $item
	 * @return MaintenanceQueueItem
	 */
	public function normalize( array $item ) :array {
		if ( ( $item[ 'zone' ] ?? '' ) !== 'maintenance' ) {
			return $item;
		}

		$href = (string)( $item[ 'href' ] ?? '' );
		$target = (string)( $item[ 'target' ] ?? '' );

		switch ( $item[ 'key' ] ?? '' ) {
			case 'wp_plugins_inactive':
				$item[ 'cta' ] = [
					'href'  => $href,
					'label' => __( 'Go to plugins', 'wp-simple-firewall' ),
				];
				break;
			case 'wp_themes_inactive':
				$item[ 'cta' ] = [
					'href'  => $href,
					'label' => __( 'Go to themes', 'wp-simple-firewall' ),
				];
				break;
			default:
				$action = (string)( $item[ 'action' ] ?? '' );
				if ( $href !== '' && $action !== '' ) {
					$item[ 'cta' ] = [
						'href'   => $href,
						'label'  => $action,
						'target' => $target,
					];
				}
				break;
		}

		return $item;
	}
}
