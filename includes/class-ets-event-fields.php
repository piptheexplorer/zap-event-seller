<?php
namespace ETS;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Event_Fields {

    public function __construct() {
        add_action( 'acf/init', [ $this, 'register_fields' ] );
    }

    public function register_fields() {

        if ( ! function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }

        $event_cpt = get_setting( 'event_post_type', 'events' );

        acf_add_local_field_group([
            'key'    => 'group_ets_event_ticket_fields',
            'title'  => 'Event Ticket Settings',
            'fields' => [

                [
                    'key'   => 'field_ets_event_date',
                    'label' => 'Event Date',
                    'name'  => 'ets_event_date',
                    'type'  => 'date_picker',
                    'display_format' => 'd/m/Y',
                    'return_format'  => 'Y-m-d',
                ],

                [
                    'key'   => 'field_ets_event_time',
                    'label' => 'Event Time',
                    'name'  => 'ets_event_time',
                    'type'  => 'time_picker',
                    'display_format' => 'g:i a',
                    'return_format'  => 'H:i',
                ],

                [
                    'key'   => 'field_ets_event_location',
                    'label' => 'Event Location',
                    'name'  => 'ets_event_location',
                    'type'  => 'text',
                ],

                [
                    'key'   => 'field_ets_event_map',
                    'label' => 'Event Map / Address Embed',
                    'name'  => 'ets_event_map',
                    'type'  => 'textarea',
                    'instructions' => 'Optional map embed, address, or extra venue notes.',
                ],

                [
                    'key'          => 'field_ets_ticket_types',
                    'label'        => 'Ticket Types',
                    'name'         => 'ets_ticket_types',
                    'type'         => 'repeater',
                    'button_label' => 'Add Ticket Type',
                    'layout'       => 'block',
                    'min'          => 1,
                    'sub_fields'   => [

                        [
                            'key'   => 'field_ets_ticket_label',
                            'label' => 'Ticket Label',
                            'name'  => 'label',
                            'type'  => 'text',
                            'placeholder' => 'Adult, Child, VIP, Parking',
                        ],

                        [
                            'key'   => 'field_ets_ticket_description',
                            'label' => 'Description',
                            'name'  => 'description',
                            'type'  => 'textarea',
                            'rows'  => 3,
                        ],

                        [
                            'key'     => 'field_ets_ticket_price',
                            'label'   => 'Price',
                            'name'    => 'price',
                            'type'    => 'number',
                            'prepend' => '£',
                            'step'    => '0.01',
                            'min'     => 0,
                        ],

                        [
                            'key'   => 'field_ets_ticket_stock',
                            'label' => 'Stock',
                            'name'  => 'stock',
                            'type'  => 'number',
                            'min'   => 0,
                            'instructions' => 'Optional. Leave blank for unlimited.',
                        ],

                        [
                            'key'           => 'field_ets_ticket_image',
                            'label'         => 'Ticket Image / PDF Design',
                            'name'          => 'image',
                            'type'          => 'image',
                            'return_format' => 'array',
                            'preview_size'  => 'medium',
                            'library'       => 'all',
                        ],
                    ],
                ],


                [
                    'key'          => 'field_ets_event_addons',
                    'label'        => 'Add-ons / Upsells',
                    'name'         => 'ets_event_addons',
                    'type'         => 'repeater',
                    'button_label' => 'Add Add-on',
                    'layout'       => 'block',
                    'instructions' => 'Optional extras customers can add to their booking, such as parking, programmes, food vouchers or merchandise.',
                    'sub_fields'   => [
                        [
                            'key'   => 'field_ets_addon_name',
                            'label' => 'Add-on Name',
                            'name'  => 'name',
                            'type'  => 'text',
                            'placeholder' => 'Parking Pass, Food Voucher, Event Programme',
                        ],
                        [
                            'key'   => 'field_ets_addon_description',
                            'label' => 'Description',
                            'name'  => 'description',
                            'type'  => 'textarea',
                            'rows'  => 3,
                        ],
                        [
                            'key'     => 'field_ets_addon_price',
                            'label'   => 'Price',
                            'name'    => 'price',
                            'type'    => 'number',
                            'prepend' => '£',
                            'step'    => '0.01',
                            'min'     => 0,
                        ],
                        [
                            'key'   => 'field_ets_addon_stock',
                            'label' => 'Stock',
                            'name'  => 'stock',
                            'type'  => 'number',
                            'min'   => 0,
                            'instructions' => 'Optional. Leave blank for unlimited.',
                        ],
                        [
                            'key'   => 'field_ets_addon_applies_to',
                            'label' => 'Applies to Ticket Label',
                            'name'  => 'applies_to',
                            'type'  => 'text',
                            'instructions' => 'Optional. Enter a ticket label such as VIP to only show this add-on when that ticket type is available. Leave blank to show for all tickets.',
                        ],
                        [
                            'key'           => 'field_ets_addon_image',
                            'label'         => 'Add-on Image',
                            'name'          => 'image',
                            'type'          => 'image',
                            'return_format' => 'array',
                            'preview_size'  => 'medium',
                            'library'       => 'all',
                        ],
                    ],
                ],

                [
                    'key'           => 'field_ets_ticket_design',
                    'label'         => 'Print Ticket Background / Design',
                    'name'          => 'ets_ticket_design',
                    'type'          => 'image',
                    'return_format' => 'array',
                    'preview_size'  => 'medium',
                    'library'       => 'all',
                    'instructions'  => 'Optional background image used by the classic landscape PDF ticket design.',
                ],

                [
                    'key'           => 'field_ets_ticket_pdf_layout',
                    'label'         => 'Ticket PDF Design',
                    'name'          => 'ets_ticket_pdf_layout',
                    'type'          => 'select',
                    'choices'       => [
                        'classic_landscape' => 'Classic landscape ticket',
                        'digital_wallet'    => 'Digital wallet ticket',
                        'modern_card'       => 'Modern card ticket',
                    ],
                    'default_value' => 'classic_landscape',
                    'ui'            => 1,
                    'instructions'  => 'Choose the PDF design used when customers download or receive digital tickets.',
                ],

                [
                    'key'           => 'field_ets_ticket_style',
                    'label'         => 'Default Ticket Layout',
                    'name'          => 'ets_ticket_style',
                    'type'          => 'select',
                    'choices'       => [
                        'table' => 'Table layout',
                        'cards' => 'Card layout',
                    ],
                    'default_value' => 'table',
                    'ui'            => 1,
                ],
            ],

            'location' => [
                [
                    [
                        'param'    => 'post_type',
                        'operator' => '==',
                        'value'    => $event_cpt,
                    ],
                ],
            ],
        ]);
    }
}