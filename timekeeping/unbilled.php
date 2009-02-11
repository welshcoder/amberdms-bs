<?php
/*
	unbilled.php

	access: projects_timegroup

	Displays all time which is current unprocessed.
*/


class page_output
{
	var $id;
	var $name_project;
	
	var $obj_menu_nav;
	var $obj_table;


	function check_permissions()
	{
		return user_permissions_get("projects_timegroup");
	}



	function check_requirements()
	{
		// do nothing
		return 1;
	}



	function execute()
	{
		/*
			Prepare array of time row IDs which do not belong to a paid time group
		*/

		$unbilled_ids = array();


		// select non-group time records
		$sql_obj		= New sql_query;
		$sql_obj->string	= "SELECT id FROM timereg WHERE groupid='0'";
		$sql_obj->execute();

		if ($sql_obj->num_rows())
		{
			$sql_obj->fetch_array();

			foreach ($sql_obj->data as $data_tmp)
			{
				// we store the ID inside an array key, since they are unique
				// and this will prevent us needed to check for the existance of
				// the ID already.
				$unbilled_ids[ $data_tmp["id"] ] = "on";
			}
		}

		unset($sql_obj);


		// select unpaid group IDs
		$sql_obj		= New sql_query;
		$sql_obj->string	= "SELECT id FROM time_groups WHERE invoiceid='0'";
		$sql_obj->execute();

		if ($sql_obj->num_rows())
		{
			$sql_obj->fetch_array();

			foreach ($sql_obj->data as $data_group)
			{
				// fetch all the time reg IDs belonging this group
				$sql_reg_obj		= New sql_query;
				$sql_reg_obj->string	= "SELECT id FROM timereg WHERE groupid='". $data_group["id"] ."'";
				$sql_reg_obj->execute();

				if ($sql_reg_obj->num_rows())
				{
					$sql_reg_obj->fetch_array();

					foreach ($sql_reg_obj->data as $data_tmp)
					{
						// we store the ID inside an array key, since they are unique
						// and this will prevent us needed to check for the existance of
						// the ID already.
						$unbilled_ids[ $data_tmp["id"] ] = "on";
					}
				}

				unset($sql_reg_obj);
			}
		}

		unset($sql_obj);




		/*
			Define table
		*/



		// establish a new table object
		$this->obj_table = New table;

		$this->obj_table->language	= $_SESSION["user"]["lang"];
		$this->obj_table->tablename	= "timereg_unbilled";

		// define all the columns and structure
		$this->obj_table->add_column("date", "date", "timereg.date");
		$this->obj_table->add_column("standard", "name_phase", "CONCAT_WS(' -- ', projects.name_project, project_phases.name_phase)");
		$this->obj_table->add_column("standard", "name_staff", "staff.name_staff");
		$this->obj_table->add_column("standard", "time_group", "time_groups.name_group");
		$this->obj_table->add_column("standard", "description", "timereg.description");
		$this->obj_table->add_column("hourmins", "time_booked", "timereg.time_booked");

		// defaults
		$this->obj_table->columns		= array("date", "name_phase", "name_staff", "time_group", "description", "time_booked");
		$this->obj_table->columns_order		= array("date", "name_phase");
		$this->obj_table->columns_order_options	= array("date", "name_phase", "name_staff", "time_group", "description");

		// define SQL structure
		$this->obj_table->sql_obj->prepare_sql_settable("timereg");
		$this->obj_table->sql_obj->prepare_sql_addfield("id", "timereg.id");
		$this->obj_table->sql_obj->prepare_sql_addfield("projectid", "projects.id");
		$this->obj_table->sql_obj->prepare_sql_addfield("employeeid", "timereg.employeeid");
		$this->obj_table->sql_obj->prepare_sql_addfield("timegroupid", "time_groups.id");
		$this->obj_table->sql_obj->prepare_sql_addfield("timegroupinvoiceid", "time_groups.invoiceid");
		$this->obj_table->sql_obj->prepare_sql_addjoin("LEFT JOIN staff ON timereg.employeeid = staff.id");
		$this->obj_table->sql_obj->prepare_sql_addjoin("LEFT JOIN time_groups ON timereg.groupid = time_groups.id");
		$this->obj_table->sql_obj->prepare_sql_addjoin("LEFT JOIN project_phases ON timereg.phaseid = project_phases.id");
		$this->obj_table->sql_obj->prepare_sql_addjoin("LEFT JOIN projects ON project_phases.projectid = projects.id");



		// provide list of valid IDs
		$unbilled_ids_keys	= array_keys($unbilled_ids);
		$unbilled_ids_count	= count($unbilled_ids_keys);
		$unbilled_ids_sql	= "";

		$i = 0;
		foreach ($unbilled_ids_keys as $id)
		{
			$i++;

			if ($i == $unbilled_ids_count)
			{
				$unbilled_ids_sql .= "timereg.id='$id' ";
			}
			else
			{
				$unbilled_ids_sql .= "timereg.id='$id' OR ";
			}
		}
				
		$this->obj_table->sql_obj->prepare_sql_addwhere("($unbilled_ids_sql)");
		

		
		/// Filtering/Display Options

		// fixed options
		$this->obj_table->add_fixed_option("id", $this->id);


		// acceptable filter options
		$structure = NULL;
		$structure["fieldname"] = "date_start";
		$structure["type"]	= "date";
		$structure["sql"]	= "date >= 'value'";
		$this->obj_table->add_filter($structure);

		$structure = NULL;
		$structure["fieldname"] = "date_end";
		$structure["type"]	= "date";
		$structure["sql"]	= "date <= 'value'";
		$this->obj_table->add_filter($structure);
		
		$structure = form_helper_prepare_dropdownfromdb("phaseid", "SELECT projects.name_project as label,
													project_phases.id as id, 
													project_phases.name_phase as label1
													FROM `projects` 
													LEFT JOIN project_phases ON project_phases.projectid = projects.id
													ORDER BY projects.name_project, project_phases.name_phase");
													
		$structure["sql"]	= "project_phases.id='value'";
		$this->obj_table->add_filter($structure);

		$structure		= form_helper_prepare_dropdownfromdb("employeeid", "SELECT id, name_staff as label FROM staff ORDER BY name_staff ASC");
		$structure["sql"]	= "timereg.employeeid='value'";
		$this->obj_table->add_filter($structure);

		$structure = NULL;
		$structure["fieldname"] = "searchbox";
		$structure["type"]	= "input";
		$structure["sql"]	= "timereg.description LIKE '%value%' OR project_phases.name_phase LIKE '%value%' OR staff.name_staff LIKE '%value%'";
		$this->obj_table->add_filter($structure);

		$structure = NULL;
		$structure["fieldname"]	= "groupby";
		$structure["type"]	= "radio";
		$structure["values"]	= array("none", "name_phase", "name_staff");
		$structure["defaultvalue"] = "none";
		$this->obj_table->add_filter($structure);




		// create totals
		$this->obj_table->total_columns	= array("time_booked");


		// load options form
		$this->obj_table->load_options_form();

		// add group by options
		if ($this->obj_table->filter["filter_groupby"]["defaultvalue"] != "none")
		{
			$this->obj_table->sql_obj->prepare_sql_addgroupby( $this->obj_table->filter["filter_groupby"]["defaultvalue"] );

			// replace timereg value with SUM query
			$this->obj_table->structure["time_booked"]["dbname"] = "SUM(timereg.time_booked)";

			switch ($this->obj_table->filter["filter_groupby"]["defaultvalue"])
			{
				case "name_staff":
					$this->obj_table->columns		= array("name_staff", "time_booked");
					$this->obj_table->columns_order		= array();
					$this->obj_table->columns_order_options	= array("name_staff");
				break;

				case "name_phase":
					$this->obj_table->columns		= array("name_phase", "time_booked");
					$this->obj_table->columns_order		= array();
					$this->obj_table->columns_order_options	= array("name_phase");
				break;


			}
		}

		
		// generate & execute SQL query			
		$this->obj_table->generate_sql();
		$this->obj_table->load_data_sql();

		// delete any rows which belong to processed time groups
		for ($i=0; $i < $this->obj_table->data_num_rows; $i++)
		{
			if ($this->obj_table->data[$i]["timegroupinvoiceid"])
			{
				$this->obj_table->data[$i] = NULL;	
			}
		}
	}

	function render_html()
	{
		// heading
		print "<h3>UNBILLED TIME</h3>";
		print "<p>This page shows all time which has not yet been added to an invoice.</p>";


		// display options form
		$this->obj_table->render_options_form();

		// Display table data
		if (!$this->obj_table->data_num_rows)
		{
			format_msgbox("info", "<p>There is currently no unbilled time matching your search filter options.</p>");
		}
		else
		{
		
			// time entry link
			if ($this->obj_table->filter["filter_groupby"]["defaultvalue"] == "none")
			{
				$structure = NULL;
				$structure["id"]["column"]		= "id";
				$structure["date"]["column"]		= "date";
				$structure["employeeid"]["column"]	= "employeeid";
				$this->obj_table->add_link("tbl_lnk_view_timeentry", "timekeeping/timereg-day-edit.php", $structure);
			}

			// project/phase ID
			$structure = NULL;
			$structure["id"]["column"]		= "projectid";
			$structure["column"]			= "name_phase";
			$this->obj_table->add_link("tbl_lnk_project", "projects/timebooked.php", $structure);

			// project/phase ID
			$structure = NULL;
			$structure["id"]["column"]		= "projectid";
			$structure["groupid"]["column"]		= "timegroupid";
			$structure["column"]			= "time_group";
			$this->obj_table->add_link("tbl_lnk_groupid", "projects/timebooked.php", $structure);



			$this->obj_table->render_table_html();


			// display CSV download link
			print "<p align=\"right\"><a href=\"index-export.php?mode=csv&page=timekeeping/unbilled.php\">Export as CSV</a></p>";
		}
	}


	function render_csv()
	{
		$this->obj_table->render_table_csv();
	}
	
}

?>
