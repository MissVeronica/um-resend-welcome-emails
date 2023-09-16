<?php
/**
 * Plugin Name:     Ultimate Member - Resend Welcome emails
 * Description:     Extension to Ultimate Member for resending the Welcome and Account Approved emails from UM Action dropdown in WP All Users page.
 * Version:         1.0.0 
 * Requires PHP:    7.4
 * Author:          Miss Veronica
 * License:         GPL v3 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI:      https://github.com/MissVeronica
 * Text Domain:     ultimate-member
 * Domain Path:     /languages
 * UM version:      2.6.10
 */
if ( ! defined( 'ABSPATH' ) ) exit; 
if ( ! class_exists( 'UM' ) ) return;

class UM_Resend_Welcome_Emails {

    public $resend = false;

    function __construct( ) {

        add_filter( 'um_admin_bulk_user_actions_hook',  array( $this, 'um_admin_bulk_user_actions_resend_welcome' ), 10, 1 );
        add_action( 'um_admin_custom_hook_um_welcome',  array( $this, 'um_admin_custom_hook_um_welcome' ), 10, 1 );
        add_action( 'um_admin_custom_hook_um_approved', array( $this, 'um_admin_custom_hook_um_approved' ), 10, 1 );
        add_filter( 'um_email_send_subject',            array( $this, 'um_email_resend_subject' ) , 10, 2 );
        add_filter( 'um_settings_structure',            array( $this, 'um_settings_structure_resend_emails' ), 10, 1 );
    }

    public function um_email_resend_subject( $subject, $template ) {

        if ( $this->resend  && ! empty( UM()->options()->get( 'mail_resend_subject_pretext' ) )) {
            $subject = sanitize_text_field( UM()->options()->get( 'mail_resend_subject_pretext' ) . $subject );
        }

        return $subject;
    }

    public function um_admin_bulk_user_actions_resend_welcome( $actions ) {

        $actions['um_welcome']  = array( 'label' => __( 'Resend Welcome email', 'ultimate-member' ));
        $actions['um_approved'] = array( 'label' => __( 'Resend Approved email', 'ultimate-member' ));

        return $actions;
    }

    public function um_admin_custom_hook_um_welcome( $user_id ) {

        $this->resend = true;
        UM()->mail()->send( um_user( 'user_email' ), 'welcome_email' );
    }

    public function um_admin_custom_hook_um_approved( $user_id ) {

        $this->resend = true;
        UM()->mail()->send( um_user( 'user_email' ), 'approved_email' );
    }

    public function um_settings_structure_resend_emails( $settings ) {

        $settings['email']['fields'][] = array(
            'id'          => 'mail_resend_subject_pretext',
            'type'        => 'text',
            'label'       => __( 'Resend Welcome emails - Subject pre-text', 'ultimate-member' ),
            'tooltip'     => __( 'Subject pre-text for resending of the Welcome and Approval emails', 'ultimate-member' ),
            'size'        => 'small',
        );

        return $settings;
    }
}

new UM_Resend_Welcome_Emails();
