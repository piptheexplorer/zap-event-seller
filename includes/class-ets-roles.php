<?php
namespace ETS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Roles {

    public const CUSTOMER_ROLE = 'customer';

    public function __construct() {
        add_action( 'init', [ $this, 'ensure_customer_role' ] );
    }

    public static function activate(): void {
        self::add_customer_role();
    }

    public function ensure_customer_role(): void {
        self::add_customer_role();
    }

    public static function add_customer_role(): void {
        if ( get_role( self::CUSTOMER_ROLE ) ) {
            return;
        }

        add_role(
            self::CUSTOMER_ROLE,
            __( 'Customer', 'ets' ),
            [
                'read' => true,
            ]
        );
    }

    public static function assign_customer_role( int $user_id ): void {
        $user = get_user_by( 'id', $user_id );

        if ( ! $user instanceof \WP_User ) {
            return;
        }

        self::add_customer_role();

        if ( ! in_array( self::CUSTOMER_ROLE, (array) $user->roles, true ) ) {
            $user->add_role( self::CUSTOMER_ROLE );
        }
    }
}
