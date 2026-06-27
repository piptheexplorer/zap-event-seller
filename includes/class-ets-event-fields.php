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

        $event_cpt = get_setting( 'event_cpt_slug', 'event' );

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