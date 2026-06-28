<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( empty( $addons ) || ! is_array( $addons ) ) {
    return;
}
?>
<div class="ets-addons-wrapper mt-6 mb-8">
    <h3 class="text-lg mb-2">Enhance your booking</h3>
    <p class="text-sm opacity-80 mb-4">Add optional extras to your order.</p>

    <div class="ets-addons-grid grid gap-4 md:grid-cols-2">
        <?php foreach ( $addons as $index => $addon ) :
            $name        = $addon['name'] ?? '';
            $description = $addon['description'] ?? '';
            $price       = isset( $addon['price'] ) ? (float) $addon['price'] : 0;
            $image_url   = \ETS\normalise_image_url( $addon['image'] ?? '' );
            $remaining   = $addon['_ets_remaining'] ?? null;
            $sold_out    = ! empty( $addon['_ets_sold_out'] );
            $applies_to  = $addon['applies_to'] ?? '';
            $context     = sanitize_key( (string) ( $addon['context'] ?? '' ) );
            $scope       = sanitize_key( (string) ( $addon['scope'] ?? '' ) );
            $addon_label = '';

            if ( $applies_to ) {
                $addon_label = sprintf( __( 'For %s ticket', 'ets' ), $applies_to );
            } elseif ( in_array( $context, [ 'event', 'legacy' ], true ) || in_array( $scope, [ 'event', 'both' ], true ) ) {
                $addon_label = __( 'Event add-on', 'ets' );
            }

            if ( ! $name ) { continue; }
        ?>
            <div class="ets-addon-card border border-gray-200/50 rounded-xl p-3" data-applies-to="<?php echo esc_attr( sanitize_title( $applies_to ) ); ?>">
                <?php if ( $image_url ) : ?>
                    <img src="<?php echo esc_url( $image_url ); ?>" alt="" class="mb-3 rounded-lg object-cover w-full h-28">
                <?php endif; ?>

                <div class="flex items-start justify-between gap-3 mb-1">
                    <h4 class="text-base font-semibold"><?php echo esc_html( $name ); ?></h4>
                    <?php if ( $addon_label ) : ?>
                        <span class="ets-addon-context-label inline-flex items-center rounded-full bg-indigo-950/60 px-2 py-1 text-[11px] font-semibold uppercase tracking-wide text-indigo-100">
                            <?php echo esc_html( $addon_label ); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <?php if ( $description ) : ?>
                    <p class="text-sm opacity-80 mb-2"><?php echo esc_html( $description ); ?></p>
                <?php endif; ?>

                <div class="flex items-center justify-between gap-3 border-t border-gray-200/30 pt-3 mt-3">
                    <div>
                        <strong><?php echo esc_html( \ETS\esc_money_gbp( $price ) ); ?></strong>
                        <div class="text-xs opacity-70">
                            <?php if ( $remaining === null ) : ?>
                                Available
                            <?php elseif ( $sold_out ) : ?>
                                Sold out
                            <?php else : ?>
                                <?php echo esc_html( (string) $remaining ); ?> left
                            <?php endif; ?>
                        </div>
                    </div>
                    <input type="number"
                           class="ets-addon-qty-input w-20 text-center size-6 text-xs border border-indigo-700/60 bg-indigo-700/80 text-white rounded-md"
                           data-price="<?php echo esc_attr( $price ); ?>"
                           name="ets_addons[<?php echo esc_attr( $index ); ?>][qty]"
                           min="0"
                           <?php echo $remaining !== null ? 'max="' . esc_attr( $remaining ) . '"' : ''; ?>
                           value="0"
                           <?php disabled( $sold_out ); ?>>
                </div>

                <input type="hidden" name="ets_addons[<?php echo esc_attr( $index ); ?>][addon_key]" value="<?php echo esc_attr( $index ); ?>">
                <input type="hidden" name="ets_addons[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $name ); ?>">
                <input type="hidden" name="ets_addons[<?php echo esc_attr( $index ); ?>][price]" value="<?php echo esc_attr( $price ); ?>">
                <input type="hidden" name="ets_addons[<?php echo esc_attr( $index ); ?>][image]" value="<?php echo esc_url( $image_url ); ?>">
            </div>
        <?php endforeach; ?>
    </div>
</div>
