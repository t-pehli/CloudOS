<?php

/** 	Helper class that contains all system settings and environment variables
* 	and also access lists for modules to be loaded during initialistaion.
*/
class SYSTEM
{

	// ---------------- Constructor ------------------
	public static function start () {

		SYSTEM::$CYCLE = 0;

		SYSTEM::loadConfiguration();
		SYSTEM::loadStatus();

		if( SYSTEM::$STATUS['POWER'] == "RESTART" ){
		
			SYSTEM::$STATUS['POWER'] = "ON";
			SYSTEM::saveStatus();
		}

		SYSTEM::handleShutdown();
	}
	// -----------------------------------------------


	// ---------- Main Operation Methods -------------
	public static function loop(){

		if( PULSE::$END_TIME - ( microtime(true)*1000 ) < 1 || SYSTEM::$CYCLE > 300 ){

			SYSTEM::$CYCLE = -1;
		}
		else {

			SYSTEM::$CYCLE++;
			usleep( 20000 );
		}

		
		if( PULSE::$COUNT > 550 ){ //TODO remove debug mode

			SYSTEM::$CYCLE = -1;
			SYSTEM::powerOff();
		}
		
	}

	public static function runDirective( $directive ){
		
		if( strcasecmp( $directive, "STATUS") == 0 ){

			echo json_encode( SYSTEM::$STATUS );
		}
		else if( strcasecmp( $directive, "CONTROLS") == 0 ){

			require ( "idle.php" );
		}
		else if( strcasecmp( $directive, "START") == 0 ){

			SYSTEM::loadEnvironment();
			SYSTEM::powerOn();
			require ( "idle.php" );
		}
		else if( strcasecmp( $directive, "STOP") == 0 ){

			SYSTEM::loadEnvironment();
			SYSTEM::powerOff();
			require ( "idle.php" );
		}
	}


	public static function powerOn(){

			SYSTEM::logx( "CLEAR_LOG" );

		if( SYSTEM::$STATUS['POWER'] != "ON" ){

			SYSTEM::$STATUS['POWER'] = "ON";
			SYSTEM::$STATUS['CONNECTION'] = "OFF";
			SYSTEM::saveStatus();

		}
		else {

			SYSTEM::logx( "Already online!" );
		}
	}

	public static function powerOff(){

		// ============= Stop ==============
		$MAIN = SYSTEM::$PARAMETERS['ENVIRONMENTS'][SYSTEM::$ENVIRONMENT]['MAIN_CLASS'];
		if( method_exists( $MAIN, "stop")){

			$MAIN::stop();	
		}
		// ==================================

		SYSTEM::$STATUS = [];
		SYSTEM::$STATUS['POWER'] = "OFF";
		SYSTEM::$STATUS['CONNECTION'] = "OFF";
		SYSTEM::saveStatus();
	}
	// -----------------------------------------------


	// -------------- Helper Methods -----------------
	public static function loadConfiguration(){

		$conf = json_decode( file_get_contents("kernel/conf.json"), true);
		foreach ($conf as $property => $value) {
			SYSTEM::$PARAMETERS[$property] = $value;
		}
	}

	public static function saveConfiguration(){

		$conf = json_encode( SYSTEM::$PARAMETERS, JSON_PRETTY_PRINT );
		file_put_contents( "kernel/conf.json", $conf );
	}

	public static function loadStatus( ){

		SYSTEM::$STATUS = json_decode( file_get_contents("kernel/status.json"), true);
		if( !isset( SYSTEM::$STATUS['POWER'] ) ){

			SYSTEM::$STATUS['POWER'] = "OFF";
		}

		if( !isset( SYSTEM::$STATUS['CONNECTION'] ) ){

			SYSTEM::$STATUS['CONNECTION'] = "OFF";
		}
	}

	public static function saveStatus( ){

		file_put_contents( "kernel/status.json", json_encode(SYSTEM::$STATUS) );
	}

	public static function loadEnvironment(){

		if( 
			isset($_POST['environment']) 
			&& array_key_exists( $_POST['environment'], SYSTEM::$PARAMETERS['ENVIRONMENTS'])
		){
		
			SYSTEM::$ENVIRONMENT = $_POST['environment'];
			SYSTEM::$STATUS['ENVIRONMENT'] = SYSTEM::$ENVIRONMENT;
		}
		else if( isset(SYSTEM::$STATUS['ENVIRONMENT']) ){

			SYSTEM::$ENVIRONMENT = SYSTEM::$STATUS['ENVIRONMENT'];
		}
		else{

			SYSTEM::$ENVIRONMENT = "SHELL";
			SYSTEM::$STATUS['ENVIRONMENT'] = SYSTEM::$ENVIRONMENT;
		}
	
	}

	public static function logx ( $out ){
		// stringify if not
		if( !is_string($out) ) {
			$out = print_r( $out, true );
		}

		if( $out == "CLEAR_LOG" ){

			file_put_contents("log.txt", "CloudOS System Logfile:");
		}
		else {

			file_put_contents("log.txt", "\n".$out, FILE_APPEND);
		}
	}

	public static function handleShutdown(){

		function shutdown_handler() {

			$error = error_get_last();
			
			if ( $error["type"] > 0 ){

				chdir( $_SERVER['DOCUMENT_ROOT'] );

				SYSTEM::logx( "Error ".$error["type"].": ".$error["message"] );
				SYSTEM::logx( "File: ".$error["file"]." line: ".$error["line"] );
			}
			if( $error['type'] == E_ERROR 
				|| $error['type'] == E_COMPILE_ERROR 
				|| ( $error['type'] == E_PARSE && SYSTEM::$STATUS['POWER'] != "DONE" ) ){
				// fatal error has occured

				if( isset( SYSTEM::$ENVIRONMENT ) ){

					$MAIN=SYSTEM::$PARAMETERS['ENVIRONMENTS'][SYSTEM::$ENVIRONMENT]['MAIN_CLASS'];
					$MAIN::handleShutdown( $error );
				}

				
				SYSTEM::$STATUS['POWER'] = "RESTART";
				SYSTEM::saveStatus();
				PULSE::fire();
			}
		}

		register_shutdown_function('shutdown_handler');
	}
	// -----------------------------------------------


	public static $PARAMETERS = [];
	public static $MEMORY = [];
	public static $STATUS = [];
	public static $ENVIRONMENT = "";
	public static $CYCLE = 0;
	public static $DEBUG = "";


}
SYSTEM::start();

?>