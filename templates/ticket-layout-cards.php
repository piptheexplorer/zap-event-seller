<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="ets-ticket-hero bg-slate-800 text-center border-b border-gray-300/50">
    <div class="p-5">
        <?php if ( $event_heading ) : ?>
            <h2 class="ets-heading uppercase text-2xl md:text-xl tracking-widest font-thin mt-3 mb-1"><?php echo esc_html( $event_heading ); ?></h2>
        <?php endif; ?>

        <?php if ( $event_title ) : ?>
            <h2 class="ets-title uppercase max-w-4xl ml-auto mr-auto text-2xl md:text-7xl font-bold mb-4"><?php echo esc_html( $event_title ); ?></h2>
        <?php endif; ?>

        <?php if ( $event_desc ) : ?>
            <p class="ets-description text-lg/6 m-auto font-normal max-w-3xl mb-4"><?php echo nl2br( esc_html( $event_desc ) ); ?></p>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-0">
        <?php if ( $event_location ) : ?>
            <p class="ets-location text-xs font-light bg-indigo-700 p-2"><strong>Location:</strong> <?php echo esc_html( $event_location ); ?></p>
        <?php endif; ?>
        <?php if ( $event_time ) : ?>
            <p class="ets-time text-xs font-light p-2 bg-indigo-800"><strong>Start Time:</strong> <?php echo esc_html( $event_time ); ?></p>
        <?php endif; ?>
        <?php if ( $event_date ) : ?>
            <p class="ets-date text-xs font-light p-2 bg-indigo-900"><strong>Date:</strong> <?php echo esc_html( $event_date ); ?></p>
        <?php endif; ?>
    </div>
</div>

<form class="ets-ticket-form">
    <h3 class="text-xl mb-5 mt-5">Event Tickets</h3>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 items-start">
        <div class="ets-ticket-cards grid gap-4 md:grid-cols-1 lg:grid-cols-2 lg:col-span-2 mb-8">
            <?php foreach ( $ticket_types as $index => $ticket ) :
                $label       = $ticket['label'] ?? '';
                $description = $ticket['description'] ?? '';
                $price       = isset( $ticket['price'] ) ? (float) $ticket['price'] : 0;
                $image       = $ticket['image'] ?? [];
                $image_url   = \ETS\normalise_image_url( $image );
            ?>
                <div class="ets-ticket-card border border-gray-200/50 rounded-xl p-3 flex flex-col">
                    <?php if ( $image_url ) : ?>
                        <img src="<?php echo esc_url( $image_url ); ?>" class="mb-4 aspect-video w-full h-auto rounded-lg object-cover" alt="">
                    <?php endif; ?>

                    <h4 class="text-lg font-semibold mb-1"><?php echo esc_html( $label ); ?></h4>

                    <?php if ( $description ) : ?>
                        <p class="text-sm mb-3 opacity-80"><?php echo esc_html( $description ); ?></p>
                    <?php endif; ?>

                    <div class="grid grid-cols-2 mb-3">
                        <?php if ( $event_time ) : ?>
                            <p class="ets-time opacity-80 text-xs font-light"><strong>Start Time:</strong> <?php echo esc_html( $event_time ); ?></p>
                        <?php endif; ?>
                        <?php if ( $event_date ) : ?>
                            <p class="ets-date opacity-80 text-xs font-light"><strong>Date:</strong> <?php echo esc_html( $event_date ); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="mt-auto flex items-center pt-4 justify-between gap-3 border-t border-indigo-700/70">
                        <span class="text-lg font-bold"><?php echo esc_html( \ETS\esc_money_gbp( $price ) ); ?></span>
                        <input type="number" class="ets-qty-input w-20 text-center size-6 text-xs border border-indigo-700/60 bg-indigo-700/80 text-white rounded-md" data-price="<?php echo esc_attr( $price ); ?>" name="ets_tickets[<?php echo esc_attr( $index ); ?>][qty]" min="0" value="0">
                    </div>

                    <input type="hidden" name="ets_tickets[<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $label ); ?>">
                    <input type="hidden" name="ets_tickets[<?php echo esc_attr( $index ); ?>][price]" value="<?php echo esc_attr( $price ); ?>">
                    <input type="hidden" name="ets_tickets[<?php echo esc_attr( $index ); ?>][image]" value="<?php echo esc_url( $image_url ); ?>">
                </div>
            <?php endforeach; ?>
        </div>

        <div class="ets-ticket-sidebar border bg-primary text-white relative lg:sticky top-1  lg:top-1 h-auto border-gray-200/50 rounded-xl p-4 mb-8">
            <?php include ETS_PLUGIN_DIR . 'templates/ticket-form-footer.php'; ?>
        </div>
    </div>
</form>
