<?php

declare(strict_types=1);

$string = '0d9dde11421845dc80ca9f600c0e34c9';

preg_match(
    pattern: "/(.{8})(.{4})(.{4})(.{4})(.{12})/",
    subject: $string,
    matches: $matches
);

echo $matches[1];
echo $matches[2];
echo $matches[3];
echo $matches[4];
echo $matches[5];
