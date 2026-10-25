import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

// WCAG 2.1 AA on the public pages (M12.8). The car pages need the seed pack's cars.
const pages = [
    '/',
    '/fleet',
    '/services',
    '/about',
    '/rental-policy',
    '/contact',
    '/privacy',
    '/my-bookings',
];

for (const path of pages) {
    test(`${path} has no WCAG 2.1 AA violations`, async ({ page }) => {
        await page.goto(path, { waitUntil: 'load' });

        const results = await new AxeBuilder({ page })
            .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
            // Third-party frames (Turnstile, the map) are outside our control.
            .exclude('iframe')
            .analyze();

        expect(
            results.violations.map(
                (violation) =>
                    `${violation.id}: ${violation.nodes.map((node) => node.target.join(' ')).join(', ')}`,
            ),
        ).toEqual([]);
    });
}

test('a car page and its booking form have no WCAG 2.1 AA violations', async ({
    page,
}) => {
    await page.goto('/fleet', { waitUntil: 'load' });
    const car = page.locator('a[href*="/cars/"]').first();
    const href = await car.getAttribute('href');
    test.skip(!href, 'No published cars: run the seed pack first.');

    for (const path of [href!, `${href!}/book`]) {
        await page.goto(path, { waitUntil: 'load' });

        const results = await new AxeBuilder({ page })
            .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
            .exclude('iframe')
            .analyze();

        expect(
            results.violations.map((violation) => `${path} ${violation.id}`),
        ).toEqual([]);
    }
});
