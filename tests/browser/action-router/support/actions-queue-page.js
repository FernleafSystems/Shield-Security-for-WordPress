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

	async drillToDetail( fixture ) {
		const bucket = await this.waitForBucket( fixture.bucket_key );
		await this.clickElement( bucket );
		await expect( this.page.locator( '[data-actions-queue-groups="1"]' ) ).toBeVisible();

		const group = await this.waitForGroupWithRetry( bucket, fixture.group_key );

		if ( group === null ) {
			throw new Error( `Unable to locate Actions Queue group "${fixture.group_key}" in the groups layer.` );
		}

		await this.clickElement( group );
		await expect( this.page.locator( '[data-actions-queue-detail="1"]' ) ).toBeVisible();
	}

	async openAssetPanel( panelTarget ) {
		const tile = this.assetTile( panelTarget );
		await this.clickElement( tile );

		const panel = this.assetPanel( panelTarget );
		await expect( panel ).toBeVisible();
		await expect( panel ).toHaveAttribute( 'aria-hidden', 'false' );

		return panel;
	}

	async findGroup( groupKey ) {
		return this.findDrillTargetBySelection(
			this.page.locator( '[data-actions-queue-groups="1"] [data-drill-target="detail"]' ),
			'data-drill-group-selection',
			groupKey
		);
	}

	async findBucket( bucketKey ) {
		return this.findDrillTargetBySelection(
			this.page.locator( '[data-actions-landing="1"] [data-drill-target="groups"]' ),
			'data-drill-bucket-selection',
			bucketKey
		);
	}

	async waitForGroup( groupKey, timeout = 20000 ) {
		let group = null;

		await expect.poll( async () => {
			group = await this.findGroup( groupKey );
			return group === null ? '' : groupKey;
		}, { timeout } ).toBe( groupKey );

		return group;
	}

	async waitForBucket( bucketKey, timeout = 20000 ) {
		let bucket = null;

		await expect.poll( async () => {
			bucket = await this.findBucket( bucketKey );
			return bucket === null ? '' : bucketKey;
		}, { timeout } ).toBe( bucketKey );

		return bucket;
	}

	async waitForGroupWithRetry( bucket, groupKey, timeout = 20000 ) {
		for ( let attempt = 0; attempt < 2; attempt++ ) {
			try {
				return await this.waitForGroup( groupKey, timeout );
			}
			catch ( error ) {
				if ( attempt > 0 ) {
					break;
				}
				await this.clickElement( bucket );
				await expect( this.page.locator( '[data-actions-queue-groups="1"]' ) ).toBeVisible();
			}
		}

		return await this.findGroup( groupKey );
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
