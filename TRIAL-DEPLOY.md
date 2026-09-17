# Trial Listings System — deployment & verification

Implements Feature 1 from the client requirements: new instructors get a
free 30-day Premium trial (only if they don't pay at signup), a countdown
badge, start/reminder/ended emails, and an automatic downgrade to the Free
plan at expiry.

File: `mu-plugins/instructorplace-trial-listings.php`

## Deploy

1. Copy the file to `wp-content/mu-plugins/` on the server (same place as
   `instructorplace-listingpro-fixes.php` from the earlier speed fix).
2. Nothing else to install — no settings page, no options to configure.
   Plan IDs are resolved automatically (see "How plans are identified" below).

## One manual step: add the badge to the listing template

The countdown badge is a function, not automatically injected into any
template — I couldn't confirm from the theme source alone which single-listing
template variant (`listing_detail5.php`, `listing-detail6.php`, etc.) is
actually the live one for this site, and editing the wrong one would be a
silent no-op. Whichever file live turns out to be, add this one line
where the "✔ Claimed" badge is printed (same visual row is the natural spot):

```php
<?php echo ip_trial_badge_html( get_the_ID() ); ?>
```

If you'd rather not touch a template file at all, the shortcode
`[ip_trial_badge]` does the same thing and can be dropped into an Elementor
Shortcode widget on the listing header instead.

## How plans are identified (no hardcoded IDs)

- **Premium** = the `price_plan` post with `menu_order = 1`. This is the
  same rule the site's own search-results sorting already uses to mean
  "top plan" (`include/find-instructor-ajax.php`).
- **Free** = the `price_plan` post whose `plan_price` meta is empty or
  zero — the same rule ListingPro's own submit handler uses to decide a
  plan is free (`inc/submit-ajax.php`).

If plans ever get reordered or renamed, this keeps working. If the actual
Premium/Free plan doesn't fit those rules on your setup, the trial simply
won't fire (`_ip_trial_decided` meta will show `no_premium_plan_found`) —
it fails safe rather than assigning the wrong plan.

## Who gets a trial

Only an author's very first published listing, and only if they submitted
without paying (no plan selected, or the Free plan explicitly chosen).
Anyone who pays for Standard or Premium at signup keeps what they paid for
— untouched.

## Why this doesn't use ListingPro's built-in plan-expiry system

ListingPro already has a daily cron (`lp_expire_this_listing` in the theme's
`functions.php`) that expires listings whose `lp_purchase_days` meta runs
out. It was **not** reused here on purpose: that cron sets `post_status`
to `expired` — unpublishing the listing — regardless of whether a fallback
plan is configured. That's correct for a real subscription lapsing, but
wrong for a trial, where the requirement is a listing that stays live on a
free Basic plan.

So trial state lives in its own meta keys, never touches
`lp_purchase_days`, and is run by its own daily cron
(`ip_trial_daily_check`). Nothing here changes any global ListingPro
setting, and real paid subscriptions are completely unaffected.

## Verify

1. **Grant:** Log in as a brand-new user with no prior listings, submit a
   listing without selecting/paying for a plan. Check post meta on that
   listing: `_ip_trial_active` = `yes`, `Plan_id` = the Premium plan's post
   ID. Confirm the trial-start email arrived.
2. **Badge:** View the listing while logged out — the countdown badge
   should show `"30 days left on free trial"` (adjust for whatever day you
   test on).
3. **Reminder:** Temporarily edit the listing's `_ip_trial_start` post meta
   (via Query Monitor's DB tab or phpMyAdmin) to a timestamp 28 days in the
   past, then manually trigger `do_action('ip_trial_daily_check');` from a
   throwaway WP-CLI command or a one-off admin-ajax test — confirm the
   reminder email sends and `_ip_trial_reminder_sent` flips to `yes`, and
   that running it again the same day does **not** send a second reminder.
4. **Downgrade:** Set `_ip_trial_start` to 31 days in the past, run the
   daily check again, confirm `Plan_id` switches to the Free plan's post
   ID, `_ip_trial_active` becomes `no`, the badge disappears, and the
   "trial ended" email arrives.
5. **Paid signup untouched:** Submit a listing as a new user who pays for
   Standard or Premium at signup — confirm no trial meta gets set at all
   (`_ip_trial_decided` = `paid_at_signup`).

## Known assumption to confirm with the client

The instructor dashboard URL is assumed to be `/dashboard/` in the email
templates' upgrade link. If it's a different slug, update the
`$dashboard_url` line near the top of `ip_trial_send_email()`.
