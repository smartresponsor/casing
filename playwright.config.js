const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/Browser',
  use: {
    baseURL: process.env.CASING_BASE_URL || 'http://127.0.0.1:8000',
    trace: 'retain-on-failure'
  }
