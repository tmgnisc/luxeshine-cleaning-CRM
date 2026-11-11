import { defineConfig, devices } from "@playwright/test";

/**
 * Smoke tests run against a live frontend (and its backend) on http://localhost:3000.
 * Start both servers first, then: `npm run test:e2e`.
 * One-time setup: `npm i -D @playwright/test && npx playwright install chromium`.
 */
export default defineConfig({
  testDir: "./tests/e2e",
  timeout: 30_000,
  fullyParallel: true,
  reporter: "list",
  use: {
    baseURL: process.env.E2E_BASE_URL ?? "http://localhost:3000",
    trace: "on-first-retry",
  },
  projects: [{ name: "chromium", use: { ...devices["Desktop Chrome"] } }],
});
