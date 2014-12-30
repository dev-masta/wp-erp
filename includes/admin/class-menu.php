<?php
namespace WeDevs\ERP;

/**
 * Administration Menu Class
 *
 * @package payroll
 */
class Admin_Menu {

    /**
     * Kick-in the class
     *
     * @return void
     */
    public function __construct() {
        add_action( 'init', array( $this, 'do_mode_switch' ), 99 );
        add_action( 'init', array( $this, 'do_company_switch' ), 99 );

        add_action( 'admin_menu', array( $this, 'admin_menu' ), 99 );
        add_action( 'admin_menu', array( $this, 'hide_admin_menus' ), 100 );
        add_action( 'wp_before_admin_bar_render', array( $this, 'hide_admin_bar_links' ), 100 );

        add_action( 'init', array( $this, 'tools_page_handler' ) );

        add_action( 'admin_bar_menu', array( $this, 'admin_bar_mode_switch' ), 9999 );
    }

    /**
     * Get the admin menu position
     *
     * @return int the position of the menu
     */
    public function get_menu_position() {
        return apply_filters( 'payroll_menu_position', 9999 );
    }


    /**
     * Mode/Context switch for ERP
     *
     * @param WP_Admin_Bar $wp_admin_bar The admin bar object
     */
    public function admin_bar_mode_switch( $wp_admin_bar ) {
        // bail if current user doesnt have cap
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $modules      = erp_get_modules();
        $current_mode = erp_get_current_module();

        // ERP Mode
        $title        = __( 'Switch ERP Mode', 'wp-erp' );
        $icon         = '<span class="ab-icon dashicons-randomize"></span>';
        $text         = sprintf( '%s: %s', __( 'ERP Mode', 'wp-erp' ), $current_mode['title'] );

        $wp_admin_bar->add_menu( array(
            'id'        => 'erp-mode-switch',
            'title'     => $icon . $text,
            'href'      => '#',
            'position'  => 0,
            'meta'      => array(
                'title' => $title
            )
        ) );

        foreach ($modules as $key => $module) {
            $wp_admin_bar->add_menu( array(
                'id'     => 'erp-mode-' . $key,
                'parent' => 'erp-mode-switch',
                'title'  => $module['title'],
                'href'   => wp_nonce_url( add_query_arg( 'erp-mode', $key ), 'erp_mode_nonce', 'erp_mode_nonce' )
            ) );
        }

        // Company Mode
        $companies       = erp_get_companies();
        $current_company = erp_get_current_company();
        $com_label       = ( false === $current_company ) ? __( '- None -', 'wp-erp' ) : $current_company->name;

        $com_icon        = '<span class="ab-icon dashicons-admin-home"></span>';
        $com_text        = sprintf( '%s: %s', __( 'Company', 'wp-erp' ), $com_label );

        $wp_admin_bar->add_menu( array(
            'id'        => 'erp-comp-switch',
            'title'     => $com_icon . $com_text,
            'href'      => '#',
            'position'  => 0,
            'meta'      => array(
                'title' => __( 'Switch Company', 'wp-erp' )
            )
        ) );

        if ( $companies ) {
            foreach ($companies as $key => $company) {
                $wp_admin_bar->add_menu( array(
                    'id'     => 'erp-comp-' . $key,
                    'parent' => 'erp-comp-switch',
                    'title'  => $company->name,
                    'href'   => wp_nonce_url( add_query_arg( 'erp-comp', $company->id ), 'erp_comp_swt_nonce', 'erp_comp_swt_nonce' )
                ) );
            }
        }
    }

    /**
     * Do the admin mode switch
     *
     * @return void
     */
    public function do_mode_switch() {
        global $current_user;

        // bail if current user doesnt have cap
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // check for our nonce
        if ( ! isset( $_GET['erp_mode_nonce'] ) || ! wp_verify_nonce( $_GET['erp_mode_nonce'], 'erp_mode_nonce' ) ) {
            return;
        }

        $modules = erp_get_modules();

        // now check for our query string
        if ( ! isset( $_REQUEST['erp-mode'] ) || ! array_key_exists( $_REQUEST['erp-mode'], $modules ) ) {
            return;
        }

        $new_mode = $_REQUEST['erp-mode'];

        update_user_meta( $current_user->ID, '_erp_mode', $new_mode );

        $redirect_to = apply_filters( 'erp_switch_redirect_to', admin_url( 'index.php' ), $new_mode );
        wp_redirect( $redirect_to );
        exit;
    }

    /**
     * Do the company switch from admin bar
     *
     * @return void
     */
    public function do_company_switch() {
        global $current_user;

        // check for our nonce
        if ( ! isset( $_GET['erp_comp_swt_nonce'] ) || ! wp_verify_nonce( $_GET['erp_comp_swt_nonce'], 'erp_comp_swt_nonce' ) ) {
            return;
        }

        $companies   = erp_get_companies();
        $company_ids = array_map( 'intval', wp_list_pluck( $companies, 'id' ) );

        // now check for our query string
        if ( ! isset( $_REQUEST['erp-comp'] ) || ! in_array( $_REQUEST['erp-comp'], $company_ids ) ) {
            return;
        }

        $company_id = (int) $_REQUEST['erp-comp'];
        update_user_meta( $current_user->ID, '_erp_company', $company_id );

        $redirect_to = apply_filters( 'erp_comp_switch_redirect_to', admin_url( 'index.php' ), $company_id );
        wp_redirect( $redirect_to );
        exit;
    }

