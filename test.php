<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/2/5
 * Time: 19:01
 */

$s = base_convert(3, 10, 2);
$s1 = str_pad($s, 32, '0', STR_PAD_LEFT);
var_dump(bindec($s1));
var_dump(str_pad($s, 32, '0', STR_PAD_LEFT));

$a = '';
var_dump(isset($a));
