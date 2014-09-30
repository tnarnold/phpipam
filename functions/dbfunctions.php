<?php

/**
 *
 * All functions to communicate with database
 *
 * Extended mysqli class to simplify result handling
 * 
 */
 
 
class database extends mysqli  {

	public $lastSqlId;	# last SQL insert id
	

	/* open database connection */
	public function __construct($host = NULL, $username = NULL, $dbname = NULL, $port = NULL, $socket = NULL, $printError = true) {
	
		# to throw exceptions
		mysqli_report(MYSQLI_REPORT_STRICT);

		# try to open
	    try { parent::__construct($host, $username, $dbname, $port, $socket); }
	    catch (Exception $e) { 
	        $error =  $e->getMessage(); 
	        if($printError) print ("<div class='alert alert-danger'>"._('Error').": ".$this->connect_error."</div>");
	        return false;
	    } 		
		
		# set charset
		$this->set_charset("utf8");
		
		return false;
	} 

	
	/* execute given query */
	function executeQuery( $query, $lastId = false, $printError = true ) 
	{
		# try to execute
	    try { $result = parent::query( $query ); }
	    catch (Exception $e) { 
	        $error =  $e->getMessage(); 
	        if($printError) print ("<div class='alert alert-danger'>"._('Error').": ".$this->connect_error."</div>");
	        return false;
	    } 
	    
	    # save id
	    $this->lastSqlId = $this->insert_id;

	    # return lastId if requested
	    if($lastId)	{ return $this->lastSqlId; }
	    else 		{ return true; }
	}
		
	
	/* get only 1 row */
    function getRow ($query) 
    {
		# try to execute
	    try { $result = parent::query( $query ); }
	    catch (Exception $e) { 
	        $error =  $e->getMessage(); 
	        if($printError) print ("<div class='alert alert-danger'>"._('Error').": ".$this->connect_error."</div>");
	        return false;
	    } 
	    
        /* get result */
        if ($result) {     
            $resp = $result->fetch_row();   
        }
        
        /* return result */
        return $resp;   
        
        /* free result */
		$result->close();  
    }
	
	
	/**
	 * get array of results
	 *
	 * returns multi-dimensional array
	 *     first dimension is number
	 *     from second on the values
	 * 
	 * if nothing is provided use assocciative results
	 *
	 */
	function getArray( $query , $assoc = true, $printError = true ) 
	{	
		/* save query */
		$tmpfile = fopen("/tmp/phpipam_queries.txt", "a") or die("Unable to open file!");
		fwrite($tmpfile, "$query \n");
		fclose($tmpfile);
		
		# try to execute query
		try { $result = parent::query( $query ); }
	    catch (Exception $e) { 
	        $error =  $e->getMessage(); 
	        if($printError) print ("<div class='alert alert-danger'>"._('Error').": ".$this->connect_error."</div>");
	        return false;
	    } 
        
		/** 
		 * fetch array of all access responses 
         * either assoc or num, based on input
         */
		if ($assoc == true) {
            while($row = $result->fetch_array(MYSQLI_ASSOC)) {
                $fields[] = $row;	
            }
		} 
		else {
            while($row = $result->fetch_array(MYSQLI_NUM)) {
                $fields[] = $row;	
            }
        }
        
		/* return result array */
		if(isset($fields)) {
        	return($fields);
        }
        else {
        	$fields = array();
        	return $fields;
        }
		
		/* free result */
		$result->close();	
	}


	
	/**
	 * get array of multiple results
	 *
	 * returns multi-dimensional array
	 *     first dimension is number
	 *     from second on the values
	 * 
	 * if nothing is provided use assocciative results
	 *
	 */
	function getMultipleResults( $query ) 
	{
        /* execute query */
		$result = parent::multi_query($query);
		
		/**
		 * get results to array
		 * first save it, than get each row from result and store it to active[]
		 */
		do { 
            $results = parent::store_result();
			
			/* save each to array (only first field) */
			while ( $row = $results->fetch_row() ) {
				$rows[] = $row[0];
			}
			$results->free();
		}
		while( parent::next_result() );
		
		/* return result array of rows */
		return($rows);
		
		/* free result */
		$result->close();	
	}
	
	
	/**
	 * Execute multiple querries!
	 *
	 */
	function executeMultipleQuerries( $query, $lastId = false ) 
	{	
        /* execute querries */
		$result = parent::multi_query($query);
		$this->lastSqlId   = $this->insert_id;

		/* if it failes throw new exception */
		if ( mysqli_error( $this ) ) {
            throw new exception( mysqli_error( $this ), mysqli_errno( $this ) ); 
      	}
        else {
       		if($lastId)	{ return $this->lastSqlId; }
        	else 		{ return true; }
        }
		
		/* free result */
		$result->close();	
	}


	/**
	 * Select database
	 *
	 */
	function selectDatabase( $database ) 
	{	
        /* execute querries */
		$result = parent::select_db($database);

		/* if it failes throw new exception */
		if ( mysqli_error( $this ) ) {
            throw new exception( mysqli_error( $this ), mysqli_errno( $this ) ); 
      	}
        else {
            return true;
        }
		
		/* free result */
		$result->close();	
	}
}

?>