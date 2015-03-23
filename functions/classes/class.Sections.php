<?php

/**
 *	phpIPAM Section class
 */

class Sections  {

	/* public variables */
	public $sections;						//(array of objects) to store sections, section ID is array index

	/* protected variables */
	protected $user = null;					//(object) for User profile

	/* object holders */
	protected $Result;						//for Result printing
	protected $Database;					//for Database connection




	/**
	 * __construct function
	 *
	 * @access public
	 * @return void
	 */
	public function __construct (Database_PDO $database) {
		# Save database object
		$this->Database = $database;
		# initialize Result
		$this->Result = new Result ();
	}

	/**
	 * Strip tags from array or field to protect from XSS
	 *
	 * @access public
	 * @param mixed $input
	 * @return void
	 */
	public function strip_input_tags ($input) {
		if(is_array($input)) {
			foreach($input as $k=>$v) { $input[$k] = strip_tags($v); }
		}
		else {
			$input = strip_tags($input);
		}
		# stripped
		return $input;
	}

	/**
	 * Changes empty array fields to specified character
	 *
	 * @access public
	 * @param array $fields
	 * @param string $char (default: "/")
	 * @return array
	 */
	public function reformat_empty_array_fields ($fields, $char = "/") {
		foreach($fields as $k=>$v) {
			if(is_null($v) || strlen($v)==0) {
				$out[$k] = 	$char;
			} else {
				$out[$k] = $v;
			}
		}
		# result
		return $out;
	}

	/**
	 * Function to verify checkbox if 0 length
	 *
	 * @access public
	 * @param mixed $field
	 * @return void
	 */
	public function verify_checkbox ($field) {
		return @$field==""||strlen(@$field)==0 ? 0 : $field;
	}










	/**
	 *	@update section methods
	 *	--------------------------------
	 */

	/**
	 * Modify section
	 *
	 * @access public
	 * @param mixed $action
	 * @param mixed $values
	 * @return void
	 */
	public function modify_section ($action, $values) {
		# strip tags
		$values = $this->strip_input_tags ($values);

		# fetch user
		$User = new User ($this->Database);
		$this->user = $User->user;

		# execute based on action
		if($action=="add")			{ return $this->section_add ($values); }
		elseif($action=="edit")		{ return $this->section_edit ($values); }
		elseif($action=="delete")	{ return $this->section_delete ($values); }
		elseif($action=="reorder")	{ return $this->section_reorder ($values); }
		else						{ return $this->Result->show("danger", _("Invalid action"), true); }
	}

	/**
	 * Creates new section
	 *
	 * @access private
	 * @param mixed $values
	 * @return void
	 */
	private function section_add ($values) {
		# null empty values
		$values = $this->reformat_empty_array_fields ($values, null);

		# unset possible delegates permissions and id
		unset($values['delegate'], $values['id']);

		# execute
		try { $this->Database->insertObject("sections", $values); }
		catch (Exception $e) {
			$this->Result->show("danger", _("Error: ").$e->getMessage(), false);
			write_log( "Sections creation", "Failed to create new section<hr>".$e->getMessage()."<hr>".array_to_log($values), 2, $this->user->username);
			return false;
		}
		# write changelog
		write_changelog('section', "delete", 'success', array(), $values);
		# ok
		write_log( "$table object creation", "New $table database object createdt<hr>".array_to_log($values), 0, $this->user->username);
		return true;
	}

	/**
	 * Edit existing section
	 *
	 * @access private
	 * @param mixed $values
	 * @return void
	 */
	private function section_edit ($values) {
		# null empty values
		$values = $this->reformat_empty_array_fields ($values, NULL);

		# save old values
		$old_section = $this->fetch_section ("id", $values['id']);

		# set delegations
		if(@$values['delegate']==1)	{ $delegate = true; }

		# unset possible delegates permissions and id
		unset($values['delegate']);

		# execute
		try { $this->Database->updateObject("sections", $values, "id"); }
		catch (Exception $e) {
			$this->Result->show("danger", _("Error: ").$e->getMessage(), false);
			write_log( "Section $old_section->name edit", "Failed to edit section $old_section->name<hr>".$e->getMessage()."<hr>".array_to_log($values), 2, $this->user->username);
			return false;
		}

		# delegate permissions if requested
        if(@$delegate) {
	        if(!$this->delegate_section_permissions ($values['id'], $values['permissions']))	{ $this->Result->show("danger", _("Failed to delegate permissions"), false); }
        }
		# write changelog
		write_changelog('section', "edit", 'success', $old_section, $values);
		# ok
		write_log( "Section $old_section->name edit", "Section $old_section->name edited<hr>".array_to_log($values), 0, $this->user->username);
		return true;
	}

