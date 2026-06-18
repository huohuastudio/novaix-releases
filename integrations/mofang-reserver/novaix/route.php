<?php

use think\facade\Route;

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$cors = [
    'Access-Control-Allow-Origin'      => $origin,
    'Access-Control-Allow-Credentials' => 'true',
    'Access-Control-Max-Age'           => 600,
];

$ctrl = "\\reserver\\novaix\\controller\\home\\CloudController@";

// 无需登录的接口（购买页计价）
Route::group('console/v1', function () use ($ctrl) {

    Route::get('renovaix/product/:product_id/configoption', $ctrl . "cartConfigoption");

})->allowCrossDomain($cors)->middleware(\app\http\middleware\Check::class);

// 需要登录的接口
Route::group('console/v1', function () use ($ctrl) {

    Route::get('renovaix/host', $ctrl . "hostList");
    Route::get('renovaix/host/:host_id/configoption', $ctrl . "hostConfigoption");
    Route::post('renovaix/host/:host_id/provision/status', $ctrl . "provisionFuncStatus");
    Route::post('renovaix/host/:host_id/provision/vnc', $ctrl . "provisionVnc")
        ->middleware(\app\http\middleware\CheckClientOperatePassword::class);
    Route::post('renovaix/host/:host_id/provision/:func', $ctrl . "provisionFunc")
        ->middleware(\app\http\middleware\CheckClientOperatePassword::class);

})->allowCrossDomain($cors)
->middleware(\app\http\middleware\CheckHome::class)
->middleware(\app\http\middleware\ParamFilter::class);
