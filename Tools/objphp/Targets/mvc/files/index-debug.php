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

date_default_timezone_set("##DefaultTimeZone##");

define('OBJPHP_INCLUDE_PATH', getenv('OBJPHP'));
define('OBJPHP_APP_PATH', __DIR__);

set_include_path( OBJPHP_INCLUDE_PATH . PATH_SEPARATOR . OBJPHP_INCLUDE_PATH."/Moka/"  . PATH_SEPARATOR. OBJPHP_APP_PATH);

include_once 'Objective-PHP/runtime.php';

// Create a runtime and bootstrap by loading FrontController.op
$pp = _objphp_PreProcessor::getInstance();
if( $source = $pp->loadObjPHPFile("FrontController.op") )
    $pp->run($source);
