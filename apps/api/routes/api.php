<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    require __DIR__.'/api/auth.php';
    require __DIR__.'/api/portal.php';
    require __DIR__.'/api/master.php';
    require __DIR__.'/api/academic.php';
    require __DIR__.'/api/cbt.php';
    require __DIR__.'/api/student_affairs.php';
    require __DIR__.'/api/counseling.php';
    require __DIR__.'/api/facilities.php';
    require __DIR__.'/api/cms.php';
    require __DIR__.'/api/ppdb.php';
    require __DIR__.'/api/guest.php';
    require __DIR__.'/api/relations.php';
    require __DIR__.'/api/notifications.php';
    require __DIR__.'/api/reports.php';
    require __DIR__.'/api/finance.php';
});
