import { expect, test } from '@playwright/test';

/*
 * M8.20: a renter books a car online, from the booking page to Awaiting payment, with
 * their documents. Runs against a seeded app (`composer dev`): it books the Mazda Demio
 * from the seed pack on random dates in the coming year, so reruns don't clash.
 */

const PDF = Buffer.from(
    '%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [] /Count 0 >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n',
);

// A datetime-local value `days` from now at 10:00.
function local(days: number): string {
    const date = new Date(Date.now() + days * 86_400_000);
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T10:00`;
}

test('a renter books a car with their documents', async ({ page }) => {
    const start = 30 + Math.floor(Math.random() * 300);

    await page.goto('/cars/mazda-demio/book');
    await expect(page.getByRole('heading', { level: 1 })).toContainText(
        'Book the',
    );

    // Step 1: trip.
    await page.getByLabel('Pickup', { exact: true }).fill(local(start));
    await page.getByLabel('Return', { exact: true }).fill(local(start + 3));
    await expect(page.getByText('Pay now to reserve')).toBeVisible();
    await page
        .getByRole('button', { name: 'Continue to driver details' })
        .click();

    await expect(page).toHaveURL(/\/bookings\/[A-Za-z0-9]{64}$/);
    const link = page.url();

    // Step 2: driver and documents.
    await page.getByLabel('First name').fill('Amina');
    await page.getByLabel('Surname').fill('Otieno');
    await page.getByLabel('Date of birth').fill('1991-03-15');
    await page.getByLabel('Email').fill('amina@example.test');
    await page.getByLabel('Phone or WhatsApp').fill('+254 700 000 002');
    await page.getByLabel('ID number').fill('12345678');
    await page.getByLabel('KRA PIN').fill('A123456789Z');
    await page.getByLabel('Licence number').fill('DL-1234567');
    await page.getByLabel('Expiry date').fill('2030-01-01');
    await page.getByLabel('Years held').fill('8');

    const files = page.locator('input[type="file"]');
    const count = await files.count();
    expect(count).toBe(4);

    // Each uploader hides its input once it holds its one file.
    for (let index = 0; index < count; index++) {
        await expect(files).toHaveCount(count - index);
        await files.first().setInputFiles({
            name: `document-${index}.pdf`,
            mimeType: 'application/pdf',
            buffer: PDF,
        });
    }

    // A finished upload can be removed.
    await expect(
        page.getByRole('button', { name: /^Remove document-/ }),
    ).toHaveCount(4);
    await page.getByRole('button', { name: 'Save and review' }).click();

    // Step 3: review. A refresh keeps the place.
    await expect(
        page.getByRole('button', { name: 'Submit booking' }),
    ).toBeVisible();
    await page.goto(link);
    await expect(
        page.getByRole('button', { name: 'Submit booking' }),
    ).toBeVisible();

    await page.getByLabel(/I have read and accept/).check();
    await page.getByRole('button', { name: 'Submit booking' }).click();

    await expect(page.getByText('Awaiting payment').first()).toBeVisible();
    await expect(page.getByText(/Booking BE-\d{4}-\d{6}/)).toBeVisible();

    // Steps 4 and 5: the instructions show once a method is chosen (P-1).
    await expect(page.getByText('Paying by M-Pesa')).toHaveCount(0);
    await page.getByRole('radio', { name: 'M-Pesa' }).check();
    await expect(page.getByText('Paying by M-Pesa')).toBeVisible();
    await page
        .getByLabel('Confirmation message')
        .fill('TJK1AB2CD3 Confirmed. Ksh16,588.00 sent to BAINES EXECUTIVES.');
    await page.locator('input[type="file"]').setInputFiles({
        name: 'mpesa.pdf',
        mimeType: 'application/pdf',
        buffer: PDF,
    });
    await expect(
        page.getByRole('button', { name: /^Remove mpesa/ }),
    ).toBeVisible();
    await page.getByRole('button', { name: 'Send payment details' }).click();

    await expect(
        page.getByRole('heading', { name: 'We are checking your payment' }),
    ).toBeVisible({ timeout: 15_000 });
    await expect(page.getByText('Payment submitted').first()).toBeVisible();
});
