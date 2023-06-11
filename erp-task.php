<?php
/*
 * Plugin Name: ERP Task
 * Plugin URI: #
 * Description: MFlow api json data.
 * Version: 1.0
 * Requires at least: 5.2
 * Requires PHP: 7.2
 * Author: Bishal GC
 * Author URI:#
 */

require_once plugin_dir_path(__FILE__) . "includes/class-Mflow-integration.php";

function instantiate_mflow_erp_integration()
{
    // Instantiate the class
    $mflow_erp_integration = new MFlow_ERP_Integration();
}

function mflow_erp_integration_activate()
{
    // Load the function to instantiate the class
    instantiate_mflow_erp_integration();

    // Add any additional activation tasks here
    // ...
}

// Register the activation hook
register_activation_hook(__FILE__, "mflow_erp_integration_activate");