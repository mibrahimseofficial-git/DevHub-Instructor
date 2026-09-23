# Traffic Light Availability System — deployment & testing

Implements Feature 2: instructors set Green/Amber/Red from their
dashboard, it shows on their listing card and profile, Red disables the
contact form and booking widget, and 30 days untouched auto-decays any
status to Amber.

## Deploy — four files

1. `mu-plugins/instructorplace-availability.php` →
   `wp-content/mu-plugins/instructorplace-availability.php`
   (new file, alongside the trial-listings mu-plugin — doesn't replace it)

2. `child-theme/templates/dashboard/listings.php` →
   `wp-content/themes/listingpro-child/templates/dashboard/listings.php`
   **This REPLACES the version already there for the trial countdown fix —
   it's the same file with both sets of changes together, not a separate
   file. Don't deploy the old trial-only version over this one, or you'll
   silently undo the availability buttons.**

3. `child-theme/listing-loop.php` →
   `wp-content/themes/listingpro-child/listing-loop.php`
   (new file — this is the search-results/listing-card template)

4. `child-theme/templates/single-list/listing-details-style6/content/title-bar.php` →
   `wp-content/themes/listingpro-child/templates/single-list/listing-details-style6/content/title-bar.php`
   (new file — create the nested folders if they don't exist)

## How it works

- **Default status:** a listing that's never had this touched shows Green,
  not a blank/missing badge. The 30-day staleness cron leaves it alone
  until the instructor sets something for the first time — there's no
  honest "last updated" date to measure staleness against before that.
- **Staleness only decays Green:** 30 days with no update forces a stale
  Green to Amber. It never touches Amber (nothing further to decay) or a
  deliberately-set Red — a stale Red is a real choice the instructor made,
  and staleness shouldn't quietly override it.
- **Dashboard control:** styled as list items matching the dropdown's own
  existing Edit/Remove/Change Plan rows (colored circle icon + label, the
  active one bold with a checkmark) rather than a row of buttons — the
  dropdown's width is sized for short text links, and three side-by-side
  buttons clipped against that width in the first version of this.
- **Hard block on Red:** disables the contact form's Send button and the
  booking widget's controls via JS, with an inline explanation. Targets
  both booking mechanisms this codebase has (`.classic-booking-appointment`
  for the ListingPro Bookings plugin, `.make-reservation`/
  `.ifram-reservation` for the third-party Resurva iframe button) since I
  can't tell from source which one is actually active on this site —
  whichever is present in the DOM gets handled, the other is simply never
  matched and does nothing.
- **Dashboard controls:** three small buttons added to the existing
  per-listing dropdown menu in "My Listings" — no new column, no grid
  layout changes, so nothing else in that table shifts around.

## Verify

1. **Set it:** as your test instructor, go to dashboard → My Listings →
   click each of the three availability buttons in turn on a test
   listing. Confirm the page reloads and the button that matches the
   current status is visually highlighted.
2. **Card badge:** view `/find-instructor/` (or wherever search results
   show) and confirm the colored badge appears next to that listing's
   title, matching whatever you just set.
3. **Profile badge:** view the listing's own page — same badge should
   appear next to the title, beside the "Claimed" badge if present.
4. **Red blocks contact:** set the listing to Red, reload its profile
   page (fresh, not cached — same caution as everything else on this
   site), confirm the contact form's Send button is disabled and the
   warning message shows. If a booking widget is present, confirm it's
   disabled too.
5. **Green/Amber allow contact:** switch back to Green or Amber, reload,
   confirm the form and booking widget work normally again — nothing
   should stay disabled once it's off Red.
6. **30-day staleness:** set a test listing to Green first. In
   phpMyAdmin, set that listing's `_ip_availability_updated` meta value to
   31 days ago:
   ```sql
   UPDATE RyK_postmeta SET meta_value = UNIX_TIMESTAMP() - (31*86400)
   WHERE post_id = <listing_id> AND meta_key = '_ip_availability_updated';
   ```
   Trigger the cron manually (WP Crontrol → `ip_availability_daily_check`
   → Run Now — same tool used for the trial system's testing). Confirm
   the status flips to Amber. Then repeat the same backdating on a
   different test listing that's set to **Red** instead, run the cron
   again, and confirm that one does **not** change — Red stays Red
   regardless of staleness.
