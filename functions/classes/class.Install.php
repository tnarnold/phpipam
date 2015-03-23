<?php

/**
 *	phpIPAM Install class
 */

class Install  {

	/**
	 * public varibles
	 */
	public	  $exception;					//to store DB exceptions

	/**
	 * protected variables
	 */
	protected $db;							//db parameters
	protected $debugging = false;			//(bool) debugging flag
	protected $settings = array();			//)object) settings for upgrade

	/**
	 * object holders
	 */
	protected $Result;						//for Result printing
	protected $Database;					//for Database connection
	protected $Database_root;				//for Database connection for installation

	/**
	 * __construct method
	 *
	 * @access public
	 * @return void
	 */
	public function __construct (Database_PDO $Database) {
		# initialize Result
		$this->Result = new Result ();
		# initialize object
		$this->Database = $Database;
		# set debugging
		$this->set_debugging ();
		# set debugging
		$this->set_db_params ();
	}

	/**
	 * Fetch settings from database
	 *
	 * @access private
	 * @return void
	 */
	private function fetch_settings () {
		# check if already set
		if(sizeof($this->settings)>0) {
			return $this->settings;
		}
		# fetch
		else {
			try { $this->settings = $this->Database->getObject("settings", 1); }
			catch (Exception $e) { $this->Result->show("danger", _("Database error: ").$e->getMessage()); }
		}
	}









	/**
	 * @install methods
	 * ------------------------------
	 */

	/**
	 * Install database files
	 *
	 * @access public
	 * @param mixed $rootuser
	 * @param mixed $rootpass
	 * @param bool $drop_database (default: false)
	 * @param bool $create_database (default: false)
	 * @param bool $create_grants (default: false)
	 * @return void
	 */
	public function install_database ($rootuser, $rootpass, $drop_database = false, $create_database = false, $create_grants = false) {

		# open new connection
		$this->Database_root = new Database_PDO ($rootuser, $rootpass);

		# set install flag to make sure DB is not trying to be selected via DSN
		$this->Database_root->install = true;

		# drop database if requested
		if($drop_database===true) 	{ $this->drop_database(); }

		# create database if requested
		if($create_database===true) { $this->create_database(); }

		# set permissions!
		if($create_grants===true) 	{ $this->create_grants(); }

	    # reset connection, reset install flag and connect again
		$this->Database_root->resetConn();

		# install database
		$this->install_database_execute ();

	    # return true, if some errors occured script already died! */
		sleep(1);
		write_log( "Database installation", "Database installed successfully. Version ".VERSION.".".REVISION." installed", 1 );
		return true;
	}

	/**
	 * Drop existing database
	 *
	 * @access private
	 * @return void
	 */
	private function drop_database () {
	 	# set query
	    $query = "drop database if exists `". $this->db['name'] ."`;";
		# execute
		try { $this->Database_root->runQuery($query); }
		catch (Exception $e) {	$this->Result->show("danger", $e->getMessage(), true);}
	}

	/**
	 * Create database
	 *
	 * @access private
	 * @return void
	 */
	private function create_database () {
	 	# set query
	    $query = "create database `". $this->db['name'] ."`;";
		# execute
		try { $this->Database_root->runQuery($query); }
		catch (Exception $e) {	$this->Result->show("danger", $e->getMessage(), true);}
	}

	/**
	 * Create user grants
	 *
	 * @access private
	 * @return void
	 */
	private function create_grants () {
	 	# set query
	    $query = 'grant ALL on '. $this->db['name'] .'.* to '. $this->db['user'] .'@localhost identified by "'. $this->db['pass'] .'";';
		# execute
		try { $this->Database_root->runQuery($query); }
		catch (Exception $e) {	$this->Result->show("danger", $e->getMessage(), true);}
	}

	/**
	 * Execute files installation
	 *
	 * @access private
	 * @return void
	 */
	private function install_database_execute () {
	    # import SCHEMA file queries
	    $query  = file_get_contents("../../db/SCHEMA.sql");

	    # formulate queries
	    $queries = explode(";\n", $query);

	    # execute
	    foreach($queries as $q) {
			try { $this->Database_root->runQuery($q.";"); }
			catch (Exception $e) {
				//unlock tables
				$this->Database_root->runQuery("UNLOCK TABLES;");
				//drop database
				try { $this->Database_root->runQuery("drop database if exists ". $this->db['name'] .";"); }
				catch (Exception $e) {
					$this->Result->show("danger", 'Cannot set permissions for user '. $db['user'] .': '.$e->getMessage(), true);
				}
				//print error
				$this->Result->show("danger", "Cannot install sql SCHEMA file: ".$e->getMessage()."<br>query that failed: <pre>$q</pre>", false);
				$this->Result->show("info", "Database dropped", false);
			}
	    }
	}










	/**
	 * @check methods
	 * ------------------------------
	 */

	/**
	 * Tries to connect to database
	 *
	 * @access public
	 * @param bool $redirect
	 * @return void
	 */
	public function check_db_connection ($redirect = false) {
		# try to connect
		try { $res = $this->Database->connect(); }
		catch (Exception $e) 	{
			$this->exception = $e->getMessage();
			# redirect ?
			if($redirect == true)  	{ $this->redirect_to_install (); }
			else					{ return false; }
		}
		# ok
		return true;
	}

