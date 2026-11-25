@if (\Robuust\Boost\Stack::current()->craftMajor() === 6)
# Robuust Craft 6 (beta)

- Craft 6 is in beta, so this guidance will change until it is GA. Check the installed `craftcms/cms` version before relying on an API.
- Craft 6 is a Laravel 13 application: use `php artisan`, templates live in `resources/views/` and `public/` replaces `web/`.
- Boost's `search-docs` does not cover Craft. For a concrete Craft CMS question, call the `search-craft-docs` tool; it searches the project's Craft major by default, says when no index exists for it and never answers from another major.
- Craft 5 skills and examples do not apply as-is: Yii-based code runs only through `craftcms/yii2-adapter`, and categories, globals and tags are deprecated.
@endif
