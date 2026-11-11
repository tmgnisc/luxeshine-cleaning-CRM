import { expect, test } from "@playwright/test";

test.describe("Admin bookings", () => {
  test("an admin can sign in and view the bookings table", async ({ page }) => {
    await page.goto("/login");
    await page.getByLabel("Email").fill("admin@dusttodone.test");
    await page.getByLabel("Password").fill("password");
    await page.getByRole("button", { name: "Sign in" }).click();

    // Redirected into the admin panel.
    await page.waitForURL("**/admin");

    await page.goto("/admin/bookings");
    await expect(page.getByRole("heading", { name: "Bookings" })).toBeVisible();

    // Status filter chips and the table render.
    await expect(page.getByRole("button", { name: /^All/ })).toBeVisible();
    await expect(page.getByRole("button", { name: "New booking" })).toBeVisible();
  });
});