	/**
	 * Checks if table exists
	 *
	 * @access public
	 * @param mixed $table
	 * @return void
	 */
	public function check_table ($table, $redirect = false) {
		# set query
		$query = "SELECT COUNT(*) AS `cnt` FROM information_schema.tables WHERE table_schema = '".$this->db['name']."' AND table_name = '$table';";
		# try to fetch count
		try { $table = $this->Database->getObjectQuery($query); }
		catch (Exception $e) 	{ if($redirect === true) $this->redirect_to_install ();	else return false; }
		# redirect if it is not existing
		if($table->cnt!=1) 	 	{ if($redirect === true) $this->redirect_to_install ();	else return false; }
		# ok
		return true;
	}

	/**
	 * This function redirects to install page
	 *
	 * @access private
	 * @return void
	 */
	private function redirect_to_install () {
		# redirect to install
		header("Location: ".BASE.create_link("install", null,null,null,null,true));
	}

	/**
	 * sets debugging if set in config.php file
	 *
	 * @access private
	 * @return void
	 */
	private function set_debugging () {
		require( dirname(__FILE__) . '/../../config.php' );
		if($debugging==true) { $this->debugging = true; }
	}

	/**
	 * Sets DB parmaeters
	 *
	 * @access private
	 * @return void
	 */
	private function set_db_params () {
		require( dirname(__FILE__) . '/../../config.php' );
		$this->db = $db;
	}









	/**
	 * @postinstallation functions
	 * ------------------------------
	 */

	/**
	 * Post installation settings update.
	 *
	 * @access public
	 * @param mixed $adminpass
	 * @param mixed $siteTitle
	 * @param mixed $siteURL
	 * @return void
	 */
	function postauth_update($adminpass, $siteTitle, $siteURL) {
		# update Admin pass
		$this->postauth_update_admin_pass ($adminpass);
		# update settings
		$this->postauth_update_settings ($siteTitle, $siteURL);
		# ok
		return true;
	}

	/**
	 * Updates admin password after installation
	 *
	 * @access public
	 * @param mixed $adminpass
	 * @return void
	 */
	public function postauth_update_admin_pass ($adminpass) {
		try { $this->Database->updateObject("users", array("password"=>$adminpass, "passChange"=>"No","username"=>"Admin"), "username"); }
		catch (Exception $e) { $this->Result->show("danger", $e->getMessage(), false); }
		return true;
	}

	/**
	 * Updates settings after installation
	 *
	 * @access private
	 * @param mixed $siteTitle
	 * @param mixed $siteURL
	 * @return void
	 */
	private function postauth_update_settings ($siteTitle, $siteURL) {
		try { $this->Database->updateObject("settings", array("siteTitle"=>$siteTitle, "siteURL"=>$siteURL,"id"=>1), "id"); }
		catch (Exception $e) { $this->Result->show("danger", $e->getMessage(), false); }
		return true;
	}










	/**
	 * @upgrade database
	 * -----------------
	 */

	/**
	 * Upgrade database checks and executes.
	 *
	 * @access public
	 * @return void
	 */
	public function upgrade_database () {
		# first check version
		$this->fetch_settings ();

		if($this->settings->version == VERSION)				{ $this->Result->show("danger", "Database already at latest version", true); }
		else {
			# check db connection
			if($this->check_db_connection(false)===false)  	{ $this->Result->show("danger", "Cannot connect to database", true); }
			# execute
			else {
				return $this->upgrade_database_execute ();
			}
		}
	}

	/**
	 * Execute database upgrade.
	 *
	 * @access private
	 * @return void
	 */
	private function upgrade_database_execute () {
		# set queries
		$queries = $this->get_upgrade_queries ();

	    # execute all queries
	    foreach($queries as $query) {
			try { $this->Database->runQuery($query); }
			catch (Exception $e) {
				# write log
				write_log( "Database upgrade", $e->getMessage()."<br>query: ".$query, 2 );
				# fail
				$this->Result->show("danger", _("Update: ").$e->getMessage()."<br>query: ".$query, true);
			}
	    }


		# all good, print it
		sleep(1);
		write_log( "Database upgrade", "Database upgraded from version ".$this->settings->version." to version ".VERSION.".".REVISION, 1 );
		return true;
	}

	/**
	 * Fetch all upgrade queries from DB files
	 *
	 * @access private
	 * @return void
	 */
	private function get_upgrade_queries () {
		$dir = dirname(__FILE__) . '/../../db/';
		$files = scandir($dir);

		# set queries
		foreach($files as $f) {
			//get only UPGRADE- for specific version
			if(substr($f, 0, 6) == "UPDATE") {
				$new_version = str_replace(".sql", "",substr($f, 8));
				if($new_version>$this->settings->version) {
					$queries[] = file_get_contents($dir.$f);
				}
			}
		}
		# return
		return $queries;
	}
}