Elasticsearch 8 Connector
=========================
<small>provided by [Ellinaut](https://github.com/Ellinaut) </small>

---

## What is this library for?

This library provides a reusable structure and implementation for common tasks related to elasticsearch development with
PHP. The goal is to have the same structure in every application and to avoid rewriting simple tasks like creation of
indices or storing documents every time you need it.

The library uses the official library [elasticsearch/elasticsearch](https://github.com/elastic/elasticsearch-php) and
adds some more structure and features.

## Requirements

This library requires you to use PHP in the version 7.2 or higher and an elasticsearch server with version 8.x.

## Installation

The simplest way to install this library to your application is composer:

```composer require ellinaut/elasticsearch-8-connector```

## How to use this library in your application

Core of this library, and the entry point for each call from your application, is the
class `Ellinaut\ElasticsearchConnector\ElasticsearchConnector`.

So you need an instance of this class, which requires instances of
`Ellinaut\Elasticsearch\Connection\ConnectionFactoryInterface`,
`Ellinaut\Elasticsearch\NameProvider\NameProviderInterface` and
`Ellinaut\Elasticsearch\Connection\ResponseHandlerInterface` (optional).

Here is an example for instance which connect to localhost on the default port and does not change index or pipeline
names between PHP and elasticsearch:

```php
    use Ellinaut\ElasticsearchConnector\Connection\DsnConnectionFactory;
    use Ellinaut\ElasticsearchConnector\NameProvider\RawNameProvider;
    use Ellinaut\ElasticsearchConnector\ElasticsearchConnector;

    $elasticsearch = new ElasticsearchConnector(
        new DsnConnectionFactory('http://127.0.0.1:9200'),
        new RawNameProvider(),
        new RawNameProvider()
    );
```

## How to manage connections

Connections are created with the help of a ConnectionFactory (an instance
of `Ellinaut\ElasticsearchConnector\Connection\ConnectionFactoryInterface`). The created connection is an instance of
`Elasticsearch\Client` which is used for all actions executed with the `ElasticsearchConnector`.

The simplest way is to use the `DsnConenctionFactory` provided by this library (see above) but if your configuration is
more complex you are also able to implement the `ConnectionFactoryInterface` by your self.

## How to manage indices

The `ElasticsearchConnector` provides some methods to manage indices. Each method will result in one or more calls to an
instance of `Ellinaut\ElasticsearchConnector\Index\IndexManagerInterface`. Each index requires an instance of this
interface which have to be provided and registered to the connector by your application.

To simplify your implementation, you can use the trait `Ellinaut\ElasticsearchConnector\Index\IndexManagerTrait`. This
trait requires that you implement the method `getIndexDefinition`, which have to provide the elasticsearch index
configuration as array. The trait uses this method and provides all methods required by the `IndexManagerInterface`.

Here is an example how a custom `IndexManager` could look like:

```php
    namespace App\IndexManager;
    
    use Ellinaut\ElasticsearchConnector\Index\IndexManagerInterface;
    use Ellinaut\ElasticsearchConnector\Index\IndexManagerTrait;

    class CustomIndexManager implements IndexManagerInterface {
        use IndexManagerTrait;
        
        /**
         * @return array
         */
        protected function getIndexDefinition() : array{
            return [
                'mappings' => [
                    'properties' => [
                        'test' => [
                            'type' => 'keyword',
                        ],
                    ],
                ],
            ];
        }
    }
```

To use your custom index manager, you have to register it on the connector instance:

```php
    /** @var \Ellinaut\ElasticsearchConnector\ElasticsearchConnector $elasticsearch */
    $elasticsearch->addIndexManager('custom_index', new App\IndexManager\CustomIndexManager());
```

Then you can use this index through these connector method calls:

```php
    /** @var \Ellinaut\ElasticsearchConnector\ElasticsearchConnector $elasticsearch */
    
    // Creates the index. Will throw an exception if the index already exists.
    $elasticsearch->createIndex('custom_index');
    
    // Creates the index only if it does not exist.
    $elasticsearch->createIndexIfNotExist('custom_index');
    
    // Deletes the index if it exists, then create the index new.
    $elasticsearch->recreateIndex('custom_index');
    
    // Migrate all documents from the index to a (new) migration index,
    // then recreate the old index and moves all the documents back.
    $elasticsearch->updateIndex('custom_index');
    
    // Deletes the index if it exists.
    $elasticsearch->deleteIndex('custom_index');
```

### Index Naming

Up to now only the internal index name was used in the documentation. If your application uses more than one
environment, it might make sense to use different index names for each environment, especially when hosted on the same
elasticsearch server.

That can be reached by using an index name provider, which is an instance
of `Ellinaut\ElasticsearchConnector\NameProvider\NameProviderInterface`. This provider will "decorate" your internal
index names for all elasticsearch requests and can be used to get the original (internal) index name from the (external)
elasticsearch index name.

Build in providers are:

* `Ellinaut\ElasticsearchConnector\NameProvider\RawNameProvider`: For equal naming in PHP and elasticsearch
* `Ellinaut\ElasticsearchConnector\NameProvider\PrefixedNameProvider`: For a custom prefix on external index names
* `Ellinaut\ElasticsearchConnector\NameProvider\SuffixedNameProvider`: For a custom suffix on external index names
* `Ellinaut\ElasticsearchConnector\NameProvider\ChainedNameProvider`: To combine two or more providers

If you need a custom naming strategy, you can also implement the `NameProviderInterface` with your custom name provider.

The name provider is given to the connector within the constructor.

### Index Migrations

By default, the `updateIndex` method work with these steps:

1. Create the migration index (index name will be "INDEX_NAME__migrating")
1. Fetch old documents from the old index
1. Store old documents to the migration index
1. Move all documents from the old index to the migration index
1. Delete the old index
1. Create the new index with the same name as the old index
1. Move all documents form the migration index to the new index
1. Delete the migration index

This procedure might be useful for simple migrations where only a new field will be added or some analyzer or field
configurations will be changed. If your migration requires data changes, you are able to provide an instance of
`Ellinaut\ElasticsearchConnector\Document\DocumentMigratorInterface` to the connector.

Here is how your document migrator could look like:

```php
    namespace App\Document;

    use Ellinaut\ElasticsearchConnector\Document\DocumentMigratorInterface;
    
    class CustomDocumentMigrator implements DocumentMigratorInterface {
    
        /**
         * @param array $previousSource
         * @return array
         */
        public function migrate(array $previousSource) : array{
            if(!array_key_exists('test',$previousSource)){
                $previousSource['test'] = 'Test';
            }
            
            return $previousSource;
        }
    }
```

It can be added to the connector like this:

```php
    /** @var \Ellinaut\ElasticsearchConnector\ElasticsearchConnector $elasticsearch */
    $elasticsearch->addDocumentMigrator('custom_index', new App\Document\CustomDocumentMigrator());
```

If you have added a document migrator to the connector, the new procedure for `updateIndex` will be:

1. Create the migration index (index name will be "INDEX_NAME__migrating")
1. Fetch old documents from the old index
1. Migrate old documents with the document migrator
1. Store migrated documents to the migration index
1. Delete the old index
1. Create the new index with the same name as the old index
1. Move all documents form the migration index to the new index
1. Delete the migration index

## How to manage pipelines

The `ElasticsearchConnector` provides some methods to manage pipelines. Each method will result in one or more calls to
an instance of `Ellinaut\ElasticsearchConnector\Index\PipelineManagerInterface`. Each pipeline requires an instance of
this interface which have to be provided and registered to the connector by your application.

To simplify your implementation, you can use the trait `Ellinaut\ElasticsearchConnector\Index\PipelineManagerTrait`.
This trait requires that you implement the method `getPipelineDefinition`, which have to provide the elasticsearch
pipeline configuration as array. The trait uses this method and provides all methods required by
the `PipelineManagerInterface`.

Here is an example how a custom `PipelineManager` could look like:

```php
    namespace App\PipelineManager;
    
    use Ellinaut\ElasticsearchConnector\Index\PipelineManagerInterface;
    use Ellinaut\ElasticsearchConnector\Index\PipelineManagerTrait;

    class CustomPipelineManager implements PipelineManagerInterface {
        use PipelineManagerTrait;
        
        /**
         * @return array
         */
        protected function getPipelineDefinition() : array{
            return [
                'description' => 'Your custom pipeline which converts content from field "test" to lowercase.',
                'processors' => [
                    [
                        'lowercase' => [
                            'field' => 'test',
                        ],
                    ],
                ],
            ];
        }
    }
```

To use your custom pipeline manager, you have to register it on the connector instance:

```php
    /** @var \Ellinaut\ElasticsearchConnector\ElasticsearchConnector $elasticsearch */
    $elasticsearch->addPipelineManager('custom_pipeline', new App\PipelineManager\CustomPipelineManager());
```

Then you can use this pipeline through these connector method calls:

```php
    /** @var \Ellinaut\ElasticsearchConnector\ElasticsearchConnector $elasticsearch */
    
    // Creates all registered pipelines.
    $elasticsearch->createPipelines();
    
    // Creates only the given pipeline.
    $elasticsearch->createPipelines(['custom_pipeline']);
    
    // Deletes all registered pipelines.
    $elasticsearch->deletePipelines();
    
    // Deletes only the given pipeline.
    $elasticsearch->deletePipelines(['custom_pipeline']);
```

### Pipeline Naming

As with the indices also pipeline names could be different between PHP and elasticsearch. The name providers are the
same as for indices.

## How to manage documents

The `ElasticsearchConnector` provides some methods to manage documents. You should use the method `indexDocument` to
create or update a document. You should use the method `deleteDocument` to delete a document by ID. You could also
use `indexDocumentImmediately` or `deleteDocumentImmediately` if you don't want to use bulk requests. You can retrieve
single documents via methods `retrieveDocument` or `retrieveDocumentSource`.

See how it looks like in your code:

```php
    /** @var \Ellinaut\ElasticsearchConnector\ElasticsearchConnector $elasticsearch */
    
    // Index the document with ID "document_1" into the index "custom_index" and use pipeline "custom_pipeline".
    // Document is index via queue system which uses bulk requests internally. Recommended method to index documents.
    $elasticsearch->indexDocument('custom_index','document_1', ['test'=>'Test'],'custom_pipeline');
    
    // Index the document with ID "document_1" into the index "custom_index" and use pipeline "custom_pipeline".
    // Document is indexed immediately to elasticsearch.
    $elasticsearch->indexDocumentImmediately('custom_index','document_1', ['test'=>'Test'],'custom_pipeline');
    
    // Retrieves the full document by ID from elasticsearch.
    $elasticsearch->retrieveDocument('custom_index','document_1');
    
    // Retrieves only the content of key "_source" from the elasticsearch document.
    $elasticsearch->retrieveDocumentSource('custom_index','document_1');
    
    // Deletes the document with ID "document_1" from index "custom_index".
    // Document is deleted via queue system which uses bulk requests internally. Recommended method to delete documents.
    $elasticsearch->deleteDocument('custom_index','document_1');
    
    // Deletes the document with ID "document_1" from index "custom_index".
    // Document is deleted immediately from elasticsearch.
    $elasticsearch->deleteDocumentImmediately('custom_index','document_1');
```

### Queue System

If you use the recommended methods to index or delete documents, your "commands" are internally stored in a queue. If
the configured `maxQueueSize` (via constructor of `ElasticsearchConnector`) is reached, or a different pipeline should
be used for the next document, the queue is executed automatically. If the limit is never reached, you have to
call `executeQueueImmediately` before the php process is finished. In a framework like symfony this should be done via
an event listener. In your custom application this method should be executed at the end of your php script or
application cycle. The method will execute all queued commands within a single bulk request to elasticsearch, which will
improve your application performance through reduction of http requests.

```php
    /** @var \Ellinaut\ElasticsearchConnector\ElasticsearchConnector $elasticsearch */
    
    // Executes all queued index or delete commands with a single bulk request.
    $elasticsearch->executeQueueImmediately();
```

## How to search documents

The connector offers you a method to search results and a method to count results without fetching all results. These
methods make use of the base methods from the `Elasticsearch\Client`. The difference is that the connector methods take
care of the internal index names and convert them to external index names, which can be used for elasticsearch requests.

```php
    /** @var \Ellinaut\ElasticsearchConnector\ElasticsearchConnector $elasticsearch */
    
    // Executes a search request over all indices.
    $searchAllResult = $elasticsearch->executeSearch(
        [
            'body' => [
                'query' => [
                    'match_all' => (object)[] // fix for elasticsearch, because an empty array doesn't work in the api
                ] 
            ]
        ]
    );
    
    // Executes a search request only for the index "custom_index".
    $searchCustomResult = $elasticsearch->executeSearch(
        [
            'body' => [
                'query' => [
                    'match_all' => (object)[] // fix for elasticsearch, because an empty array doesn't work in the api
                ] 
            ]
        ],
        ['custom_index']
    );
    
    // Executes a count request over all indices.
    $numberOfAllResults = $elasticsearch->executeCount(
        [
            'body' => [
                'query' => [
                    'match_all' => (object)[] // fix for elasticsearch, because an empty array doesn't work in the api
                ] 
            ]
        ]
    );
    
    // Executes a count request only for the index "custom_index".
    $numberOfCustomResults = $elasticsearch->executeCount(
        [
            'body' => [
                'query' => [
                    'match_all' => (object)[] // fix for elasticsearch, because an empty array doesn't work in the api
                ] 
            ]
        ],
        ['custom_index']
    );
```

## How to handle more complex scenarios

The goal of this library is to standardize and simplify common actions with PHP and elasticsearch. More complex
scenarios has to been solved by your application, but the connector can help you with that.

The connector offers some helper methods, which could be useful for your custom scenario:

```php
    /** @var \Ellinaut\ElasticsearchConnector\ElasticsearchConnector $elasticsearch */
    
    // Returns the configured instance of "Elasticsearch\Client" which is also used by the connector.
    $connection = $elasticsearch->getConnection();
    
    // Get the external index name for elasticsearch requests
    $externalIndexName = $elasticsearch->getExternalIndexName('custom_index');
    
    // Get the internal index name for internal mappings with the result of an elasticsearch request
    $internalIndexName = $elasticsearch->getInternalIndexName('external_custom_index');
    
    // Get the external pipeline name for elasticsearch requests
    $externalPipelineName = $elasticsearch->getExternalPipelineName('custom_pipeline');
    
    // Get the internal pipeline name for internal mappings with the result of an elasticsearch request
    $internalPipelineName = $elasticsearch->getInternalPipelineName('external_custom_pipeline');
```

## Error Handling / Response Handling

Sometimes you will need direct access to the elasticsearch responses. This will be useful for custom error handling or
debugging. You are able to provide an instance of `Ellinaut\ElasticsearchConnector\Connection\ResponseHandlerInterface`
via constructor to the connector.

A custom response handler could look like this:

```php
    namespace App\PipelineManager;
    
    use Ellinaut\ElasticsearchConnector\Connection\ResponseHandlerInterface;

    class CustomResponseHandler implements ResponseHandlerInterface {
    
        /**
         * @param string $method
         * @param array $response
         */
        public function handleResponse(string $method,array $response) : void{
            if($method === 'createIndex'){
                var_dump($response);
            }
        }
    }
```

The response handler will be called for responses in these methods:

* `IndexManagerInterface::createIndex`
* `IndexManagerInterface::updateIndex`
* `IndexManagerTrait::moveDocuments`
* `IndexManagerInterface::deleteIndex`
* `PipelineManagerInterface::createPipeline`
* `PipelineManagerInterface::deletePipeline`
* `ElasticsearchConnector::indexDocumentImmediately`
* `ElasticsearchConnector::deleteDocumentImmediately`
* `ElasticsearchConnector::executeQueueImmediately`

---
<small>Ellinaut is powered by [NXI GmbH & Co. KG](https://nxiglobal.com)
and [BVH Bootsvermietung Hamburg GmbH](https://www.bootszentrum-hamburg.de).</small>
