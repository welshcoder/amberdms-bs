<?php
/*
	customers/service-edit-process.php

	access: customers_write

	Allows new services to be added to customers, or existing ones to be modified
*/

// includes
require("../include/config.php");
require("../include/amberphplib/main.php");

require("../include/customers/inc_customers.php");
require("../include/services/inc_services.php");



if (user_permissions_get('customers_write'))
{
	/*
		Load Data
	*/
	$obj_customer				= New customer_services;
	$obj_customer->id			= @security_form_input_predefined("int", "customerid", 1, "");
	$obj_customer->id_service_customer	= @security_form_input_predefined("int", "id_service_customer", 0, "");


	if ($obj_customer->id_service_customer)
	{
		// standard fields
		$data["active"]			= @security_form_input_predefined("checkbox", "active", 0, "");


		// options
		$data["quantity"]		= @security_form_input_predefined("int", "quantity", 0, "");

		if (!$data["quantity"])
			$data["quantity"] = 1;	// all services must have at least 1
	
	}
	else
	{
		// standard fields
		$data["serviceid"]		= @security_form_input_predefined("any", "serviceid", 1, "");
		$data["date_period_first"]	= @security_form_input_predefined("date", "date_period_first", 1, "");
		$data["date_period_next"]	= $data["date_period_first"];
	}


	// general details		
	$data["name_service"]		= @security_form_input_predefined("any", "name_service", 0, "");
	$data["description"]		= @security_form_input_predefined("any", "description", 0, "");




	/*
		Verify Data
	*/


	// check that the specified customer actually exists
	if (!$obj_customer->verify_id())
	{
		log_write("error", "process", "The customer you have attempted to edit - ". $obj_customer->id ." - does not exist in this system.");
	}
	else
	{
		if ($obj_customer->id_service_customer)
		{
			// are we editing an existing service? make sure it exists and belongs to this customer
			if (!$obj_customer->verify_id_service_customer())
			{
				log_write("error", "process", "The service you have attempted to edit - ". $obj_customer->id_service_customer ." - does not exist in this system.");
			}
			else
			{
				$obj_customer->load_data();
				$obj_customer->load_data_service();
			}
		}
	}



	// verify the service ID is valid
	if (!$obj_customer->id_service_customer)
	{
		$obj_customer->obj_service->id	= $data["serviceid"];

		if (!$obj_customer->obj_service->verify_id())
		{
			log_write("error", "process", "Unable to find service ". $obj_customer->obj_service->id ."");
		}
		else
		{
			$obj_customer->obj_service->load_data();
		}
	}



	/*
		Check for any errors
	*/
	if (error_check())
	{	
		$_SESSION["error"]["form"]["service_view"] = "failed";
		header("Location: ../index.php?page=customers/service-edit.php&customerid=". $obj_customer->id ."&serviceid=". $obj_customer->id_service_customer);
		exit(0);
	}
	else
	{
		if (!$obj_customer->id_service_customer)
		{
			/*
				Add new service
			*/

			// assign service to customer
			$obj_customer->service_add($data["date_period_first"]);

			// update service item option information
			$obj_customer->obj_service->option_type			= "customer";
			$obj_customer->obj_service->option_type_id		= $obj_customer->id_service_customer;

			$obj_customer->obj_service->data = array();
			$obj_customer->obj_service->load_data_options();

			$obj_customer->obj_service->data["description"]		= $data["description"];

			$obj_customer->obj_service->action_update_options();

		}
		else
		{
			/*
				Adjust an existing service
			*/


			// enable/disable service if needed
			if ($obj_customer->service_get_status() != $data["active"])
			{
				if ($data["active"])
				{
					// service has been enabled
					$obj_customer->service_enable();
				}
				else
				{
					// service has been disabled
					$obj_customer->service_disable();
				}
			}


			// clear data so that we can update the options
			$obj_customer->obj_service->data = array();
			$obj_customer->obj_service->load_data_options();

			$obj_customer->obj_service->data["description"]		= $data["description"];
			$obj_customer->obj_service->data["name_service"]	= $data["name_service"];

			$obj_customer->obj_service->action_update_options();

		}

		// return to services page
		header("Location: ../index.php?page=customers/services.php&id=". $obj_customer->id );
		exit(0);
			
	}

	/////////////////////////
	
}
else
{
	// user does not have perms to view this page/isn't logged on
	error_render_noperms();
	header("Location: ../index.php?page=message.php");
	exit(0);
}


?>
