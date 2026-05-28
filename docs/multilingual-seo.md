# Multilingual SEO Notes

The language switcher uses real URL paths:

- English: `/pages/about`
- Arabic: `/ar/pages/about`
- Chinese: `/zh/pages/about`
- Spanish: `/es/pages/about`

SEO guardrails applied:

- No automatic redirects by browser language or IP.
- English URLs remain the canonical URLs.
- Localized proxy pages include `noindex,follow` while the site has only partial interface translations. This prevents duplicate-language search results until each localized page has a full human-reviewed translation.
- Localized routes are crawlable for users and internal QA, but they are not submitted as hreflang alternates yet.
- When full translations are ready, remove `noindex`, make each localized URL self-canonical, and add reciprocal `hreflang` alternates including `x-default`.

Reference guidance:

- Google recommends different URLs for different language versions and `hreflang` only for true localized equivalents.
- Google warns that translating only boilerplate while the main content stays in one language can create a poor search experience.
- Google recommends letting users switch languages without forced automatic redirects.
