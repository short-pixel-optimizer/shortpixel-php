<?php
/**
 * User: simon
 * Date: 19.08.2016
 * Time: 18:01
 */
namespace ShortPixel;

interface Persister {
    function __construct($options);
    function isOptimized($path);
    function getOptimizationData($path);
    function info($path);
    function getTodo($path, $count, $nextFollows = false);
    function getNextTodo($path, $count);
    function doneGet();
    function setPending($path, $optData);
    function setOptimized($path, $optData);
    function setFailed($path, $optData);
}