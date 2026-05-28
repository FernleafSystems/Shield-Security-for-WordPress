<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SiteHealth;

/**
 * @phpstan-import-type TabGroup from SiteHealthSecurityStatusBuilder
 * @phpstan-import-type ZoneStatus from SiteHealthSecurityStatusBuilder
 */
class SiteHealthSecurityTabRenderer {

	private SiteHealthSecurityStatusBuilder $builder;

	public function __construct( ?SiteHealthSecurityStatusBuilder $builder = null ) {
		$this->builder = $builder ?? new SiteHealthSecurityStatusBuilder();
	}

	public function render() :string {
		return sprintf(
			'<div class="health-check-body health-check-tab-%s">%s</div>',
			esc_attr( SiteHealthSecurityStatusBuilder::TAB_SLUG ),
			\implode( '', \array_map(
				fn( array $group ) :string => $this->renderGroup( $group ),
				$this->builder->buildTabGroups()
			) )
		);
	}

	/**
	 * @param TabGroup $group
	 */
	private function renderGroup( array $group ) :string {
		if ( \count( $group[ 'items' ] ) === 0 ) {
			return '';
		}

		return sprintf(
			'<div class="site-health-issues-wrapper site-health-shield-%s"><h3>%s</h3><p>%s</p><div class="health-check-accordion issues">%s</div></div>',
			esc_attr( $group[ 'status' ] ),
			esc_html( $group[ 'title' ] ),
			esc_html( $group[ 'description' ] ),
			\implode( '', \array_map(
				fn( array $item ) :string => $this->renderZoneStatus( $item ),
				$group[ 'items' ]
			) )
		);
	}

	/**
	 * @param ZoneStatus $item
	 */
	private function renderZoneStatus( array $item ) :string {
		return sprintf(
			'<h4 class="health-check-accordion-heading"><button aria-expanded="false" class="health-check-accordion-trigger" aria-controls="%s" type="button"><span class="title">%s</span><span class="badge blue">%s</span><span class="icon"></span></button></h4><div class="health-check-accordion-panel" id="%s" hidden="hidden">%s%s</div>',
			esc_attr( $item[ 'panel_id' ] ),
			esc_html( $item[ 'title' ] ),
			esc_html( $item[ 'status_label' ] ),
			esc_attr( $item[ 'panel_id' ] ),
			$item[ 'description' ],
			$item[ 'actions' ]
		);
	}
}
