# Turbo + GTM (Hotwired) Patterns

Use this reference when a Craft CMS site uses:
- `@hotwired/turbo`
- `<meta name="turbo-refresh-method" content="morph">`

## Recommended Pattern (GTM + Turbo Pageviews)

```twig
{% if isProduction %}
  <script>
    document.addEventListener('turbo:load', function (event) {
      window.dataLayer = window.dataLayer || [];
      window.dataLayer.push({
        event: 'pageView',
        virtualUrl: (event && event.detail && event.detail.url) ? event.detail.url : window.location.href
      });
    });

    (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-[id]');
  </script>
{% endif %}
```

Body fallback:

```twig
{% if isProduction %}
  <noscript>
    <iframe src="https://www.googletagmanager.com/ns.html?id=GTM-[id]" height="0" width="0" style="display:none;visibility:hidden"></iframe>
  </noscript>
{% endif %}
```

## GA4-Only Pattern (No GTM)

```twig
{% if isProduction %}
  <script async src="https://www.googletagmanager.com/gtag/js?id=GA4-[id]"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());

    document.addEventListener('turbo:load', function (event) {
      gtag('config', 'GA4-[id]', {
        page_location: (event && event.detail && event.detail.url) ? event.detail.url : window.location.href
      });
    });
  </script>
{% endif %}
```

## Anti-Patterns

- Tracking only on initial HTML load while Turbo handles navigation.
- No `turbo:load` bridge for virtual pageviews.
- Re-inserting GTM bootstrap repeatedly on partial renders.
- Mixing multiple event names for the same pageview concept.
- Splitting production guards into multiple disconnected condition blocks in the same area.
