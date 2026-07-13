# Frontend Guide: First Key / First Egg Badge

## What Changed

The backend now awards a limited signup achievement to the next 10 newly created users:

- Badge slug: `first_key_first_egg`
- Badge name: `First to the key! First to the egg!`
- Badge level: `legendary`
- Profile frame slug: `first_key_first_egg_frame`

The award is granted only during new GitHub OAuth signup. Existing accounts that reconnect GitHub or match by email do not enter this cohort.

## Badge Surfaces

The badge appears through the existing badge endpoints:

- `GET /api/v1/badges`
- `GET /api/v1/me/badges`
- `POST /api/v1/me/badges/evaluate`

Expected badge shape:

```json
{
  "slug": "first_key_first_egg",
  "name": "First to the key! First to the egg!",
  "description": "Awarded to the first 10 new members after this milestone opened.",
  "category": "special",
  "level": "legendary",
  "image_url": "/uploads/media/badges/first_key_first_egg.png",
  "image_alt": "First to the key and first to the egg badge",
  "icon": "key-round",
  "is_secret": false,
  "display_order": 190
}
```

## Profile Frame Surfaces

Authenticated profile responses now include `profile.profile_frame`.

Example:

```json
{
  "profile": {
    "role": "frontend",
    "seniority": "junior",
    "onboarding_completed": true,
    "goals": ["first_contribution"],
    "profile_frame": {
      "slug": "first_key_first_egg_frame",
      "name": "First to the key frame",
      "image_url": null,
      "style_config": {
        "variant": "founder-key-egg",
        "accent": "#f05d4f",
        "ring": "#f8c14a",
        "shadow": "#15202b",
        "glow": "#fff3c4"
      },
      "source_badge_slug": "first_key_first_egg",
      "awarded_at": "2026-07-13 15:00:00"
    }
  }
}
```

Public profiles include the same object at:

```text
data.profile.profile_frame
```

The field is nullable. Render the normal avatar/profile treatment when it is `null`.

## Rendering Recommendation

When `profile_frame.slug === "first_key_first_egg_frame"`:

- Add a decorative ring around the avatar using `style_config.ring`.
- Use `style_config.accent` for a small badge/frame accent.
- Use `style_config.glow` as a subtle outer glow if the design supports it.
- Link or visually associate the frame with the badge whose slug is `source_badge_slug`.
- Do not expose a countdown or remaining slots unless product explicitly asks for it.

Suggested CSS mapping:

```css
.profile-avatar--framed {
  box-shadow:
    0 0 0 3px var(--frame-ring),
    0 0 0 6px var(--frame-accent),
    0 10px 24px color-mix(in srgb, var(--frame-glow), transparent 25%);
}
```

If `image_url` is provided in the future, prefer the image asset for the frame overlay and keep `style_config` as fallback.

## New Signup UX

After OAuth callback, the backend still redirects normally. The frontend can detect the award by refreshing:

1. `GET /api/v1/me/badges`
2. `GET /api/v1/me/profile` or `GET /api/v1/auth/me`

If `unseen_awarded` contains `first_key_first_egg`, show the existing badge-awarded notification. Include an extra mention that the user also received a profile frame.

## Level Handling

`legendary` is now a possible badge level. If the frontend maps badge levels to colors, add a fallback-safe mapping for:

```text
legendary
```

Recommended treatment:

- Accent: `#f05d4f`
- Gold/ring: `#f8c14a`
- Text: existing high-contrast foreground color

If the frontend does not recognize a level, it should fall back to the highest existing style instead of hiding the badge.

## Email

The thank-you email is backend-owned. The frontend does not need to trigger it.

## Testing Checklist

- A user with `profile.profile_frame === null` renders normally.
- A user with `first_key_first_egg_frame` shows the avatar/profile frame.
- The public profile renders the same frame.
- `legendary` badges render with a polished fallback.
- The existing badge notification flow handles `first_key_first_egg`.
- The UI does not show remaining cohort slots.
