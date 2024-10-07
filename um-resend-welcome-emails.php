<?php
/**
 * Plugin Name:     Ultimate Member - Resend Welcome and Approval emails
 * Description:     Extension to Ultimate Member for resending the Welcome and Account Approved emails from UM Action dropdown in WP All Users page.
 * Version:         1.2.0 
 * Requires PHP:    7.4
 * Author:          Miss Veronica
 * License:         GPL v3 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI:      https://github.com/MissVeronica
 * Plugin URI:      https://github.com/MissVeronica/um-resend-welcome-emails
 * Update URI:      https://github.com/MissVeronica/um-resend-welcome-emails
 * Text Domain:     ultimate-member
 * Domain Path:     /languages
 * UM version:      2.8.7
 */
if ( ! defined( 'ABSPATH' ) ) exit; 
if ( ! class_exists( 'UM' ) ) return;

class UM_Resend_Welcome_Emails {

    public $resend = false;

    public $actions_list = array( 'um_welcome_resend', 'um_approved_resend' );

    function __construct( ) {

        if ( version_compare( ultimatemember_version, '2.8.7' ) == -1 ) {

            add_filter( 'um_admin_bulk_user_actions_hook',         array( $this, 'um_admin_bulk_user_actions_resend_welcome_286' ), 10, 1 );
            add_action( 'um_admin_custom_hook_um_welcome_resend',  array( $this, 'um_admin_custom_hook_um_welcome' ), 10, 1 );
            add_action( 'um_admin_custom_hook_um_approved_resend', array( $this, 'um_admin_custom_hook_um_approved' ), 10, 1 );

        } else {

            add_filter( 'bulk_actions-users',                      array( $this, 'um_admin_bulk_user_actions_resend_welcome_287' ), 10, 1 );

            if ( isset( $_REQUEST['action'] ) && in_array( $_REQUEST['action'], $this->actions_list )) {

                add_filter( 'handle_bulk_actions-users',           array( $this, 'um_admin_custom_hook_handle' ), 10, 3 );
            }

            add_filter( 'um_adm_action_custom_update_notice',      array( $this, 'resend_email_admin_notice' ), 99, 2 );
        }

        add_filter( 'um_email_send_subject',            array( $this, 'um_email_resend_subject' ) , 10, 2 );
        add_filter( 'um_settings_structure',            array( $this, 'um_settings_structure_resend_emails' ), 10, 1 );
        add_filter( 'um_template_tags_patterns_hook',   array( $this, 'my_template_tags_patterns' ), 10, 1 );
        add_filter( 'um_template_tags_replaces_hook',   array( $this, 'my_template_tags_replaces' ), 10, 1 );
    }

    public function um_email_resend_subject( $subject, $template ) {

        if ( $this->resend  && ! empty( UM()->options()->get( 'mail_resend_subject_pretext' ) )) {
            $subject = sanitize_text_field( UM()->options()->get( 'mail_resend_subject_pretext' ) . $subject );
        }

        return $subject;
    }

    public function resend_email_admin_notice( $message, $update ) {

        if ( in_array( $update, $this->actions_list ) && isset( $_REQUEST['result'] )) {

            $result = explode( '_', sanitize_text_field( $_REQUEST['result'] ) );
            $notice = esc_html__( 'Invalid', 'ultimate-member' );

            if ( is_array( $result ) && count( $result ) == 2 ) {
                switch( $result[0] ) {

                    case 'w':   $notice = sprintf( esc_html__( 'Resend Welcome email to %d Users', 'ultimate-member' ), intval( $result[1] ) );
                                break;

                    case 'a':   $notice = sprintf( esc_html__( 'Resend Approved email to %d Users', 'ultimate-member' ), intval( $result[1] ) );
                                break;

                    case 'c':   $notice = esc_html__( 'No user selected for resending email.', 'ultimate-member' );
                                break;

                    default:    break;
                }
            }

            $message[]['content'] = $notice;
        }

        return $message;
    }

    public function um_admin_bulk_user_actions_resend_welcome_286( $actions ) {

        $actions['um_welcome_resend']  = array( 'label' => esc_html__( 'Resend Welcome email', 'ultimate-member' ));
        $actions['um_approved_resend'] = array( 'label' => esc_html__( 'Resend Approved email', 'ultimate-member' ));

        return $actions;
    }

    public function um_admin_bulk_user_actions_resend_welcome_287( $actions ) {

        $rolename = UM()->roles()->get_priority_user_role( get_current_user_id() );
        $role     = get_role( $rolename );

        if ( null === $role ) {
            return $actions;
        }

        if ( ! current_user_can( 'edit_users' ) && ! $role->has_cap( 'edit_users' ) ) {
            return $actions;
        }

        $sub_actions = array();

        $sub_actions['um_welcome_resend']  = esc_html__( 'Resend Welcome email', 'ultimate-member' );
        $sub_actions['um_approved_resend'] = esc_html__( 'Resend Approved email', 'ultimate-member' );

        $actions[ esc_html__( 'UM Resend', 'ultimate-member' ) ] = $sub_actions;

        return $actions;
    }

    public function um_admin_custom_hook_handle( $sendback, $current_action, $userids ) {

        if ( in_array( $current_action, $this->actions_list )) {

            $count = 0;

            switch( $current_action ) {

                case 'um_welcome_resend':  foreach( $userids as $userid ) {
                                                $this->um_admin_custom_hook_um_welcome( $userid );
                                                $count++;
                                            }
                                            $result = 'w_' . $count;
                                            break;

                case 'um_approved_resend': foreach( $userids as $userid ) {
                                                $this->um_admin_custom_hook_um_approved( $userid );
                                                $count++;
                                            }
                                            $result = 'a_' . $count;
                                            break;

                default:                    $result = 'c_0';
                                            break;
            }

            $url = add_query_arg(
                array(
                        'update'         => $current_action,
                        'result'         => $result,
                        '_wpnonce'       => wp_create_nonce( $current_action ),
                ),
                admin_url( 'users.php' )
            );

            wp_safe_redirect( $url );
            exit;
        }
    }

    public function um_admin_custom_hook_um_welcome( $user_id ) {

        $this->resend = true;
        UM()->mail()->send( um_user( 'user_email' ), 'welcome_email' );
    }

    public function um_admin_custom_hook_um_approved( $user_id ) {

        $this->resend = true;
        UM()->mail()->send( um_user( 'user_email' ), 'approved_email' );
    }

    public function my_template_tags_patterns( $search ) {

	    $search[] = '{password_reset_link}';
	    return $search;
    }

    public function my_template_tags_replaces( $replace ) {

	    $replace[] = um_user( 'password_reset_link' );
	    return $replace;
    }

    public function um_settings_structure_resend_emails( $settings ) {

        $settings['email']['fields'][] = array(
            'id'          => 'mail_resend_subject_pretext',
            'type'        => 'text',
            'label'       => esc_html__( 'Resend Welcome emails - Subject pre-text', 'ultimate-member' ),
            'tooltip'     => esc_html__( 'Subject pre-text for resending of the Welcome and Approval emails', 'ultimate-member' ),
            'size'        => 'small',
        );

        return $settings;
    }
}

new UM_Resend_Welcome_Emails();
