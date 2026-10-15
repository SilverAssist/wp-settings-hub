import { defineConfig, devices } from '@playwright/test';

const PORT = 9400;

export default defineConfig({
	testDir: './tests/e2e',
	testMatch: '**/*.spec.ts',
	fullyParallel: true,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 1 : 0,
	reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : 'list',
	use: {
		baseURL: `http://127.0.0.1:${PORT}`,
		trace: 'retain-on-failure',
		storageState: 'tests/e2e/.auth/admin.json',
	},
	globalSetup: './tests/e2e/global-setup.ts',
	webServer: {
		command: 'npm run test:e2e:server',
		url: `http://127.0.0.1:${PORT}/wp-login.php`,
		reuseExistingServer: !process.env.CI,
		timeout: 180_000,
	},
	projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
