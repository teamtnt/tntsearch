<?php

function dd() {
    array_map(function($x) { var_dump($x); }, func_get_args());
    die;
}