	/**
	 * Delete section, subsections, subnets and ip addresses
	 *
	 * @access private
	 * @param mixed $values
	 * @return void
	 */
	private function section_delete ($values) {
		# subnets class
		$Subnets = new Subnets ($this->Database);

		# save old values
		$old_section = $this->fetch_section ("id", $values['id']);

		# check for subsections and store all ids
		$all_ids = $this->get_all_section_and_subsection_ids ($values['id']);		//array of section + all subsections

		# truncate and delete all subnets in all sections, than delete sections
		foreach($all_ids as $id) {
			$section_subnets = $Subnets->fetch_section_subnets ($id);
			if(sizeof($section_subnets)>0) {
				foreach($section_subnets as $ss) {
					//delete subnet
					$Subnets->modify_subnet("delete", array("id"=>$ss->id));
				}
			}
			# delete all sections
			try { $this->Database->deleteRow("sections", "id", $id); }
			catch (Exception $e) {
				write_log( "Section $old_section->name delete", "Failed to delete section $old_section->name<hr>".$e->getMessage()."<hr>".array_to_log($values), 2, $this->user->username);
				$this->Result->show("danger", _("Error: ").$e->getMessage(), false);
				return false;
			}
		}

		# write changelog
		write_changelog('section', "delete", 'success', $old_section, array());
		# log
		write_log( "Section $old_section->name delete", "Section $old_section->name deleted<hr>".array_to_log($old_section), 0, $this->user->username);
		return true;
	}

	/**
	 * Updates section order
	 *
	 * @access private
	 * @param mixed $order
	 * @return void
	 */
	private function section_reorder ($order) {
		# update each section
		foreach($order as $key=>$o) {
			# execute
			try { $this->Database->updateObject("sections", array("order"=>$o, "id"=>$key), "id"); }
			catch (Exception $e) {
				$this->Result->show("danger", _("Error: ").$e->getMessage(), false);
				return false;
			}
		}
	    return true;
	}










	/**
	 *	@fetch section methods
	 *	--------------------------------
	 */

	/**
	 * fetches all available sections
	 *
	 * @access public
	 * @return void
	 */
	public function fetch_all_sections () {
		# fetch all
		try { $sections = $this->Database->getObjects("sections", "order", true); }
		catch (Exception $e) {
			$this->Result->show("danger", _("Error: ").$e->getMessage());
			return false;
		}
		# save them to array
		if(sizeof($sections)>0)	{
			foreach($sections as $s) {
				$this->sections[$s->id] = $s;
			}
		}
		# response
		return sizeof($sections)>0 ? $sections : false;
	}

	/**
	 * Alias for fetch_all_sections
	 *
	 * @access public
	 * @return void
	 */
	public function fetch_sections () {
		return $this->fetch_all_sections ();
	}

	/**
	 * fetches section by specified method
	 *
	 * @access public
	 * @param string $method (default: "id")
	 * @param mixed $id
	 * @return void
	 */
	public function fetch_section ($method, $id) {
		# null method
		$method = is_null($method) ? "id" : $this->Database->escape($method);
		# check cache first
		if(isset($this->sections[$id]))	{
			return $this->sections[$id];
		}
		else {
			try { $section = $this->Database->getObjectQuery("SELECT * FROM `sections` where `$method` = ? limit 1;", array($id)); }
			catch (Exception $e) {
				$this->Result->show("danger", _("Error: ").$e->getMessage());
				return false;
			}
			# save to sections
			$this->sections[$id] = $section;
			#result
			return $section;
		}
	}

	/**
	 * Fetch subsections for specified sectionid
	 *
	 * @access public
	 * @param mixed $sectionid
	 * @return void
	 */
	public function fetch_subsections ($sectionid) {
		try { $subsections = $this->Database->getObjectsQuery("SELECT * FROM `sections` where `masterSection` = ? limit 1;", array($sectionid)); }
		catch (Exception $e) {
			$this->Result->show("danger", _("Error: ").$e->getMessage());
			return false;
		}
		# result
		return sizeof($subsections)>0 ? $subsections : array();
	}

	/**
	 * Fetches ids of section and possible subsections for deletion
	 *
	 * @access private
	 * @param int $id
	 * @return void
	 */
	private function get_all_section_and_subsection_ids ($id) {
		# check for subsections and store all ids
		$subsections = $this->fetch_subsections ($id);
		if(sizeof($subsections)>0) {
			foreach($subsections as $ss) {
				$subsections_ids[] = $ss->id;
			}
		}
		else {
				$subsections_ids = array();
		}
		//array of section + all subsections
		return  array_filter(array_merge($subsections_ids, array($id)));
	}

