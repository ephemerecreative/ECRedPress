<?php
require_once __DIR__ . "/ECRedPressAdminHandlers.php";

class ECRedPressAdmin {
    public function __construct()
    {
        $this->prep_admin_main();
        $this->prep_clear_cache();
    }

    /**
     * Setup the main page admin.
     */
    private function prep_admin_main()
    {
        function ecrp_setup_admin_main()
        {
            add_menu_page( 'ECRedPress', 'ECRedPress', 'manage_options', 'ecrp', 'ECRedPressAdminHandlers::admin_main' );
        }

        add_action("admin_menu", "ecrp_setup_admin_main");
    }

    private function prep_clear_cache()
    {
        function ecrp_setup_clear_cache()
        {
            add_submenu_page("ecrp", "Clear Cache", "Clear Cache", "edit_pages", "ecrp-clear-cache", "ECRedPressAdminHandlers::clear_cache");
        }

        add_action("admin_menu", "ecrp_setup_clear_cache");
    }
}