<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;

/**
 * Contract checks for sidebar navigation trigger markup.
 */
class NavSidebarTemplateTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function testExpandableParentItemsUseExplicitCollapseTriggerMarkup() :void {
		$content = $this->getPluginFileContents(
			'templates/twig/wpadmin/components/page/nav_sidebar.twig',
			'sidebar navigation template'
		);

		$this->assertStringContainsString(
			"{% set is_expandable = mitem.sub_items|default([]) is not empty %}",
			$content
		);
		$this->assertStringContainsString(
			"href=\"{{ is_expandable ? '#subnav-' ~ mitem.slug : mitem.href|default('javascript:{}') }}\"",
			$content
		);
		$this->assertStringContainsString( 'data-bs-toggle="collapse"', $content );
		$this->assertStringContainsString( 'data-bs-target="#subnav-{{ mitem.slug }}"', $content );
		$this->assertStringContainsString( 'aria-controls="subnav-{{ mitem.slug }}"', $content );
		$this->assertStringContainsString( 'aria-expanded="{{ mitem.active|default(false) ? \'true\' : \'false\' }}"', $content );
	}

	public function testTopLevelTargetUsesMitemTargetNotSubTarget() :void {
		$content = $this->getPluginFileContents(
			'templates/twig/wpadmin/components/page/nav_sidebar.twig',
			'sidebar navigation template'
		);

		$this->assertStringContainsString( '{% if mitem.target|default(\'\') is not empty %}target="{{ mitem.target }}"{% endif %}', $content );
		$this->assertStringNotContainsString( 'sub.target', $content );
	}
}
