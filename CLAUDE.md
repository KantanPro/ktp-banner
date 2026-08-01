# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

**KTP Banner** — a small WordPress plugin that displays a rotating banner ad, built specifically to integrate with **KantanPro (WP, free edition)**. GitHub: `KantanPro/ktp-banner`.

This is a **separate product** from KantanPro/KantanProEX/KantanBiz — don't conflate it with them. It has a sister plugin, **myBanner** (`wordpress/wp-content/plugins/myBanner`), which is a fork of this plugin with all KantanPro-specific integration removed for general resale — when fixing a generic bug (not KantanPro-integration-specific), consider whether it should also be ported to myBanner.

## Key integration behavior — KantanPro edition detection is load-bearing

This plugin **must not show banners when KantanProEX (paid) is active**, only when KantanPro (free) is active or absent:
- Detects KantanProEX via the EX plugin's own activation state / published identifiers, checking multiple possible basenames (`KantanProEX`, `KantanProEx`) — not solely via the `KTPWP_EDITION` constant, since that check has previously mis-detected free installs as paid (see version history in `README.md` — this was fixed across several point releases, don't reintroduce the old constant-only check).
- When EX is active: auto-insertion (hook fallback, admin-screen notice, shortcode-output fallback insertion) is fully suppressed. An **explicitly placed** `[ktp_banner]` shortcode or the widget still renders even under EX (later behavior change — check current `README.md` changelog before assuming otherwise, this has flipped direction before).
- When no KantanPro is present at all: falls back to site-wide placement via `wp_footer` / `wp_body_open`.

## Display surfaces (all driven from one config, `KTP_Banner_Plugin::OPTION_KEY`)

- Shortcode `[ktp_banner class="..."]`
- Widget "KTP Banner" (Appearance → Widgets)
- Gutenberg block `blocks/ktp-banner/` (wide/full alignment support)
- Arbitrary `do_action('your_hook')` insertion point, configurable from the admin screen
- KantanPro admin-screen notice area (menu slug matching `ktp-`/`ktpwp` patterns)
- Automatic fallback insertion into `ktpwp_all_tab` / `kantanAllTab` / `kantanpro_ex` shortcode output when no explicit hook is configured

Multiple banners can be registered with rotation (2–60s, fade transition); legacy single-banner config auto-migrates to the multi-banner format on `admin_init`.

## Architecture

Single-file plugin: `ktp-banner.php` (~2500 lines) contains the whole `KTP_Banner_Plugin` singleton — settings page, shortcode/widget/block registration, KantanPro-detection logic, and output rendering all together. `css/ktp-banner-frontend.css` and `js/ktp-banner-{admin,frontend}.js` are the only other runtime files; `blocks/ktp-banner/` holds the Gutenberg block (`block.json` + `index.js` + `editor.css`, no build step — plain JS).

## Commands

```bash
./create_release_zip.sh   # build release zip
```

No automated test suite — verify manually against a WordPress install with KantanPro (free) present, absent, and with KantanProEX active, since the edition-detection branches are the main source of regressions here.

## Commit messages

Always write commit messages in Japanese, concise form like `〇〇を追加` / `〇〇を修正` / `〇〇のバグを修正` — never English one-liners.
