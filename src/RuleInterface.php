<?php

namespace Zk2\SpsDbalComponent;

interface RuleInterface
{
    const EQUALS = '=';
    const NOT_EQUALS = '!=';
    const GREATER_THAN = '>';
    const GREATER_THAN_OR_EQUAL = '>=';
    const LESS_THAN = '<';
    const LESS_THAN_OR_EQUAL = '<=';
    const IS_NULL = 'IS NULL';
    const IS_NOT_NULL = 'IS NOT NULL';
    const IN = 'IN';
    const NOT_IN = 'NOT IN';
    const LIKE = 'LIKE';
    const NOT_LIKE = 'NOT LIKE';
    const BETWEEN = 'BETWEEN';
    const NOT_BETWEEN = 'NOT BETWEEN';

    const TOKEN_EQUALS = 'equals';
    const TOKEN_NOT_EQUALS = 'not_equals';
    const TOKEN_GREATER_THAN = 'greater_than';
    const TOKEN_GREATER_THAN_OR_EQUAL = 'greater_than_or_equal';
    const TOKEN_LESS_THAN = 'less_than';
    const TOKEN_LESS_THAN_OR_EQUAL = 'less_than_or_equal';
    const TOKEN_IS_NULL = 'is_null';
    const TOKEN_IS_NOT_NULL = 'is_not_null';
    const TOKEN_IN = 'in';
    const TOKEN_NOT_IN = 'not_in';
    const TOKEN_BEGINS_WITH = 'begins_with';
    const TOKEN_ENDS_WITH = 'ends_with';
    const TOKEN_CONTAINS = 'contains';
    const TOKEN_NOT_BEGINS_WITH = 'not_begins_with';
    const TOKEN_NOT_ENDS_WITH = 'not_ends_with';
    const TOKEN_NOT_CONTAINS = 'not_contains';
    const TOKEN_BETWEEN = 'between';
    const TOKEN_NOT_BETWEEN = 'not_between';

    const COMPARISON_OPERATORS = [
        self::TOKEN_EQUALS => self::EQUALS,
        self::TOKEN_NOT_EQUALS => self::NOT_EQUALS,
        self::TOKEN_GREATER_THAN => self::GREATER_THAN,
        self::TOKEN_GREATER_THAN_OR_EQUAL => self::GREATER_THAN_OR_EQUAL,
        self::TOKEN_LESS_THAN => self::LESS_THAN,
        self::TOKEN_LESS_THAN_OR_EQUAL => self::LESS_THAN_OR_EQUAL,
        self::TOKEN_IS_NULL => self::IS_NULL,
        self::TOKEN_IS_NOT_NULL => self::IS_NOT_NULL,
        self::TOKEN_IN => self::IN,
        self::TOKEN_NOT_IN => self::NOT_IN,
        self::TOKEN_BEGINS_WITH => self::LIKE,
        self::TOKEN_ENDS_WITH => self::LIKE,
        self::TOKEN_CONTAINS => self::LIKE,
        self::TOKEN_NOT_BEGINS_WITH => self::NOT_LIKE,
        self::TOKEN_NOT_ENDS_WITH => self::NOT_LIKE,
        self::TOKEN_NOT_CONTAINS => self::NOT_LIKE,
        self::TOKEN_BETWEEN => self::BETWEEN,
        self::TOKEN_NOT_BETWEEN => self::NOT_BETWEEN,
    ];

    const STRING_ONLY_OPERATORS = [
        self::TOKEN_CONTAINS,
        self::TOKEN_NOT_CONTAINS,
        self::TOKEN_BEGINS_WITH,
        self::TOKEN_NOT_BEGINS_WITH,
        self::TOKEN_ENDS_WITH,
        self::TOKEN_NOT_ENDS_WITH,
    ];

    const NUMERIC_AND_DATES_OPERATORS = [
        self::TOKEN_GREATER_THAN,
        self::TOKEN_GREATER_THAN_OR_EQUAL,
        self::TOKEN_LESS_THAN,
        self::TOKEN_LESS_THAN_OR_EQUAL,
        self::TOKEN_BETWEEN,
        self::TOKEN_NOT_BETWEEN,
    ];

    const EQUALS_OPERATORS = [
        self::TOKEN_EQUALS,
        self::TOKEN_NOT_EQUALS,
    ];

    const NULLS_OPERATORS = [
        self::TOKEN_IS_NULL,
        self::TOKEN_IS_NOT_NULL,
    ];

    const IN_OPERATORS = [
        self::TOKEN_IN,
        self::TOKEN_NOT_IN,
    ];
}
