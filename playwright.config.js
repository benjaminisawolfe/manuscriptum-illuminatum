const { defineConfig, devices } = require('@playwright/test');

const baseURL = process.env.MANUSCRIPTUM_TEST_BASE_URL;
const storageState = process.env.MANUSCRIPTUM_TEST_STORAGE_STATE || 'playwright/.auth/storage-state.json';

if (!baseURL) {
    throw new Error('Set MANUSCRIPTUM_TEST_BASE_URL to the WordPress installation used for browser tests.');
}

module.exports = defineConfig({
    testDir: './tests',

    timeout: 30_000,

    expect: {
        timeout: 5_000,
    },

    fullyParallel: false,
    // Mutation tests share one WordPress database and must not race each other.
    workers: 1,

    use: {
        baseURL,
        storageState,

        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },

    projects: [
        {
            name: 'desktop',
            use: {
                ...devices['Desktop Chrome'],
            },
        },

        {
            name: 'mobile',
            use: {
                ...devices['Pixel 5'],
            },
        },
    ],

    reporter: [
        ['list'],
        ['html', { open: 'never' }],
    ],
});
