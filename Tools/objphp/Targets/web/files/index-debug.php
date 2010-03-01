<?php
/*
 * index-debug.php
 * ##ProjectName##
 *
 * Copyright ##Copyright##
 * ##Author## <##AuthorEmail##>
 * Created ##Date##
 */

const _DEBUG_ = true;

define('OBJPHP_INCLUDE_PATH', getenv('OBJPHP'));
define('OBJPHP_APP_PATH', __DIR__);

set_include_path( OBJPHP_INCLUDE_PATH . PATH_SEPARATOR . OBJPHP_INCLUDE_PATH."/Moka/"  . PATH_SEPARATOR. OBJPHP_APP_PATH);

include_once 'Objective-PHP/runtime.php';

// Create a runtime and bootstrap by loading AppController.op
$pp = new ObjPHP\PreProcessor();
if( $source = $pp->loadObjPHPFile("AppController.op") )
    $pp->run($source);