    /**
     * Add menu items
     *
     * @return void
     */
    public function admin_menu() {
        add_menu_page( __( 'ERP', 'wp-erp' ), __( 'ERP Settings', 'wp-erp' ), 'manage_options', 'erp-company', array( $this, 'company_page' ), 'dashicons-admin-tools', $this->get_menu_position() );

        add_submenu_page( 'erp-company', __( 'Company', 'wp-erp' ), __( 'Company', 'wp-erp' ), 'manage_options', 'erp-company', array( $this, 'company_page' ) );
        add_submenu_page( 'erp-company', __( 'Tools', 'wp-erp' ), __( 'Tools', 'wp-erp' ), 'manage_options', 'erp-tools', array( $this, 'tools_page' ) );
        add_submenu_page( 'erp-company', __( 'Audit Log', 'wp-erp' ), __( 'Audit Log', 'wp-erp' ), 'manage_options', 'erp-audit-log', array( $this, 'log_page' ) );
        add_submenu_page( 'erp-company', __( 'Settings', 'wp-erp' ), __( 'Settings', 'wp-erp' ), 'manage_options', 'erp-settings', array( $this, 'employee_page' ) );
        add_submenu_page( 'erp-company', __( 'Add-Ons', 'wp-erp' ), __( 'Add-Ons', 'wp-erp' ), 'manage_options', 'erp-addons', array( $this, 'addon_page' ) );
    }

    /**
     * Hide default WordPress menu's
     *
     * @return void
     */
    function hide_admin_menus() {
        global $menu;

        $menus = get_option( '_erp_admin_menu', array() );

        if ( ! $menus ) {
            return;
        }

        foreach ($menus as $item) {
            remove_menu_page( $item );
        }

        remove_menu_page( 'edit-tags.php?taxonomy=link_category' );
        remove_menu_page( 'separator1' );
        remove_menu_page( 'separator2' );
        remove_menu_page( 'separator-last' );

        $position = 9998;
        $menu[$position] = array(
            0   =>  '',
            1   =>  'read',
            2   =>  'separator' . $position,
            3   =>  '',
            4   =>  'wp-menu-separator'
        );
    }

    /**
     * Hide default admin bar links
     *
     * @return void
     */
    function hide_admin_bar_links() {
        global $wp_admin_bar;

        $adminbar_menus = get_option( '_erp_adminbar_menu', array() );
        if ( ! $adminbar_menus ) {
            return;
        }

        foreach ($adminbar_menus as $item) {
            $wp_admin_bar->remove_menu( $item );
        }
    }

    /**
     * Handle all the forms in the tools page
     *
     * @return void
     */
    public function tools_page_handler() {

        // admin menu form
        if ( isset( $_POST['erp_admin_menu'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'erp-remove-menu-nonce' ) ) {
            if ( isset( $_POST['menu'] ) ) {
                $menu = array_map( 'strip_tags', $_POST['menu'] );
                update_option( '_erp_admin_menu', $menu );
            }

            if ( isset( $_POST['admin_menu'] ) ) {
                $bar_menu = array_map( 'strip_tags', $_POST['admin_menu'] );
                update_option( '_erp_adminbar_menu', $bar_menu );
            }
        }
    }

    /**
     * Handles the dashboard page
     *
     * @return void
     */
    public function dashboard_page() {
        echo "Dashboard!";
    }

    /**
     * Handles the employee page
     *
     * @return void
     */
    public function employee_page() {
        echo "employee!";
    }

    /**
     * Handles the company page
     *
     * @return void
     */
    public function company_page() {
        $action = isset( $_GET['action'] ) ? $_GET['action'] : 'list';
        $id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

        switch ($action) {
            case 'new':

                // create a dummy company
                $temp       = new \stdClass();
                $temp->id   = 0;
                $temp->name = '';
                $company    = new \WeDevs\ERP\Company( $temp );

                $template   = WPERP_VIEWS . '/company-editor.php';
                break;

            case 'edit':
                $company    = new \WeDevs\ERP\Company( $id );
                $template = WPERP_VIEWS . '/company-editor.php';
                break;

            default:
                $template = WPERP_VIEWS . '/company.php';
                break;
        }

        if ( file_exists( $template ) ) {
            include $template;
        }
    }

    /**
     * Handles the company locations page
     *
     * @return void
     */
    public function locations_page() {
        include_once dirname( __FILE__ ) . '/views/locations.php';
    }

    /**
     * Handles the tools page
     *
     * @return void
     */
    public function tools_page() {
        include_once dirname( __FILE__ ) . '/views/tools.php';
    }

    /**
     * Handles the log page
     *
     * @return void
     */
    public function log_page() {
        include_once dirname( __FILE__ ) . '/views/log.php';
    }

    /**
     * Handles the log page
     *
     * @return void
     */
    public function addon_page() {
        include_once dirname( __FILE__ ) . '/views/add-ons.php';
    }
}

return new Admin_Menu();