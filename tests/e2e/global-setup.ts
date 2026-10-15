import { chromium, type FullConfig } from '@playwright/test';
import { mkdirSync } from 'node:fs';

/**
 * Log in once and reuse the session. WordPress Playground ships an `admin` / `password` user.
 */
export default async function globalSetup( config: FullConfig ): Promise<void> {
	const baseURL = config.projects[ 0 ].use.baseURL as string;
	mkdirSync( 'tests/e2e/.auth', { recursive: true } );

	const browser = await chromium.launch();
	const context = await browser.newContext( { baseURL } );
	const page = await context.newPage();

	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ );

	await context.storageState( { path: 'tests/e2e/.auth/admin.json' } );
	await browser.close();
}
