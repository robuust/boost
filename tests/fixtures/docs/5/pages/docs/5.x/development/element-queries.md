# Element Queries

Element queries fetch elements from the database.

## Advanced queries

Some criteria can only be expressed with ActiveRecord’s `where()` and `andWhere()`.

```twig
{% set entries = craft.entries()
  .section('news')
  .andWhere(['like', 'title', 'craft'])
  .all() %}
```
