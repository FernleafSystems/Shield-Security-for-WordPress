const { expect } = require( './shield-test' );

class ActionsQueuePage {
	constructor( page ) {
		this.page = page;
	}

	assetTile( panelTarget ) {
		return this.page.locator( `[data-mode-tile="1"][data-mode-panel-target="${panelTarget}"]` ).first();
	}

	assetPanel( panelTarget ) {
		return this.page.locator(
			`[data-mode-panel="1"][data-mode-panel-target-default="${panelTarget}"], [data-mode-panel="1"][data-mode-panel-target="${panelTarget}"]`
		).first();
	}

	/**
	 * Group cards are listed in place on the landing, one section per queue area, and open their
	 * results directly.
	 */
	async drillToDetail( fixture ) {
		const group = await this.waitForLandingGroup( fixture.bucket_key, fixture.group_key );
		await this.clickElement( group );
		await expect( this.page.locator( '[data-actions-queue-detail="1"]' ) ).toBeVisible();
	}

	resultsPanel() {
		return this.page.locator( '[data-actions-landing="1"] [data-drill-shell="1"][data-results-overlay="1"] [data-drill-layer-key="detail"]' );
	}

	/**
	 * Results opened from the landing sit in a panel over it, closed by the panel's own button; results
	 * reached any other way go back by the breadcrumb.
	 */
	async backToLanding() {
		const close = this.page.locator( '[data-actions-landing="1"] [data-results-overlay-close="1"]:visible' );
		if ( await close.count() > 0 ) {
			await close.click();
		}
		else {
			await this.page.locator( '[data-step-tab-drill-index="0"]' ).click();
		}
		await expect( this.page.locator( '[data-actions-landing="1"] [data-results-overlay="1"]' ) ).toHaveCount( 0 );
	}

	landingSection( bucketKey ) {
		return this.page.locator( `[data-actions-queue-sections="1"] [data-actions-queue-section-key="${bucketKey}"]` );
	}

	async findLandingGroup( bucketKey, groupKey ) {
		return this.findDrillTargetBySelection(
			this.landingSection( bucketKey ).locator( '[data-drill-target="detail"]' ),
			'data-drill-group-selection',
			groupKey
		);
	}

	async waitForLandingGroup( bucketKey, groupKey, timeout = 20000 ) {
		let group = null;

		await expect.poll( async () => {
			group = await this.findLandingGroup( bucketKey, groupKey );
			return group === null ? '' : groupKey;
		}, { timeout } ).toBe( groupKey );

		return group;
	}

	/**
	 * A landing group row; the passing section and its boxes start collapsed, so they're opened first.
	 */
	async waitForLandingGroupOuter( groupKey, timeout = 20000 ) {
		const group = this.page.locator( `[data-actions-queue-sections="1"] [data-actions-queue-group-key="${groupKey}"]` ).first();
		await expect.poll( async () => await group.count(), { timeout } ).toBe( 1 );
		await group.evaluate( ( element ) => {
			const head = element.closest( '[data-actions-queue-section-key]' )
				?.querySelector( '[data-actions-queue-section-toggle="1"]' );
			if ( head instanceof HTMLElement && head.getAttribute( 'aria-expanded' ) === 'false' ) {
				head.click();
			}
			const foldedBox = element.closest( '[hidden][id]' );
			if ( foldedBox !== null ) {
				document.querySelector( `[data-actions-queue-box-toggle][aria-controls="${foldedBox.id}"]` )?.click();
			}
		} );
		await expect( group ).toBeVisible();
		return group;
	}

	async openAssetPanel( panelTarget ) {
		const tile = this.assetTile( panelTarget );
		await this.clickElement( tile );

		const panel = this.assetPanel( panelTarget );
		await expect( panel ).toBeVisible();
		await expect( panel ).toHaveAttribute( 'aria-hidden', 'false' );

		return panel;
	}

	async clickElement( locator ) {
		await expect( locator ).toBeVisible();
		await locator.scrollIntoViewIfNeeded();
		await locator.evaluate( ( element ) => {
			element.click();
		} );
	}

	async findDrillTargetBySelection( collection, selectionAttr, key ) {
		const count = await collection.count();

		for ( let index = 0; index < count; index++ ) {
			const candidate = collection.nth( index );
			const selection = await this.parseSelection( candidate, selectionAttr );
			if ( selection && selection.key === key ) {
				return candidate;
			}
		}

		return null;
	}

	async parseSelection( candidate, selectionAttr ) {
		const selectionJson = await candidate.getAttribute( selectionAttr );
		if ( !selectionJson ) {
			return null;
		}

		try {
			return JSON.parse( selectionJson );
		}
		catch ( error ) {
			return null;
		}
	}
}

module.exports = {
	ActionsQueuePage,
};
