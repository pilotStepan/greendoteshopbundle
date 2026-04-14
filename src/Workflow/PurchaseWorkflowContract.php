<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Workflow;

enum PurchaseWorkflowContract: string
{
    case NAME = 'purchase_flow';

    // PLACES
    case S_DRAFT = 'draft';
    case S_WISHLIST = 'wishlist';
    case S_CART = 'cart';
    case S_CANCELLED = 'cancelled';
    case S_COMPLETED = 'completed';

    // TRANSITIONS
    case T_INIT_WISHLIST = 'init_wishlist';
    case T_INIT_CART = 'init_cart';
    case T_CHECKOUT = 'checkout';
    case T_PAY_PAY = 'pay_pay';
    case T_PAY_RETRY = 'pay_retry';     // Retry payment from pay_failed state
    case T_PAY_FAIL = 'pay_fail';
    case T_LOG_SEND = 'log_send';
    case T_LOG_TO_DONE = 'log_to_done';         // Auto: log_shipped → log_track_done funnel
    case T_LOG_PICKUP_DONE = 'log_pickup_done'; // Manual: customer picked up → log_track_done funnel
    case T_CANCEL = 'cancel';
    case T_COMPLETE = 'complete';

    public static function eventName(string $event, self $transition = null): string
    {
        if ($transition === null) {
            return sprintf('workflow.%s.%s',
                self::NAME->value,
                $event,
            );
        }

        return sprintf('workflow.%s.%s.%s',
            self::NAME->value,
            $event,
            $transition->value,
        );
    }
}
