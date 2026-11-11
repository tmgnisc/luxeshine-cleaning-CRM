import { expect, test } from "@playwright/test";

test.describe("Booking wizard", () => {
  test("a visitor can step through the wizard and is prompted to sign in", async ({ page }) => {
    await page.goto("/book");

    await expect(page.getByRole("heading", { name: "Book a cleaning" })).toBeVisible();

    // Step 1 — choose the first service.
    await page.getByRole("button", { name: /\/h$/ }).first().click();
    await page.getByRole("button", { name: "Continue" }).click();

    // Step 2 — duration + live price preview.
    await expect(page.getByText("Estimated price")).toBeVisible();
    await page.getByRole("button", { name: "Continue" }).click();

    // Step 3 — pick a date; availability slots load.
    const inTwoWeeks = new Date(Date.now() + 14 * 864e5).toISOString().slice(0, 10);
    await page.locator('input[type="date"]').fill(inTwoWeeks);
    await page.getByRole("button", { name: /:00 (AM|PM)/ }).first().click();
    await page.getByRole("button", { name: "Continue" }).click();

    // Step 4 — guests are asked to sign in.
    await expect(page.getByRole("link", { name: "Sign in" })).toBeVisible();
  });
});
