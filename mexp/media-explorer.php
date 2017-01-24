<?php
/*
Media Explorer
Extends the Media Manager to add support for external media services.
Version:     1.2
Author:      Code For The People Ltd, Automattic
Text Domain: mexp
Domain Path: /languages/
License:     GPL v2 or later

*/

defined( 'ABSPATH' ) or die();

if( class_exists( 'Media_Explorer') ) exit;

foreach ( array( 'plugin', 'mexp', 'service', 'template', 'response' ) as $class )
  require_once sprintf( '%s/class.%s.php', dirname( __FILE__ ), $class );

foreach ( glob( dirname( dirname( __FILE__ ) ) . '/hosts/*/mexp-ev-*-service.php' ) as $service )
  include $service;

// error_log( print_r( glob( dirname( __FILE__, 2 ) . '/hosts/*/class.mexp-ev-*-service.php' ), true ) );

Media_Explorer::init( __FILE__ );
