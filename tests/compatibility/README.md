# Security timeline verification

The timeline is tested independently of session management, MFA, notifications, and
incident response. Run PHP tests from a disposable Shopware installation with this
plugin installed:

```sh
vendor/bin/phpunit -c custom/plugins/FroshTools/phpunit.xml
```

Run the Administration suite with the matching Shopware source and dependencies:

```sh
cd custom/plugins/FroshTools/src/Resources/app/administration
npm ci
SHOPWARE_ADMINISTRATION_PATH=/path/to/shopware/src/Administration/Resources/app/administration npm run test:unit
```

`browser-timeline.mjs` exercises the native Administration build, searchable actor/action
choices, UTC dates, equivalent IPv6 addresses, pagination, redacted details, a real
filtered JSON download, empty results, and clearing identity scope.

Use a disposable shop and administrator account. Install `puppeteer-core` where the
browser script can resolve it, and supply:

- `FROSH_BROWSER_BASE_URL`: the running shop URL.
- `FROSH_BROWSER_USERNAME` and `FROSH_BROWSER_PASSWORD`: disposable administrator credentials.
- `FROSH_BROWSER_CHROME`: the Chrome executable.
- `FROSH_BROWSER_ARTIFACTS`: output directory for screenshots and downloads.
- `FROSH_BROWSER_TIMELINE_FIXTURE`: executable wrapper that runs
  `php custom/plugins/FroshTools/tests/compatibility/timeline-fixture.php "$@"`
  in that same shop environment.

The browser test creates a uniquely named temporary user; the fixture only seeds and
cleans activity for that matching user. It verifies that sensitive test values never
reach the timeline. Do not run this fixture against a production shop.

Verified on Shopware 6.6.10.28 (native Webpack) and 6.7.15.0 (native Vite), with PHP 8.3:
299 PHP tests per version, 156 Administration tests per version, and both browser flows.
The PHP runs report APCu CLI warnings and on 6.6 skip two pre-existing checks for
scheduled tasks introduced in 6.7.
