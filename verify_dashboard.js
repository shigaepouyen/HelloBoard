const { chromium } = require('playwright');
const path = require('path');

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();

  // Start PHP server
  const { exec } = require('child_process');
  const server = exec('php -S localhost:8001');

  await new Promise(r => setTimeout(r, 2000));

  await page.goto('http://localhost:8001/verify_dashboard_mock.php');

  // Wait for the JS to execute and update the UI
  await page.waitForTimeout(1000);

  await page.screenshot({ path: 'verification/dashboard_stock.png', fullPage: true });

  console.log('Screenshot saved to verification/dashboard_stock.png');

  await browser.close();
  server.kill();
})();
