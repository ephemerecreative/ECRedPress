<?php
require_once __DIR__ . "/ECRedPressAdminHandlers.php";

class ECRedPressAdmin
{
    public function __construct()
    {
        $this->prep_admin_main();
        $this->prep_clear_cache();
        $this->prep_admin_bar();
    }

    /**
     * Setup the main page admin.
     */
    private function prep_admin_main()
    {
        add_action("admin_menu", function () {
            add_menu_page('ECRedPress', 'ECRedPress', 'manage_options', 'ecrp', 'ECRedPressAdminHandlers::admin_main');
        });
    }

    private function prep_clear_cache()
    {

        add_action("admin_menu", function () {
            add_submenu_page("ecrp", "Clear Cache", "Clear Cache", "edit_pages", "ecrp-clear-cache", "ECRedPressAdminHandlers::clear_cache");
        });
    }

    private function prep_admin_bar()
    {
        add_action('admin_bar_menu', function (\WP_Admin_Bar $bar) {
            $bar->add_menu(array(
                'id' => 'ecrp',
                'parent' => null,
                'group' => null,
                'title' => __('ECRedPress', 'ecrp'),
                'href' => '#',
            ));
        }, 999);

        add_action('admin_bar_menu', function (\WP_Admin_Bar $bar) {
            $bar->add_menu(array(
                'id' => 'ecrp-clear-cache',
                'parent' => 'ecrp',
                'group' => null,
                'title' => __('Clear Entire Cache', 'ecrp'),
                'href' => admin_url('admin.php?page=ecrp-clear-cache'),
            ));
        }, 999);

        add_action('admin_bar_menu', function (\WP_Admin_Bar $bar) {
            $bar->add_menu(array(
                'id' => 'ecrp-clear-page-cache',
                'parent' => 'ecrp',
                'group' => null,
                'title' => __('Clear Page Cache', 'ecrp'),
                'href' => add_query_arg("ecrpd", "true"),
            ));
        }, 999);
    }
}