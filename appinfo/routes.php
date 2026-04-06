<?php
return [
  'routes' => [
    ['name' => 'forecast#create', 'url' => '/new', 'verb' => 'POST'],
    ['name' => 'structuredProduct#definition', 'url' => '/products/{type}', 'verb' => 'GET'],
    ['name' => 'structuredProduct#generate', 'url' => '/products/generate', 'verb' => 'POST'],
  ],
];