	/**
	 * Fetches all vlans in section
	 *
	 * @access public
	 * @param mixed $sectionId
	 * @return void
	 */
	public function fetch_section_vlans ($sectionId) {
		# set query
		$query = "select distinct(`v`.`vlanId`),`v`.`name`,`v`.`number`, `v`.`description` from `subnets` as `s`,`vlans` as `v` where `s`.`sectionId` = ? and `s`.`vlanId`=`v`.`vlanId` order by `v`.`number` asc;";
		# fetch
		try { $vlans = $this->Database->getObjectsQuery($query, array($sectionId)); }
		catch (Exception $e) {
			$this->Result->show("danger", _("Error: ").$e->getMessage());
			return false;
		}
		# result
		return sizeof($vlans)>0 ? $vlans : false;
	}

	/**
	 * Fetches all vrfs in section
	 *
	 * @access public
	 * @param mixed $sectionId
	 * @return void
	 */
	function fetch_section_vrfs ($sectionId) {
		# set query
		$query = "select distinct(`v`.`vrfId`),`v`.`name`,`v`.`description` from `subnets` as `s`,`vrf` as `v` where `s`.`sectionId` = ? and `s`.`vrfId`=`v`.`vrfId` order by `v`.`name` asc;";
		# fetch
		try { $vrfs = $this->Database->getObjectsQuery($query, array($sectionId)); }
		catch (Exception $e) {
			$this->Result->show("danger", _("Error: ").$e->getMessage());
			return false;
		}
		# result
		return sizeof($vrfs)>0 ? $vrfs : false;
	}










	/**
	 *	@permission section methods
	 *	--------------------------------
	 */

	/**
	 * Checks section permissions and returns group privilege for each section
	 *
	 * @access public
	 * @param mixed $permissions
	 * @return void
	 */
	public function parse_section_permissions($permissions) {
		# save to array
		$permissions = json_decode($permissions, true);
		# start Tools object
		$Tools = new Tools ($this->Database);
		if(sizeof($permissions)>0) {
	    	foreach($permissions as $key=>$p) {
	    		$group = $Tools->fetch_object("userGroups", "g_id", $key);
	    		$out[$group->g_id] = $p;
	    	}
	    }
	    # return array of groups
		return isset($out) ? $out : array();
	}

	/**
	 * returns permission level for specified section
	 *
	 *	3 = read/write/admin
	 *	2 = read/write
	 *	1 = read
	 *	0 = no access
	 *
	 * @access public
	 * @param obj $user
	 * @param int $sectionid
	 * @return void
	 */
	public function check_permission ($user, $sectionid) {
		# decode groups user belongs to
		$groups = json_decode($user->groups);

		# admins always has permission rwa
		if($user->role == "Administrator")		{ return 3; }
		else {
			# fetch section details and check permissions
			$section  = $this->fetch_section ("id", $sectionid);
			$sectionP = json_decode($section->permissions);

			# default permission is no access
			$out = 0;

			# for each group check permissions, save highest to $out
			if(sizeof($sectionP)>0) {
				foreach($sectionP as $sk=>$sp) {
					# check each group if user is in it and if so check for permissions for that group
					if(sizeof($groups)>0) {
						foreach($groups as $uk=>$up) {
							if($uk == $sk) {
								if($sp > $out) { $out = $sp; }
							}
						}
					}
				}
			}
			# return permission level
			return $out;
		}
	}

	/**
	 * This function returns permissions of group_id for each section
	 *
	 * @access public
	 * @param int $gid						//id of group to verify permissions
	 * @param bool $name (default: true)	//should index be name or id?
	 * @return array
	 */
	public function get_group_section_permissions ($gid, $name = true) {
		# fetch all sections
		$sections = $this->fetch_all_sections();

		# loop through sections and check if group_id in permissions
		foreach($sections as $section) {
			$p = json_decode($section->permissions, true);
			if(sizeof($p)>0) {
				if($name) {
					if(array_key_exists($gid, $p)) {
						$out[$section->name] = $p[$gid];
					}
				}
				else {
					if(array_key_exists($gid, $p)) {
						$out[$section->id] = $p[$gid];
					}
				}
			}
			# no permissions
			else {
				$out[$section->name] = 0;
			}
		}
		# return
		return $out;
	}

	/**
	 * Delegates section permissions to all belonging subnets
	 *
	 * @access private
	 * @param mixed $sectionId
	 * @param mixed $permissions
	 * @return void
	 */
	private function delegate_section_permissions ($sectionId, $permissions) {
		try { $this->Database->updateObject("subnets", array("permissions"=>$permissions, "sectionId"=>$sectionId), "sectionId"); }
		catch (Exception $e) {
			$this->Result->show("danger", _("Error: ").$e->getMessage());
			return false;
		}
		return true;
	}
}
?>