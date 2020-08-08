Zk2\SpsDbalComponent
================

Often there is a need to provide the end user with the possibility of complex filtering any data.
It is quite problematic to correctly place parentheses in the AND / OR set.
It is even more problematic to filter / sort by value from the aggregating function.

The component is intended for building valid blocks "WHERE", "OFFSET", "LIMIT" and "ORDER BY"
in `Doctrine\DBAL\Query\QueryBuilder`.
Also, the component allows you to use result of  aggregating functions in the blocks 
"WHERE" and "ORDER BY".

Documentation
-------------

[Quick start](https://github.com/zk2/sps-dbal-component/blob/master/doc/quick_start.rst)

[Definitions](https://github.com/zk2/sps-dbal-component/blob/master/doc/definitions.rst)

[Usage](https://github.com/zk2/sps-dbal-component/blob/master/doc/usage.rst)

Running the Tests
-----------------

Environment for tests inside `Docker`

It provides 3 DataBase engine (MySql, Postgres and SQLite) with fixtures.

For run tests:

    cd tests/docker
    docker-compose up -d
    docker-compose exec php ./vendor/bin/phpunit

License
-------

This bundle is released under the MIT license. See the complete license in the bundle:

    LICENSE
    
