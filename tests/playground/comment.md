<!-- markdownlint-disable-file MD041 -->

#### WordPress Playground - runs in your browser

- :test_tube: [Open this build with sample data]($PLAYGROUND_URL): a store with WooCommerce set up, Customer stock notifications on, and sample Back In Stock Notifications data: nine subscribers, the extension's settings and one product with sign-ups switched off.
- It opens on WooCommerce > Status > Tools. Under "Migrate Back In Stock Notifications subscribers", select "Start migration" and confirm the prompt, then reload the page. The migrated subscribers are under WooCommerce > Notifications.
- With a checkout of this branch you can start the same preview locally: see [tests/playground]($README_URL).

:information_source: Back In Stock Notifications is a paid extension and is not installed in the preview. The sample data stands in for what it leaves in the database, so the preview cannot show the warning about duplicate emails while that extension is active, or the refusal to start while it still has emails queued. A site in a browser tab has no WP-Cron, so the preview runs the background batches at the end of each admin page load. No email leaves the preview. The link stops working 14 days after the last build.
