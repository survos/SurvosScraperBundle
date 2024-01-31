# Scraper-Bundle

A Symfony bundle that allows a disk-based web scaper cache.

It also allows a fetch to happen from twig.  While this is not a good practice in production, it can speed up prototyping and demos.

Eventually this will be a real cache adapter, but for the moment simply fetching web pages to local storage is sufficient.

After installing the bundle,

## Installation 

```bash
composer req survos/scraper-bundle
```

If you're not using Flex, enable the bundle by adding the class to bundles.php
```php
// config/bundles.php
<?php

return [
    //...
    Survos\Bundle\SurvosScraperBundle::class => ['all' => true],
    //...
];
```

## Working Demo

Cut and paste the following to see it in action.  

```bash
symfony new --webapp scraper-bundle-demo && cd scraper-bundle-demo
composer req survos/scraper-bundle
symfony console make:controller AppController
sed -i "s|/app|/|" src/Controller/AppController.php 

cat <<'EOF' > templates/app/index.html.twig
{% extends 'base.html.twig' %}
{% block body %}
    {% set url = 'https://jsonplaceholder.typicode.com/users' %}
    {% set users = request_data(url) %}
    <ul>
        {% for row in users %}
            <li>{{ row.name }} / {{ row.website }}</li>
        {% endfor %}
    </ul>
{% endblock %}
EOF
symfony server:start -d
symfony open:local

```

When you refresh the page, it will use the cached data and be much faster.  To see the fetch in the debug toolbar, clear the cache and reload.

```bash
bin/console cache:pool:clear --all
symfony open:local
```

To use in a service or controller, inject the cache.

```php
    public function index(ScraperService $scraper): Response
    {
        $data = $scraper->fetchData('https://jsonplaceholder.typicode.com/albums', asData: 'object');
        
    }
```




